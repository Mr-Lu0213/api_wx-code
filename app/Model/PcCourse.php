<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 13:46
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;


class PcCourse extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'pc_course';

    public $guarded = [];

    /**
     * 关联课程
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function course()
    {
        return $this->hasOne('App\Model\ContentCourse', 'course_id', 'course_id');
    }

    /**
     * 专家展示列表
     * @param $type
     * @return mixed
     */
    public function Courses()
    {
        $courses = $this->with([
            'course' => function($query){
                $query->select('course_id', 'title','order_num','custom_order_num','expert_id')->with([
                    'expert' => function($query){
                        $query->select('expert_id','experience');

                    }]);
            }])
            ->take(8)->get();
        $courses = collect($courses)->lists('course');
        foreach($courses as $key=>$val){
            unset($courses[$key]['expert_id']);
        }
        return $courses;
    }

}