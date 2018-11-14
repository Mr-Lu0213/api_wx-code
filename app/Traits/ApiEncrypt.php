<?php
/**
 * Created by PhpStorm.
 * User: tervinsmac
 * Date: 2018/6/13
 * Time: 17:51
 */
namespace App\Traits;

use App\Model\WxMember;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;

trait ApiEncrypt
{

    /**
     * 通过token获取用户ID
     */
    protected function getMemberId()
    {
        $this->member_id = Cache::get('token_'.$this->accept_token, 0);
        if(!$this->member_id){
            throw new \Exception('登录已失效，请重新登录');
        }else{
            $member = WxMember::find($this->member_id);
            if(empty($member)){
                throw new \Exception('用户信息错误');
            }
            $this->member = $member;
            Session::set('member_id', $this->member_id);
            Session::set('member', $member);
        }
        $this->refreshToken();
    }

    /**
     * 刷新token过期时间
     */
    public function refreshToken()
    {
        Cache::put('token_'.$this->accept_token, $this->member_id,  Config::get('api.token_ttl', 600));
    }

    /**
     * 数据加密：aes 256  +  base64
     * @param string $key    加密钥匙
     * @param string $value  待加密明文
     * @return string
     */
    public function encrypt($value)
    {
        $this->getKey();
        return str_replace(array('+','/','='), array('-','_',''),
            base64_encode (
                openssl_encrypt($value,
                    "AES-256-CBC", $this->enc_key,
                    OPENSSL_RAW_DATA ,str_repeat(chr(0),16))
            )
        );
    }

    /**
     * 数据解密: aes 256  +  base64
     * @param string $key     解密钥匙
     * @param string $value   待解密密文
     * @return string
     */
    public function decrypt ($value)
    {
        $this->getKey();
        return openssl_decrypt(
            base64_decode(str_replace(array('-','_'),array('+','/'), $value)),
            "AES-256-CBC", $this->enc_key,
            OPENSSL_RAW_DATA, str_repeat(chr(0),16)
        );
    }

    /**
     * 通过终端类型获取对应的密钥
     * @param number $deviceType  1、H5
     */
    public function getKey()
    {
        $key_map = array(
            1 => 'Viq0lUpfXvDRA9GWNZXVPxzXagTeb2uz',  // H5
        );

        $this->enc_key = $key_map[$this->device_type];
    }

    /**
     * 数据签名 md5 数据 + 应用版本
     * @param string $value    消息数据
     * @param string $version  应用版本
     * @return string
     */
    public function sign($value)
    {
        return md5($value.$this->api_version);
    }

    /**
     * 检测敏感词
     * @param $data
     * @return bool
     */
    public function filterKeywords($data)
    {
        $keywords = $this->getKeyWords();
        $keywords = array_chunk($keywords, 1000);
        foreach ($keywords as $d){
            $blacklist="/".implode("|",$d)."/i";
            foreach ($data as $k=>$v){
                if(is_string($v) && preg_match($blacklist, $v, $matches)){
                    Log::error(__FUNCTION__, [$matches, $data]);
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * 获取敏感词
     * @return array
     */
    public function getKeywords()
    {
        $keywords = Cache::rememberForever('basic_filter_keywords', function() {
            return json_encode(DB::table('basic_filter')->lists('keyword'));
        });
        return json_decode($keywords, true);
    }

}
