<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 13:46
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class PcShow extends Model
{

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $table = 'pc_show';

    public $guarded = [];

    /**
     * 图片获取器
     * @param $value
     * @return string
     */
    public function getStorageAttribute($value)
    {
        return empty($value)? '': Config::get('api.aliyun_oss.res_url').$value;
    }

   
    /**
     * 获取列表
     * @param $type
     * @return mixed
     */
    public function showList()
    {
        $where = [
            'status'   => 1
        ];
        return $this->where($where)
            ->select('id', 'type', 'storage')
            ->get()->toArray();
    }
}
