<?php
/**
 * Created by qizhao
 * Date: 2018/7/4
 * Time: 16:59
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class ContentExpertApply extends Model
{
    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'content_expert_apply';

    public $guarded = [];

    /**
     * 图片获取器
     * @param $value
     * @return string
     */
    public function getPhotoAttribute($value)
    {
        return empty($value)? '': Config::get('api.aliyun_oss.res_url').$value;
    }

    /**
     * 检测申请记录
     * @return bool
     */
    public function applyValid()
    {
        if($this->where('member_id', get_member_id())
            ->where('status', '<', 3)->first()){
            return false;
        }else{
            return true;
        }
    }


}