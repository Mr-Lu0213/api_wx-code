<?php

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

class WxMember extends Model
{
    //
    protected $table = 'wx_member';

    protected $primaryKey = 'member_id';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

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
     * 隐私手机号获取器
     * @param $value
     * @return string
     */
    public function getPhoneTextAttribute($value)
    {
        return empty($this->phone)? '': substr($this->phone, 0, 3) .'********';
    }

    /**
     * 余额获取器
     * @param $value
     * @return string
     */
    public function getBalanceAttribute($value)
    {
        return sprintf("%0.2f", $value/100);
    }

    /**
     * IP获取器
     * @param $value
     * @return string
     */
    public function getIpAttribute($value)
    {
        return empty($value)? '': long2ip($value);
    }

    /**
     * IP修改器
     * @param $value
     * @return string
     */
    public function setIpAttribute($value)
    {
        $this->attributes['ip'] = empty($value)? 0: ip2long($value);
    }

    /**
     * 检测手机号是否可用
     * @param $phone
     */
    public function checkBindPhone($phone)
    {
        if($this->where('phone', $phone)->first()){
            return false;
        }else{
            return true;
        }
    }

    /**
     * 关联扩展字段
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function ext()
    {
        return $this->hasOne('App\Model\WxMemberExt', 'member_id', 'member_id');
    }

}
