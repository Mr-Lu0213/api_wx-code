<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxOrderDetail extends Model
{
    protected $table = 'wx_order_detail';

    protected $guarded = [];

    public $timestamps = false;


}