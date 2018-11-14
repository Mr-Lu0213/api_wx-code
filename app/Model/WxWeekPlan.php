<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class WxWeekPlan extends Model
{
    protected $table = 'wx_week_plan';

    protected $primaryKey = 'plan_id';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

    /**
     * 当前进行或即将开展的计划
     */
    public function getCurrrentPlan()
    {
        $fields = ['plan_id', 'title', 'periods', 'qrcode', 'qrcode_title', 'price', 'origin_price','invites_num','start_date', 'end_date' ,'content'];
        $date = Carbon::today();
        $plan = $this->with('items')
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->select($fields)->first();
        if($plan){
            $plan->status = 1;  // 进行中
        }else if($plan = $this->with('items')
            ->where('start_date', '>', $date)
            ->select($fields)->first()){
            $plan->status = 0;  // 未开始
        }

        return $plan;
    }

    /**
     * 计划内容
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany('App\Model\WxWeekPlanItem', 'plan_id', 'plan_id')
            ->orderBy('week_num', 'asc');
    }

    /**
     * 价格获取器
     * @param $value
     * @return string
     */
    public function getPriceAttribute($value)
    {
        return sprintf("%0.2f", $value / 100);
    }

    /**
     * 价格获取器
     * @param $value
     * @return string
     */
    public function getOriginPriceAttribute($value)
    {
        return sprintf("%0.2f", $value / 100);
    }

    /**
     * 图片获取器
     * @param $value
     * @return string
     */
    public function getQrcodeAttribute($value)
    {
        return empty($value)? '': Config::get('api.aliyun_oss.res_url').$value;
    }

    /**
     * 期数
     * @param $value
     * @return string
     */
    public function getPeriodsAttribute($value)
    {
        return sprintf('%03d', $value);
    }

    /**
     * 总结内容
     * @param $value
     * @return string
     */
    public function getContentAttribute($value)
    {
        return htmlspecialchars_decode($value);
    }

    /**
     * 用户参与状态
     */
    public function join_status()
    {
        $member_id = get_member_id();
        $join = WxWeekPlanJoin::where([
            'plan_id' => $this->plan_id,
            'member_id' => $member_id
        ])->select('type', 'status')->first();
        if(empty($join)){
            $this->join_type = 0;
            $this->join_status = 0;
        }else{
            $this->join_type = $join->type;
            $this->join_status = $join->status;
        }
    }

}