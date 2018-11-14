<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxColumn extends Model
{
    protected $table = 'wx_column';

    protected $primaryKey = 'column_id';

    /**
     * 栏目内容
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function items()
    {
        return $this->hasMany('App\Model\WxColumnItem', 'column_id', 'column_id');
    }


    /**
     * 栏目列表
     */
    public function getList()
    {
        $list = $this->with(['items' => function($query){
            $query->orderBy('seq', 'asc')->orderBy('id', 'desc')
                ->select('content_type', 'content_id', 'column_id');
        }])->where([
            'status' => 1
        ])->orderBy('seq', 'asc')->orderBy('column_id', 'desc')
            ->select('column_id', 'title', 'type')->get();

        foreach ($list as $k=>$v){
            if($v->items){
                foreach ($v->items as $vo){
                    $vo->content;
                }
            }
        }

        $list = collect($list)->toArray();
        foreach ($list as $k=>$v){
            if($v['items'] && count($v['items']) > 3){
                $list[$k]['items'] = array_slice($v['items'], 0, 3);
            }
        }
        return $list;
    }

}