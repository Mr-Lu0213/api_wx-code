<?php
/**
 * 用户注册
 */

namespace App\Jobs;

use App\Model\WxCoupon;
use App\Model\WxCouponDetail;
use App\Model\WxWeekPlan;
use App\Model\WxWeekPlanInvites;
use App\Model\WxWeekPlanJoin;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class MemberReg extends Job implements SelfHandling, ShouldQueue
{
    protected $member;
    protected $referer_url;
    protected $headimgurl;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->member      = $data['member'];
        $this->referer_url = $data['referer_url'];
        $this->headimgurl  = $data['headimgurl'];
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
            // 查看是否有邀请信息
            $pase_url = parse_url_param($this->referer_url);
            if(!empty($pase_url['inviter_id'])){
                try{
                    $join = WxWeekPlanJoin::where([
                        'plan_id'   => $pase_url['plan_id'],
                        'member_id' => $pase_url['inviter_id'],
                        'status'    => 1
                    ])->first();
                    if(!empty($join)){
                        $plan = WxWeekPlan::find($pase_url['plan_id']);

                        $invite = new WxWeekPlanInvites();
                        $invite->plan_id    = $pase_url['plan_id'];
                        $invite->member_id  = $pase_url['inviter_id'];
                        $invite->invited_id = $this->member->member_id;
                        $invite->save();

                        $join->increment('invites_num');

                        if($join->invites_num >= $plan->invites_num){
                            $join->status = 2;
                            $join->save();
                        }
                    }

                }catch (\Exception $ee){}

            }

            // 查询是否有注册优惠券
            $now = Carbon::now();
            $coupons = WxCoupon::where('start_date', '<', $now)
                ->where('end_date', '>', $now)
                ->whereIn('scene', [1, 2])
                ->select('coupon_id', 'duration')->get();
            if($coupons){
                $couponDetail = new WxCouponDetail();
                foreach ($coupons as $v){
                    $couponDetail->create([
                        'coupon_id' => $v->coupon_id,
                        'member_id' => $this->member->member_id,
                        'start_time'=> $now,
                        'end_time'  => date('Y-m-d H:i:s', time()+$v->duration * 86400)
                    ]);
                }
            }

            // 处理用户头像
            $this->member->photo = put_object(file_get_contents($this->headimgurl));
            $this->member->save();

        }catch (\Exception $e){
            Log::error(__CLASS__, [$e->getMessage()]);
        }

    }
}
