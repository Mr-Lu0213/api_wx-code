<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxFeedback extends Model
{
    protected $table = 'wx_feedback';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'member_id');
    }

    /**
     * 回复记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function replys()
    {
        return $this->hasMany('App\Model\WxFeedbackReply', 'feed_id', 'id')
                ->select('id', 'feed_id', 'type', 'content', 'status');
    }

    /**
     * 我的反馈
     */
    public function myList()
    {
        $list = $this->where([
            'member_id' => get_member_id(),
            'status'    => 1
        ])->orderBy('id', 'desc')
            ->select('id as feed_id', 'created_time', 'content', 'member_unread')->get();

        return $list;
    }

    public function detail($feed_id)
    {
        $feedback = $this->with('replys')->find($feed_id);
        foreach ($feedback->replys as $v){
            $v->read();
        }
        $feedback->member_unread = 0;
        $feedback->save();
        return $feedback->replys;
    }

    /**
     * 是否有未读消息
     */
    public function unread()
    {
        if(!empty($this->where([
            'member_id'     => get_member_id(),
            'member_unread' => 1
        ])->first())){
            return true;
        }else{
            return false;
        }
    }

}