<?php
/**
 * 用户登录
 */

namespace App\Jobs;

use App\Model\WxCoupon;
use App\Model\WxCouponDetail;
use App\Model\WxWeekPlanInvites;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class MemberLogin extends Job implements SelfHandling, ShouldQueue
{
    protected $member;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->member      = $data['member'];
        Log::info(__CLASS__, collect($data)->toArray());
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            // 查询是否有可领取优惠券
            $now = Carbon::now();
            $coupons = WxCoupon::where('start_date', '<', $now)
                ->where('end_date', '>', $now)
                ->whereIn('scene', [2])
                ->select('coupon_id', 'duration')->get();
            if($coupons){
                $couponDetail = new WxCouponDetail();
                foreach ($coupons as $v){
                    try{
                        $couponDetail->create([
                            'coupon_id' => $v->coupon_id,
                            'member_id' => $this->member->member_id,
                            'start_time'=> $now,
                            'end_time'  => date('Y-m-d H:i:s', time()+$v->duration * 86400)
                        ]);
                    }catch (\Exception $e){}
                }
            }
        }catch (\Exception $e){
            Log::error(__CLASS__, [$e->getMessage()]);
        }
    }
}
