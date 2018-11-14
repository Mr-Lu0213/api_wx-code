<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 13:46
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class PcExpert extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'pc_expert';

    public $guarded = [];

    /**
     * 头像获取器
     * @param $value
     * @return string
     */
    public function getPhotoAttribute($value)
    {
        return empty($value) ? '' : Config::get('api.aliyun_oss.res_url').$value;
    }


    /**
     * 专家列表
     * @param $type
     * @return mixed
     */
    public function expert()
    {
        return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'expert_id');
    }

    public function Experts()
    {
        $experts = $this->with(['expert' => function ($query) {
            $query->select('expert_id', 'result', 'order_num', 'custom_order_num', 'photo', 'skill', 'experience');
        }])
            ->take(4)
            ->get();

        return collect($experts)->lists('expert');
    }


}