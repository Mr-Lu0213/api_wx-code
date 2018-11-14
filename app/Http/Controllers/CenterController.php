<?php
/**
 * 个人中心
 * Created by qizhao
 * Date: 2018/6/26
 * Time: 16:02
 */

namespace App\Http\Controllers;


use App\Model\ContentExpertApply;
use App\Model\WxCollection;
use App\Model\WxComment;
use App\Model\WxCouponDetail;
use App\Model\WxFeedback;
use App\Model\WxHistory;
use App\Model\WxMember;
use App\Model\WxOrder;
use App\Model\WxSystemMsg;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class CenterController extends Controller
{
    /**
     * 用户基本信息
     */
    public function postInfo()
    {
        $member = clone $this->member;          // 对原始对象做拷贝再操作
        $member->sign  = empty($member->ext)? "": $member->ext->sign;
        $member->phone = (bool)$member->phone;

        unset($member->open_id, $member->source, $member->ip, $member->created_time, $member->updated_time, $member->ext);
        $result['member'] = $member;

        // 未读系统消息
        $result['unread_msg'] = (new WxSystemMsg())->unreadMsg();

        // 未读反馈消息
        $result['unread_fb'] = (new WxFeedback())->unread();

        return response_success($result);
    }

    /**
     * 我的订单
     */
    public function postMyOrders()
    {
        $type   = $this->request->input('order_type');               // 订单类型 0全部 1课程 2专家
        $status = $this->request->input('order_status', 0);   // 订单状态 0全部 1待支付 2已支付

        $result['order_list'] = (new WxOrder())->myOrders($type, $status);

        return response_success($result);

    }

    /**
     * 我的优惠券
     */
    public function postMyCoupon()
    {
        $result['coupon_list'] = (new WxCouponDetail())->myCoupon();

        return response_success($result);
    }

    /**
     * 我的收藏
     */
    public function postMyCollection()
    {
        $content_type   = $this->request->input('content_type');  // 内容类型 1课程 2资讯 3专家

        $result['collect_list'] = (new WxCollection())->myCollection($content_type);

        return response_success($result);
    }

    /**
     * 我的评论
     */
    public function postMyComment()
    {
        $result['comment_list'] = (new WxComment())->myComments();

        return response_success($result);
    }

    /**
     * 小秘书消息
     */
    public function postSysMsg()
    {
        $result['msg_list'] = (new WxSystemMsg())->myMsg();

        return response_success($result);
    }

    /**
     * 观看历史
     */
    public function postHistory()
    {
        $result['history_list'] = (new WxHistory())->myHistory($this->page_skip, $this->page_size);

        return response_success($result);
    }

    /**
     * 发送验证码
     */
    public function postSendCaptcha()
    {
        $phone        = $this->request->input('phone');
        $captcha_type = $this->request->input('captcha_type');  // 1、绑定手机号
        $captcha      = random_captcha();
        switch ($captcha_type){
            case 1:
                $cache_key = 'reg_captcha_'.$this->member_id.'_'.$captcha;
                break;

            default:
                return response_error('验证码类型错误');
        }
        if(send_msg($captcha, $phone)){
            Cache::put($cache_key, $phone, 15);
            return response_success();
        }else{
            return response_error('发送失败');
        }
    }

    /**
     * 绑定手机号
     */
    public function postBindPhone()
    {
        $captcha      = $this->request->input('captcha');
        $cache_key    = 'reg_captcha_'.$this->member_id.'_'.$captcha;
        $phone        = Cache::get($cache_key);
        if(!$phone){
            return response_error('验证码错误或已过期');
        }

        // 查询用户是否绑定
        if($this->member->phone){
            return response_error('该用户已绑定手机号');
        }else if(!(new WxMember())->checkBindPhone($phone)){
            return response_error('该手机号已被绑定，请更换');
        }

        $this->member->phone = $phone;
        $this->member->save();
        return response_success();

    }

    /**
     * 设置签名
     */
    public function postSign()
    {
        $sign = $this->request->input('sign');
        if(!$sign){
            return response_error('请输入签名内容');
        }
        if($this->member->ext){
            $this->member->ext->update(['sign' => $sign]);
        }else{
            $this->member->ext()->create(['sign' => $sign]);
        }

        return response_success();
    }

    /**
     * 申请成为专家
     */
    public function postExpertApply()
    {
        $apply = new ContentExpertApply();
        if(empty($this->member->phone)){
            return response_data(-1, '请先绑定手机号');
        }else if(!$apply->applyValid()){
            return response_error('请勿重复申请');
        }else if(!$this->request->hasFile('photo')){
            return response_error('请上传头像');
        }
        $data = $this->request->all();
        $validate = Validator::make($data, [
            'name'    => 'required',
            'summary' => 'required',
            'skill'   => 'required',
            'phone'   => 'required',
        ]);
        if($validate->fails()){
            return response_error($validate->errors()->first());
        }else if(!check_phone($data['phone'])){
            return response_error('手机号格式错误');
        }

        // 上传头像
        $destinationPath = upload_file();
        $apply->photo   = $destinationPath[0];
        $apply->name    = $data['name'];
        $apply->summary = $data['summary'];
        $apply->skill   = $data['skill'];
        $apply->phone   = $data['phone'];
        $apply->member_id = $this->member_id;
        $apply->save();

        return response_success();

    }

    /**
     * 我的专家信息
     */
    public function postMyExpert()
    {
        $result['apply_info'] = ContentExpertApply::where('member_id', $this->member_id)
            ->where('status', '<', 3)->first();

        return response_success($result);
    }

}