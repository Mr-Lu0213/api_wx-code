<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use App\Jobs\SendWxMsg;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class WxOrder extends Model
{
    protected $table = 'wx_order';

    protected $primaryKey = 'order_id';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

    /**
     * 实付价格
     * @param $value
     */
    public function getMoneyAttribute($value)
    {
        return sprintf("%0.2f", $value / 100);
    }

    /**
     * 实付价格
     * @param $value
     */
    public function setMoneyAttribute($value)
    {
        $this->attributes['money'] =  $value * 100;
    }

    /**
     * 原始价格
     * @param $value
     */
    public function getOriginalMoneyAttribute($value)
    {
        return sprintf("%0.2f", $value / 100);
    }

    /**
     * 原始价格
     * @param $value
     */
    public function setOriginalMoneyAttribute($value)
    {
        $this->attributes['original_money'] =  $value * 100;
    }

    /**
     * 实付价格
     * @param $value
     */
    public function setBalancePaymentAttribute($value)
    {
        $this->attributes['balance_payment'] =  $value * 100;
    }

    /**
     * 获取内容详情链接
     */
    public function content_url()
    {
        $host = Config::get('api.h5_uri');
        switch ($this->type){
            case 1:
                return $host.'courseDetails?courseId='.$this->service_id;

            case 2:
                return $host.'expertDetails?expert_id='.$this->service_id;

            case 3:
                return $host.'plan';

            default:
                return $host;
        }
    }

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'member_id');
    }

    /**
     * 内容是否订阅
     * @param $type
     * @param $service_id
     * @param int $member_id
     * @return bool
     */
    public function is_order($type, $service_id, $member_id=0)
    {
        if(!$member_id){
            $member_id = get_member_id();
        }
        $where = [
            'status'     => 2,
            'type'       => $type,
            'member_id'  => $member_id,
            'service_id' => $service_id,
        ];

        // 系列课判断是否订阅专家
        if($type == 1){
            $course = ContentCourse::find($service_id);
            if($course->operate_type == 2){
                $where['type'] = 2;
                $where['service_id'] = $course->expert_id;
            }
        }

        // 查询有效订单
        return (boolean)$this->where($where)->first();
    }

    /**
     * 关联课程
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function course()
    {
        return $this->hasOne('App\Model\ContentCourse', 'course_id', 'service_id');
    }

    /**
     * 关联专家
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function expert()
    {
        return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'service_id');
    }

    /**
     * 订单详情
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function detail()
    {
        return $this->hasOne('App\Model\WxOrderDetail', 'order_id', 'order_id');
    }

    /**
     * 我的订单
     */
    public function myOrders($type, $status = 0)
    {
        switch ($type){
            case 1:
                $query = $this->with(['course' => function($q){
                    $q->with(['expert'=>function($qq){
                        $qq->select('expert_id', 'name');
                    }])->select('course_id', 'title', 'photo', 'price', 'order_num', 'custom_order_num', 'expert_id');
                }]);
                break;

            case 2:
                $query = $this->with(['expert'=>function($qq){
                    $qq->select('expert_id', 'name', 'photo', 'price', 'skill', 'tags');
                }]);
                break;

            default:
                return [];
        }
        if($status){
            $query->where('status', $status);
        }
        return $query->where([
            'member_id' => get_member_id(),
            'type'      => $type,
            'source'    => 1,
            'is_del'    => 0
        ])->select('order_id', 'service_id', 'status', 'money','type')->get();

    }

    /**
     * 是否已订购
     * @param $service_id
     * @param $type
     * @param $member_id
     * @return bool
     */
    public function hasOrder($service_id, $type, $member_id)
    {
        if(in_array($type, [1, 2, 3]) && $this->where([
                'member_id'  => $member_id,
                'service_id' => $service_id,
                'type'       => $type,
                'status'     => 2
            ])->first()){
            return true;
        }
        return false;
    }

    /**
     * 订单成功
     */
    public function success($notify_values, $is_notify=true)
    {
        if($is_notify){
            $where = [
                'order_no'   => $notify_values['out_trade_no'],
                'status' => 1
            ];
        }else{
            $where = [
                'order_no'   => $notify_values->order_no,
                'status' => 1
            ];
        }
        $order = $this->where($where)->first();

        if(!$order){
            throw new \Exception('订单信息错误-'.$where['out_trade_no']);
        }

        Log::info(__FUNCTION__, collect($order)->toArray());
        switch ($order->type){
            case 1:
                // 课程
                $order->course->increment('order_num');
                break;

            case 2:
                // 专家
                $order->expert->increment('order_num');
                break;

            case 3:
                // 周计划
                WxWeekPlanJoin::where([
                    'plan_id'   => $order->service_id,
                    'member_id' => $order->member_id,
                    'type'      => 1
                ])->update(['status' => 2]);
                break;
        }

        $order->detail->save(['notify_param' => $notify_values]);
        $order->status = 2;
        $order->paid_time = Carbon::now();
        $order->save();

        // 微信订单进行消息通知
        if($order->pay_type == 1){
            dispatch(new SendWxMsg([
                'type' => 1,
                'model'=> $order
            ]));
        }

        return true;
    }

    /**
     * 订单取消
     */
    public function cancel($order_id)
    {
        $order = $this->where([
            'order_id' => $order_id,
            'status'   => 1,
            'is_del'   => 0,
            'member_id'=> get_member_id()
        ])->first();
        if(!$order){
            throw new \Exception('订单信息错误');
        }
        $order->is_del = 1;
        $order->save();

        // 返还优惠券和余额
        if($order->balance_payment){
            $order->member->increment('balance', $order->balance_payment);
        }
        if($order->ocd_id){
            (new WxCouponDetail())->where('ocd_id', $order->ocd_id)->update(['status' => 2]);
        }

    }
}