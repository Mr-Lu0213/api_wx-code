<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxCollection extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'wx_collection';

    protected $guarded = [];


    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'member_id');
    }

    /**
     * 是否收藏
     * @param $content_type
     * @param $content_id
     * @param int $member_id
     * @return bool
     */
    public function is_collection($content_type, $content_id, $member_id=0)
    {
        if(!$member_id){
            $member_id = get_member_id();
        }
        return (boolean)$this->where([
            'content_type' => $content_type,
            'content_id'  => $content_id,
            'member_id'   => $member_id,
        ])->first();
    }

    /**
     * 关联课程
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function course()
    {
        return $this->hasOne('App\Model\ContentCourse', 'course_id', 'content_id');
    }

    /**
     * 关联专家
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function expert()
    {
        return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'content_id');
    }

    /**
     * 关联资讯
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function article()
    {
        return $this->hasOne('App\Model\ContentArticle', 'article_id', 'content_id');
    }

    /**
     * 我的收藏
     * @param $type
     */
    public function myCollection($content_type)
    {
        switch ($content_type){
            case 1:
                $query = $this->with(['course' => function($q){
                    $q->with(['expert'=>function($qq){
                        $qq->select('expert_id', 'name');
                    }])->select('course_id', 'title', 'photo', 'price', 'order_num', 'custom_order_num', 'expert_id');
                }]);
                break;

            case 2:
                $query = $this->with(['article'=>function($qq){
                    $qq->select('article_id', 'title', 'photo');
                }]);
                break;

            case 3:
                $query = $this->with(['expert'=>function($qq){
                    $qq->select('expert_id', 'name', 'photo', 'price', 'skill', 'tags');
                }]);
                break;

            default:
                return [];
        }

        return $query->where([
            'member_id'       => get_member_id(),
            'content_type'    => $content_type
        ])->select('content_id', 'content_type')->get();
    }


}