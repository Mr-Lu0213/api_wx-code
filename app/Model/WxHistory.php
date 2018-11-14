<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxHistory extends Model
{
    protected $table = 'wx_history';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'created_time';

    protected $guarded = [];

    public function getMoreAttibute($value)
    {
        return json_decode($value, true);
    }

    /**
     * 用户
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function member()
    {
        return $this->hasOne('App\Model\WxMember', 'member_id', 'member_id');
    }

    /**
     * 关联内容
     */
    public function target()
    {
        switch ($this->type){
            case 1:
                return $this->hasOne('App\Model\ContentCourse', 'course_id', 'target_id')
                    ->select('course_id', 'title');

            case 2:
                return $this->hasOne('App\Model\ContentArticle', 'article_id', 'target_id')
                    ->select('article_id', 'title');

            case 3:
                return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'target_id')
                    ->select('expert_id', 'name');

            case 4:
                return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'target_id')
                    ->select('expert_id', 'name');
            default:
                return [];
        }
    }

    /**
     * 创建时间
     * @param $value
     * @return false|string
     */
    public function getCreatedTimeAttribute($value)
    {
        return history_time_format($value);
    }

    /**
     * 我的历史
     */
    public function myHistory($skip, $size)
    {
        $list = $this->where([
            'member_id' => get_member_id()
        ])->orderBy('id', 'desc')
            ->skip($skip)->take($size)->get();
        foreach ($list as $k=>$v){
            $list[$k]->target;
        }
        return $list;
    }


}