<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 17:50
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class BasicTags extends Model
{
    protected $table = 'basic_tags';

    protected $primaryKey = 'tag_id';

    /**
     * 首页推荐标签
     * @param $member_id
     * @return mixed
     */
    public function homeList($member_id)
    {
        $tags = WxMemberTag::where('member_id', $member_id)->lists('tag_id')->toArray();
        $query = $this->where(['parent_id'=>0]);
        if(!empty($tags)){
            $query->whereIn('tag_id', $tags);
        }else{
            $query->where('recommend', 1);
        }
        $tags = $query->orderBy('recommend', 'desc')->lists('name');
        return $tags;
    }

    /**
     * 子标签
     * @param $tag_id
     * @return mixed
     */
    public function childrenList()
    {
        return $this->where(['parent_id'=>$this->tag_id, 'level'=>1])->lists('name')->toArray();
    }

    /**
     * 热门搜索
     * @return mixed
     */
    public function hotList()
    {
        return $this->where(['parent_id'=>0, 'recommend'=>1])->lists('name');
    }
}