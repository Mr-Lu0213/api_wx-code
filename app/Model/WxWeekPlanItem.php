<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxWeekPlanItem extends Model
{
    protected $table = 'wx_week_plan_item';

    protected $primaryKey = 'item_id';

    protected $guarded = [];

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    /**
     * 根据类型关联内容
     * @return mixed
     */
    public function content()
    {
        switch ($this->content_type){
            case 2:
                return $this->hasOne('App\Model\WxWeekItemContent', 'item_id', 'item_id');

            default:
                return [];
        }
    }

}