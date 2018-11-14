<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxWeekItemContent extends Model
{
    protected $table = 'wx_week_item_content';

    /**
     * 获取内容
     * @param $value
     * @return string
     */
    public function getContentAttribute($value)
    {
        return htmlspecialchars_decode($value);
    }
}