<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxWeekPlanPoints extends Model
{
    protected $table = 'wx_week_plan_points';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'created_time';

    protected $guarded = [];

    /**
     * 计划内容item
     * @return mixed
     */
    public function item()
    {
        return $this->hasOne('App\Model\WxWeekPlanItem', 'item_id', 'item_id')
            ->select('week_num', 'points')->orderBy('week_num', 'asc');
    }

}