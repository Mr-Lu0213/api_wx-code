<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 17:50
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class ContentCourseMaterial extends Model
{
    protected $table = 'content_course_material';

    protected $primaryKey = 'ma_id';

    /**
     * 图片获取器
     * @param $value
     * @return string
     */
    public function getMaPhotoAttribute($value)
    {
        return empty($value)? '': Config::get('api.aliyun_oss.res_url').$value;
    }

    /**
     * 时长获取器
     * @param $value
     * @return string
     */
    public function getMaTimeAttribute($value)
    {
        if($value >= 3600){
            $hour = floor($value/3600);
            return $hour.'小时'.date('i分钟s秒', $value%3600);
        }else{
            return date('i分钟s秒', $value);
        }
    }

    /**
     * 播放地址
     * @param $value
     * @return string
     */
    public function getMaUrlAttribute($value)
    {
        return empty($value)? '': Config::get('api.aliyun_oss.res_url').$value;
    }

    /**
     * 关联课程
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function course()
    {
        return $this->hasOne('App\Model\ContentCourse', 'course_id', 'course_id');
    }

    /**
     * 获取播放地址
     * @param $id
     * @return string
     */
    public function getUrl($id)
    {
        $material = $this->find($id);

        // 课程免费或章节试看或已购买
        if($material->course->charge == 0 ||
            $material->trial == 1 ||
            (new WxOrder())->is_order(1, $material->course_id)
        ){
            return $material;
        }else{
            return '';
        }
    }

}