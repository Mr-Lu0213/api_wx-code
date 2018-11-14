<?php
/**
 * 微信工具类
 * Created by: zhang
 * Date Time : 2017/12/23 15:10
 */
namespace extend\Wchat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Wchat
{
    protected $instance;    // 实例ID 备用
    protected $appid;       // 微信应用ID
    protected $appsecret;   // 微信应用密钥
    protected $token;       // 微信应用全局access_token
    protected $values;

    public function __construct($instance = 1)
    {
        $this->instance  = $instance;
    }

    // 设置appid
    public function setAppid($appid)
    {
        $this->appid     = $appid;
    }

    // 设置secret
    public function setSecret($appsecret)
    {
        $this->appsecret = $appsecret;
    }

    // get请求
    private function get_url($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        Log::info(__FUNCTION__.' '. $output .' ==== '.$url);
        curl_close($ch);
        return $output;
    }

    // 获取全局access_token 并缓存
    private function InitToken(){
        $url = 'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid='.$this->appid.'&secret='.$this->appsecret;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $a = curl_exec($ch);
        $strjson=json_decode($a);
        if (empty($strjson->access_token)){
            Log::error(__FUNCTION__, [$url, $a]);
            throw new \Exception("获取token失败: $a");
        }else{
            $token = $strjson->access_token;
            Cache::put('wxtoken-'.$this->instance, $token, 7100);
            $this->token = Cache::get('wxtoken-'.$this->instance);
        }
    }

    /**
     * 功能说明：请求与返回函数,防token过期,$needToken默认为false,
     * @param string $url 跳转地址
     * @param json[Post] $data
     */
    private function GetUrlReturn($url, $data, $needToken = false){
        //第一次为空，尝试从缓存获取
        if (empty($this->token)){
            $this->token = Cache::get('wxtoken-'.$this->instance);
        }
        //依旧为空则重新取值
        if (empty($this->token) or $needToken){
            $this->InitToken();
        }
        $newurl = sprintf($url, $this->token);
        $curl = curl_init();  //创建一个新url资源
        curl_setopt($curl, CURLOPT_URL, $newurl);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
        if (!empty($data)){
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $AjaxReturn = curl_exec($curl);
        curl_close($curl);
        $strjson = json_decode($AjaxReturn);
        if (!empty($strjson->errcode)){
            Log::error(__FUNCTION__.' '.$AjaxReturn.' '.$data);
            switch ($strjson->errcode){
                case 40001:
                    return $this->GetUrlReturn($url, $data, true); //获取access_token时AppSecret错误，或者access_token无效
                    break;
                case 40014:
                    return $this->GetUrlReturn($url, $data, true); //不合法的access_token
                    break;
                case 42001:
                    return $this->GetUrlReturn($url, $data, true); //access_token超时
                    break;
                case 45009:
                    return "接口调用超过限制：".$strjson->errmsg;
                    break;
                case 41001:
                    return "缺少access_token参数：".$strjson->errmsg;
                    break;
                default:
                    return $strjson->errmsg; //其他错误，抛出
                    break;
            }
        }else{
            Log::info(__FUNCTION__.' == '. $url.' == '.$AjaxReturn);
            return $AjaxReturn;
        }
    }

    /*************认证接口*******************************************************************************************/
    /**
     * 获取用户的openid
     * @return 用户的openid
     */
    public function get_openid(){
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger')) {
            //通过code获得openid
            if (empty($_GET['code'])){
                //触发微信返回code码
                if (!empty($_SERVER['QUERY_STRING'])) {
                    $query_string = "?".$_SERVER['QUERY_STRING'];
                }
                $baseUrl = 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].$query_string;
                $url = $this->get_authorize_url_base($baseUrl, "123");
                Header("Location: $url");
                exit();
            } else {
                //获取code码，以获取openid
                $code = $_GET['code'];
                $data = $this->get_access_token($code);
                $openid = $data['openid'];
                //session('openid', $openid); //写入本地SESSION
            }
        }
        return $openid;
    }
    /**
     * 获取OAuth2授权access_token
     * @param string $code 通过get_authorize_url获取到的code
     */
    public function get_access_token($code = ''){
        $token_url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$this->appid}&secret={$this->appsecret}&code={$code}&grant_type=authorization_code";
        $data = $this->get_url($token_url);
        return $data;
    }
    /**
     * 获取微信OAuth2静默授权链接snsapi_base
     * @param string $redirect_uri 跳转地址
     * @param mixed $state 参数
     * 不弹出授权页面，直接跳转，只能获取用户openid
     */
    public function get_authorize_url_base($redirect_uri = '', $state = 'STATE'){
        $redirect_uri = urlencode($redirect_uri);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_base&state={$state}#wechat_redirect";
    }
    /**
     * 获取微信OAuth2授权链接snsapi_userinfo
     * @param string $redirect_uri 跳转地址
     * @param mixed $state 参数
     * 弹出授权页面，可通过openid拿到昵称、性别、所在地。并且，即使在未关注的情况下，只要用户授权，也能获取其信息
     */
    public function get_authorize_url_userinfo($redirect_uri = '', $state = 'STATE'){
        $redirect_uri = urlencode($redirect_uri);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$this->appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_userinfo&state={$state}#wechat_redirect";
    }

    /**
     * 拉取OAuth2授权用户信息(需scope为 snsapi_userinfo)
     * @param string $access_token 通过OAuth2授权get_access_token获取到的access_token
     * @param string $openid 用户openid
     */
    public function get_access_token_userinfo($access_token, $openid){
        $token_url = "https://api.weixin.qq.com/sns/userinfo?access_token={$access_token}&openid={$openid}&lang=zh_CN";
        $userinfo_data = $this->GetUrlReturn($token_url);
        return $userinfo_data;
    }



    //单发消息
    public function SendMessage($openid, $msgtype, $content){
        $json = '{"touser":"%s","msgtype":"%s","text":{"content":"%s"}}';
        $jsondata = sprintf($json, $openid, $msgtype, $content);
        $url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=%s";
        return $this->GetUrlReturn($url, $jsondata);
    }

    //群发消息
    public function GroupSendMessage($jsondata){
        $url = "https://api.weixin.qq.com/cgi-bin/media/uploadnews?access_token=%s";
        return $this->GetUrlReturn($url, $jsondata);
    }

    //获取模板ID POST请求
    public function TemplateID ($templateno){
        $templateno_array =  array("template_id_short" => $templateno);
        $json = json_encode($templateno_array);
        $url = "https://api.weixin.qq.com/cgi-bin/template/api_add_template?access_token=%s";
        return $this->GetUrlReturn($url, $json);
    }

    //模版消息发送
    public function TemplateMessageSend ($openid, $templateId, $url, $data){
        $array = array(
            'touser'      => $openid,
            'template_id' => $templateId,
            'url'         => $url,
            'topcolor'    => '#FF0000',
            'data'        => array('first'=>array('value'=>$data['first'],'color'=>'#173177'),
                'keyword1'=> array('value'=>isset($data['keyword1'])? $data['keyword1']: '','color'=>'#173177'),
                'keyword2'=> array('value'=>isset($data['keyword2'])? $data['keyword2']: '','color'=>'#173177'),
                'keyword3'=> array('value'=>isset($data['keyword3'])? $data['keyword3']: '','color'=>'#173177'),
                'keyword4'=> array('value'=>isset($data['keyword4'])? $data['keyword4']: '','color'=>'#173177'),
                'remark'  => array('value'=>isset($data['remark'])? $data['remark']: '',  'color'=>'#173177')
            )
        );
        $json = json_encode($array);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=%s";
        return $this->GetUrlReturn($url, $json);
    }

    //jsapi_ticket   JS接口的临时票据
    public function jsapi_ticket (){
        $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token=%s&type=jsapi";
        $ticket = json_decode($this->GetUrlReturn($url),true);
        return $ticket['ticket'];
    }

    /**
     * 获取用户基础信息
     * @param $values
     * @return mixed
     */
    public function get_user_info($values)
    {
        $url = 'https://api.weixin.qq.com/sns/userinfo?';
        return $this->get_url($url . http_build_query($values));
    }

}