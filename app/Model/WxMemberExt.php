<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 17:52
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class WxMemberExt extends Model
{
    protected $table = 'wx_member_ext';

    protected $guarded = [];

    public $timestamps = false;

}