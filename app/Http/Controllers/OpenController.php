<?php

namespace App\Http\Controllers;

use App\Jobs\MemberLogin;
use App\Jobs\MemberReg;
use App\Model\WxMember;
use App\Model\WxOrder;
use extend\Payment\Payment;
use extend\Wchat\Wchat;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;


class OpenController extends BaseController
{

    protected $wchat;

    public function creatWchat()
    {
        $this->wchat = create_wchat();
    }

    /**
     * 微信鉴权
     * @param Request $request
     */
    public function oauth(Request $request)
    {
        if(empty($request->has('code'))){
            // code不存在 跳转获取
            $this->creatWchat();
            $redirect_url = route('oauth');  // 鉴权回调地址
            $referer_url  = $request->input('referer_url', '');  // 来源地址 鉴权成功后跳转
            $url          = $this->wchat->get_authorize_url_userinfo($redirect_url, base64_encode($referer_url));
            header("Location:".$url);exit;

        }else{
            try{
                $this->creatWchat();
                $code         = $request->input('code');
                $referer_url  = base64_decode(urldecode($request->input('state')));  // 来源地址 鉴权成功后跳转
                $data         = json_decode($this->wchat->get_access_token($code));
                Log::info(__FUNCTION__, [$data]);

                // 查询用户是否存在
                if(!$user = WxMember::where(['source'=>1, 'open_id'=>$data->openid])->first()){
                    // 获取用户信息
                    $values = [
                        'access_token'=> $data->access_token,
                        'openid'      => $data->openid,
                        'lang'        => 'zh_CN'
                    ];
                    $user_info        = json_decode($this->wchat->get_user_info($values));
                    Log::info(__FUNCTION__, [$user_info]);

                    $user             = new WxMember();
                    $user->sex        = $user_info->sex;
                    $user->open_id    = $user_info->openid;
                    $user->nick_name  = $user_info->nickname;
                    $user->ip         = $request->getClientIp();
                    $user->source     = 1;
                    $user->save();

                    // 注册成功 - 执行相关操作
                    dispatch(new MemberReg(['member'=>$user, 'referer_url' => $referer_url, 'headimgurl' => $user_info->headimgurl]));

                }

                $token = create_token($user->member_id);
                $url_param = explode('?', $referer_url);
                if(!empty($url_param[0])){
                    $referer_url  = $url_param[0];
                }else{
                    $referer_url  = Config::get('api.h5_uri');
                }
                $referer_url  = $referer_url.'?'.http_build_query(['token'=>$token]);
                Log::info('redirect - '.$referer_url);

                // 登录成功 - 执行相关操作
                dispatch(new MemberLogin(['member'=>$user]));

            }catch (\Exception $e){
                Log::error(__FUNCTION__.' '.$e->getMessage());
                $referer_url = Config::get('api.h5_uri');
            }
            // 跳转
            header("Location:".$referer_url);exit;

        }

    }

    /**
     * 微信支付回调
     */
    public function notifyWx()
    {
        DB::beginTransaction();
        $payment = Payment::getInstance(1);
        try {
            $xml = file_get_contents("php://input");

            //如果返回成功则验证签名
            if (! $xml) {
                $payment->notify_result();
            }
            Log::info("微信支付结果:".$xml);

            // 将XML转为array
            libxml_disable_entity_loader(true);
            $notify_values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

            if($notify_values['return_code'] != 'SUCCESS') {
                $payment->notify_result();
            }

            //验证签名
            $payment->setValues($notify_values);
            $sign = $payment->MakeSign();
            if($sign != $notify_values['sign']) {
                Log::error("签名不匹配".[$sign, $notify_values['sign']]);
                $payment->notify_result();
            }

            // 同步订单状态
            (new WxOrder())->success($notify_values);

            DB::commit();
            return $payment->notify_result(true);

        } catch (\Exception $e){
            DB::rollBack();
            Log::error(__FUNCTION__.' '.$e->getMessage());
            return $payment->notify_result();
        }
    }


}
