<?php
/**
 * Created by qizhao
 * Date: 2018/7/2
 * Time: 11:02
 */
namespace extend\Payment;

class Payment
{
    /**
     * Get a payment instance
     *
     * @param $payment_type
     * @return WxPay
     * @throws \Exception
     */
    public static function getInstance($payment_type)
    {
        switch ($payment_type){
            case 1:
                return new WxPay();

            default:
                throw new \Exception('支付类型错误！');
        }
    }
}