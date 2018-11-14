<?php

namespace App\Http\Controllers;

use App\Model\WxFeedback;

class FeedbackController extends Controller
{
    /**
     * 列表
     */
    public function postIndex()
    {

        $result['feedback_list'] = (new WxFeedback())->myList();   // 我的反馈

        return response_success($result);
    }

    /**
     * 提交反馈
     */
    public function postCreate()
    {
        $content = $this->request->input('content');
        if(!$content){
            return response_error('反馈内容不能为空');
        }
        WxFeedback::create([
            'member_id' => $this->member_id,
            'content'   => $content,
            'sys_unread'=> 1
        ])->replys()->create([
            'type'  => 1,
            'content' => $content
        ]);
        return response_success();
    }

    /**
     * 反馈详情
     */
    public function postDetail()
    {
        $feed_id = $this->request->input('feed_id');

        $result['reply_list'] = (new WxFeedback())->detail($feed_id);

        return response_success($result);

    }


}
