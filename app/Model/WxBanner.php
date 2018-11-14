<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class WxBanner extends Model
{
    //
    protected $table = 'wx_banner';

    /**
     * 获取列表
     * @param $type
     * @return mixed
     */
    public function homeList($type)
    {
        $where = [
            'status' => 1,
            'type'   => $type
        ];
        return $this->where($where)
            ->orderBy('recommend', 'desc')
            ->orderBy('id', 'desc')
            ->select('id', 'type', 'link_type', 'link_url', 'photo')
            ->get()->toArray();
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
     * url获取器
     * @param $value
     * @return string
     */
    public function getLinkUrlAttribute($value)
    {
        $uri = Config::get('api.h5_uri');
        switch ($this->link_type){
            case 1:
                return $uri.'courseDetails?courseId='.$value;

            case 2:
                return $uri.'newsDetails?newsId='.$value;

            case 3:
                return $uri.'expertDetails?expertId='.$value;

            default:
                return $value;
        }
    }

}
