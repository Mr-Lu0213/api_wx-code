<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxWeekPlanInvites extends Model
{
    protected $table = 'wx_week_plan_invites';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'created_time';

    protected $guarded = [];

    /**
     * 受邀人
     * @return mixed
     */
    public function invited()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'invited_id')
            ->select('member_id', 'photo');
    }

}