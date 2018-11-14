<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 18:05
 */

namespace App\Http\Controllers;


use App\Model\ContentCourse;
use App\Model\ContentExpert;
use App\Model\WxCoupon;
use App\Model\WxCouponDetail;
use App\Model\WxOrder;
use App\Model\WxWeekPlan;
use Carbon\Carbon;
use extend\Payment\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * 创建
     */
    public function postCreate()
    {
        $service_id = $this->request->input('service_id');
        $type       = $this->request->input('order_type');

        // 可用优惠券
        $result['coupon_list'] = (new WxCouponDetail())->orderCouponlist($type, $service_id);
        $result['coupon_num']  = count($result['coupon_list']);

        // 可用余额
        $result['balance'] = $this->member->balance;

        return response_success($result);

    }

    /**
     * 提交支付
     */
    public function postCharge()
    {
        $service_id      = $this->request->input('service_id');
        $order_type      = $this->request->input('order_type');
        $pay_type        = $this->request->input('pay_type', 1);    //支付方式 1微信
        $ocd_id          = $this->request->input('ocd_id', 0);
        $balance_payment = $this->request->input('balance_payment', 0);
        $order           = new WxOrder();

        if(!$this->member->phone){
            return response_data(-1, '请先绑定手机号');
        }
        if($order->hasOrder($service_id, $order_type, $this->member_id)){
            return response_error('请勿重复购买');
        }

        DB::beginTransaction();
        try{
            switch ($order_type){   // 1课程 2专家 3周计划
                case 1:
                    $service = ContentCourse::find($service_id);
                    break;

                case 2:
                    $service = ContentExpert::find($service_id);
                    break;

                case 3:
                    $service = WxWeekPlan::find($service_id);
                    break;

                default:
                    return response_error('订单类型错误');
            }

            $original_money = $service->price;
            $coupon_money   = (new WxCouponDetail())->canUse($ocd_id, $original_money);
            $money          = $original_money;
            if($coupon_money < $original_money){
                $money = $original_money - $coupon_money;
            }else{
                $money = 0;
            }
            if($balance_payment < $money){
                $money = $money - $balance_payment;
            }else{
                $balance_payment = $money;
                $money = 0;
            }

            // 扣除余额
            if($balance_payment){
                $this->member->decrement('balance', $balance_payment * 100);
            }

            // 生成订单
            $order->type            = $order_type;
            $order->pay_type        = $pay_type;
            $order->service_id      = $service_id;
            $order->member_id       = $this->member_id;
            $order->ocd_id          = $ocd_id;
            $order->balance_payment = $balance_payment;
            $order->money           = $money;
            $order->original_money  = $original_money;
            $order->order_no        = create_orderno();
            $order->save();

            if($order->money == 0){
                $order->detail()->create(['pay_param' => '']);
                $order->success($order, false);
                DB::commit();
                return response_data(2, '购买成功');
            }

            // 调用支付
            $payment = Payment::getInstance($pay_type);
            $result['pay_param'] = $payment->charge(collect($order)->toArray(), $this->member);
            $order->detail->create(['pay_param' => $result['pay_param']]);

            DB::commit();
            return response_data(1, '下单成功', $result);

        }catch (\Exception $e){
            Log::error(__CLASS__.' '.__FUNCTION__, [$e->getMessage()]);
            DB::commit();
            return response_error('提交失败');
        }
    }

    /**
     * 继续支付
     */
    public function postPay()
    {
        $order_id = $this->request->input('order_id');
        $pay_type = $this->request->input('pay_type', 1);

        $order = (new WxOrder())->find($order_id);
        if((new WxOrder())->hasOrder($order->service_id, $order->type, $this->member_id)){
            return response_error('请勿重复购买');
        }

        DB::beginTransaction();
        try{
            // 调用支付
            $payment = Payment::getInstance($pay_type);
            $result['pay_param'] = $payment->charge(collect($order)->toArray(), $this->member);
            $order->detail->save(['pay_param' => $result['pay_param']]);

            if($pay_type !== $order->type){
                $order->type = $pay_type;
                $order->save();
            }

            DB::commit();
            return response_data(1, '下单成功', $result);
        }catch (\Exception $e){
            Log::error(__CLASS__.' '.__FUNCTION__, [$e->getMessage()]);
            DB::rollback();
            return response_error();
        }

    }

    /**
     * 取消
     */
    public function postCancel()
    {
        DB::beginTransaction();
        try{
            $order_id = $this->request->input('order_id');
            (new WxOrder())->cancel($order_id);

            DB::commit();
            return response_success();
        }catch (\Exception $e){
            Log::error(__CLASS__.' '.__FUNCTION__, [$e->getMessage()]);
            DB::rollback();
            return response_error();
        }

    }

}