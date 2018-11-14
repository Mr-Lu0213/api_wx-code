<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

class ContentExpert extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'content_expert';

    protected $primaryKey = 'expert_id';

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
     * 展示图获取器
     * @param $value
     * @return string
     */
    public function getBannerAttribute($value)
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
     * 订购人数获取器
     * @param $value
     * @return array
     */
    public function getOrderNumAttribute($value)
    {
        return $value + $this->custom_order_num;
    }

    /**
     * 首页列表
     * @param $take
     * @return mixed
     */
    public function homeList($take)
    {
        $where = [
            'operate_type' => 2,
            'status' => 3
        ];
        return $this->where($where)
            ->orderBy('recommend', 'desc')
            ->orderBy('updated_time', 'desc')
            ->take($take)
            ->select('expert_id', 'photo', 'name', 'banner', 'skill', 'price', 'tags')
            ->get()->toArray();

    }

    /**
     * 搜索
     * @param $keywords
     * @return mixed
     */
    public function search($keywords)
    {
        $where = [
            'operate_type' => 2,
            'status' => 3
        ];
        return $this->where($where)
            ->where(function ($query) use($keywords){
                if(!empty($keywords)){
                    $query->where('tags', 'regexp', implode('|', (array)$keywords))
                        ->orwhere('name', 'regexp', implode('|', (array)$keywords));
                }

            })->orderBy('recommend', 'desc')->orderBy('expert_id', 'desc')
            ->select('expert_id', 'name', 'photo', 'banner', 'skill', 'price')
            ->get();
    }

    /**
     * 专家课程
     * @return
     */
    public function Courses()
    {
        return $this->hasMany('App\Model\ContentCourse', 'expert_id', 'expert_id')
            ->with(['maters' => function($query){
                $query->select('ma_id', 'course_id', 'trial');
            }])->where([
            'status'       => 1,
            'operate_type' => 2
        ])->select('course_id', 'title', 'photo', 'charge', 'updated_time', 'course_time')->get();
    }

    /**
     * 详情
     * @param $expert_id
     * @return mixed
     */
    public function detail($expert_id)
    {
        $where = [
            'expert_id'    => $expert_id,
            'status'       => 3,
            'operate_type' => 2
        ];
        $expert = $this->where($where)
            ->select('expert_id', 'photo', 'banner', 'name', 'price', 'skill', 'result', 'tags', 'order_num', 'custom_order_num', 'experience')
            ->first();
        if($expert){
            // 专家课单
            $expert->course_list = $expert->Courses();

            // 订阅用户
            $order_list = (new WxOrder())->with(['member'=>function($query){
                $query->select('member_id', 'nick_name', 'photo');
            }])->where([
                'type'       => 2,
                'service_id' => $expert_id,
                'status'     => 2
            ])->orderBy('order_id', 'desc')->take(20)
                ->select('order_id', 'member_id')->get();
            $expert->order_member = $order_list->lists('member');

            // 是否收藏
            $expert->is_collect = (new WxCollection())->is_collection(3, $expert_id);

            // 是否订阅
            $expert->is_order = in_array(get_member_id(), collect($expert->order_member)->lists('member_id')->toArray());

        }
        return $expert;

    }

}