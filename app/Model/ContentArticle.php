<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 13:46
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class ContentArticle extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'content_article';

    protected $primaryKey = 'article_id';

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
     * 标签获取器
     * @param $value
     * @return array
     */
    public function getTagsAttribute($value)
    {
        return empty($value)?array():array_filter(explode(',', trim($value,',')));
    }

    /**
     * 关联详情
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function detail()
    {
        return $this->hasOne('App\Model\ContentArticleDetail', 'article_id', 'article_id');
    }

    /**
     * 搜索
     * @param $keywords
     * @return mixed
     */
    public function search($keywords)
    {
        $where = [
            'status' => 1
        ];
        return $this->where($where)
            ->where(function ($query) use ($keywords){
                if(!empty($keywords)){
                    $query->where('tags', 'regexp', implode('|', (array)$keywords))
                        ->orwhere('title', 'regexp', implode('|', (array)$keywords));
                }

            })->orderBy('article_id', 'desc')
            ->select('article_id', 'title', 'photo', 'summary', 'tags', 'look_num', 'custom_look_num', 'created_time')
            ->get();
    }

    /**
     * 课程详情
     * @param $id
     */
    public function articleDetail($id)
    {
        $where = [
            'article_id'   => $id,
            'status'       => 1
        ];
        $article = $this->with(['detail' => function($query){
            $query->select('content', 'article_id');
        }])->where($where)
            ->select('article_id', 'title', 'author', 'source_type', 'tags')
            ->first();

        if($article){
            // 是否收藏
            $article->is_collect = (new WxCollection())->is_collection(2, $id);
        }

        return $article;
    }

    /**
     * 浏览人数获取器
     * @param $value
     * @return string
     */
    public function getLookNumAttribute($value)
    {
        $custom_look_num = empty($this->custom_look_num)? 0: $this->custom_look_num;
        return $value + $custom_look_num;
    }

}