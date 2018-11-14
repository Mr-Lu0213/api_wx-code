<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxWeekPlanJoin extends Model
{
    protected $table = 'wx_week_plan_join';

    protected $primaryKey = 'join_id';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

    /**
     * 计划
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function plan()
    {
        return $this->hasOne('App\Model\WxWeekPlan', 'plan_id', 'plan_id');
    }

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'member_id')
            ->select('member_id', 'phone', 'photo');
    }

    /**
     * 得分记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function points()
    {
        return $this->hasMany('App\Model\WxWeekPlanPoints', 'join_id', 'join_id');
    }

}