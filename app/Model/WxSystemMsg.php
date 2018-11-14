<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxSystemMsg extends Model
{
    protected $table = 'wx_system_msg';

    /**
     * 客服消息
     */
    public function myMsg()
    {
        $member = get_member();
        $list = $this->where([
            'status' => 1
        ])->where('created_time', '>=', $member->created_time)
            ->orderBy('id', 'desc')->get();

        // 标记已读进度
        if(!empty($list[0]) && $list[0]->id > $member->ext->sys_msg_id){
            $member->ext->sys_msg_id = $list[0]->id;
            $member->ext->save();
        }
        return $list;
    }

    /**
     * 是否有未读消息
     */
    public function unreadMsg()
    {
        $member     = get_member();
        $member_ext = WxMemberExt::firstOrCreate(['member_id' => $member->member_id]);
        $msg_id = $this->where('created_time', '>=', $member->created_time)->max('id');
        if($msg_id > $member_ext->sys_msg_id){
            return true;
        }else{
            return false;
        }
    }

}