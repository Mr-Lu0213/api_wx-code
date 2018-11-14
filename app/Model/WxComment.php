<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxComment extends Model
{
    protected $table = 'wx_comment';

    protected $primaryKey = 'comment_id';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $fillable = ['member_id', 'content_type', 'content_id', 'content', 'source'];

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'member_id');
    }

    /**
     * 评论时间
     * @param $value
     */
    public function getCreatedTimeAttribute($value)
    {
        return comment_time_format($value);
    }

    /**
     * 关联内容
     */
    public function target()
    {
        switch ($this->content_type){
            case 1:
                return $this->hasOne('App\Model\ContentCourse', 'course_id', 'content_id')
                    ->with(['expert'=>function($query){
                        $query->select('expert_id', 'name');
                    }])->select('course_id', 'title', 'photo', 'price', 'expert_id');

            case 2:
                return $this->hasOne('App\Model\ContentArticle', 'article_id', 'content_id')
                    ->select('article_id', 'title', 'photo', 'tags');

            case 3:
                return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'content_id')
                    ->select('expert_id', 'name', 'price', 'photo', 'tags', 'skill');

        }
    }

    /**
     * 我的评论
     */
    public function myComments()
    {
        $list = $this->where([
            'member_id' => get_member_id(),
            'status'    => 1
        ])->select('comment_id', 'content_type', 'content_id', 'content', 'created_time')
            ->orderby('comment_id', 'desc')->get();
        foreach ($list as $k=>$v){
            $list[$k]->target;
        }
        return $list;
    }

}