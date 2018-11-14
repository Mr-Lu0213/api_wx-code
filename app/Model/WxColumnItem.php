<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxColumnItem extends Model
{
    protected $table = 'wx_column_item';

    /**
     * 内容详情
     */
    public function content()
    {
        switch ($this->content_type){
            case 1:
                return $this->hasOne('App\Model\ContentCourse', 'course_id', 'content_id')
                    ->select('course_id', 'title', 'photo', 'price', 'order_num', 'custom_order_num');
            case 2:
                return $this->hasOne('App\Model\ContentExpert', 'expert_id', 'content_id')
                    ->select('expert_id', 'name', 'photo', 'banner', 'price', 'tags', 'skill');
        }
    }


    /**
     * 栏目所有内容列表
     * @param $column_id
     */
    public function getColumnItems($column_id)
    {
        $list = $this->where([
            'column_id' => $column_id
        ])->orderBy('seq', 'asc')->orderby('id', 'desc')
            ->select('column_id', 'content_type', 'content_id')->get();
//        foreach ($list as $v){
//            $v->content;
//        }
        foreach ($list as $key => $value) {
            if(empty($v->content)){
                unset($list[$key]);
            }
        }
        return $list;
    }


}