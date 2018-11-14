<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 13:46
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class ContentCourse extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'content_course';

    protected $primaryKey = 'course_id';


    /**
     * 订购人数获取器
     * @param $value
     * @return string
     */
    public function getOrderNumAttribute($value)
    {
        $custom_order_num = empty($this->custom_order_num)? 0: $this->custom_order_num;
        return $value + $custom_order_num;
    }

    /**
     * 时长获取器
     * @param $value
     * @return string
     */
    public function getCourseTimeAttribute($value)
    {
        if($value >= 3600){
            $hour = floor($value/3600);
            return $hour.'小时'.date('i分钟s秒', $value%3600);
        }else{
            return date('i分钟s秒', $value);
        }
    }

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
     * 价格获取器
     * @param $value
     * @return string
     */
    public function getPriceAttribute($value)
    {
        return sprintf("%0.2f", $value / 100);
    }

    /**
     * 标签获取器
     * @param $value
     * @return array
     */
    public function getTagsAttribute($value)
    {
        return empty($value)?array():array_filter(explode(',', trim($value,',')));
    }

    /**
     * 关联专家
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function expert()
    {
        return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'expert_id');
    }

    /**
     * 详情
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function detail()
    {
        return $this->hasOne('App\Model\ContentCourseDetail', 'course_id', 'course_id');
    }

    /**
     * 课程章节
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function maters()
    {
        return $this->hasMany('App\Model\ContentCourseMaterial', 'course_id', 'course_id');
    }

    /**
     * 首页课程列表
     * @param $take
     * @param int $type
     * @return mixed
     */
    public function homeList($take, $type=1)
    {
        $where = [
            'type'         => $type,
            'status'       => 1,
            'operate_type' => 1
        ];
        $list = $this->where($where)
            ->orderBy('recommend', 'desc')
            ->orderBy('updated_time', 'desc')
            ->take($take)
            ->select('course_id', 'title', 'photo', 'price', 'charge')
            ->get()->toArray();

        $wxorder = new WxOrder();
        $mater   = new ContentCourseMaterial();
        foreach ($list as $k=>$v){
            if($v['charge'] == 0 || $wxorder->is_order(1, $v['course_id'])){
                // 免费或已订购
                $list[$k]['play'] = 1;
            }else if($mat = $mater->where(['course_id' => $v['course_id']])->first()){
                // 能否试看
                $list[$k]['play'] = $mat->trial;
            }else{
                $list[$k]['play'] = 0;
            }
        }
        return $list;
    }

    /**
     * 搜索
     * @param $keywords
     * @return mixed
     */
    public function search($keywords)
    {
        $where = [
            'operate_type' => 1,
            'status' => 1
        ];
        return $this->with(['expert'=>function($query){
            $query->select('expert_id', 'name', 'tags');
        }])->where($where)
            ->where(function ($query) use ($keywords){
                if(!empty($keywords)){
                    $query->where('tags', 'regexp', implode('|', (array)$keywords))
                        ->orwhere('title', 'regexp', implode('|', (array)$keywords))
                        ->orwhere('expert_name', 'regexp', implode('|', (array)$keywords));
                }

            })->orderBy('recommend', 'desc')->orderBy('course_id', 'desc')
            ->select('course_id', 'title', 'photo', 'summary', 'price', 'charge', 'order_num', 'custom_order_num', 'expert_id')
            ->get();
    }

    /**
     * 课程详情
     * @param $id
     */
    public function courseDetail($id)
    {
        $where = [
            'course_id'    => $id,
            'status'       => 1,
            'operate_type' => 1
        ];
        $course = $this->with(['detail' => function($query){
            $query->select('content', 'results', 'course_id');
        }, 'maters' => function($query){
            $query->select('ma_id', 'course_id', 'ma_title', 'ma_time', 'updated_time', 'trial');
        }, 'expert' =>function($query){
            $query->select('expert_id', 'photo', 'skill', 'name');
        }])->where($where)
            ->select('course_id', 'title', 'expert_id', 'photo', 'price', 'charge', 'tags')
            ->first();

        if($course){
            // 是否收藏
            $course->is_collect = (new WxCollection())->is_collection(1, $id);

            // 是否订阅
            $course->is_order = (new WxOrder())->is_order(1, $id);
        }

        return $course;
    }

}