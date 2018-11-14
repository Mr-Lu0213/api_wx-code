<?php
/**
 * Created by qizhao
 * Date: 2018/7/2
 * Time: 13:41
 */

namespace extend\Payment;


use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class WxPay
{
    protected $appid;        //appid
    protected $secret;       //app_secret
    protected $key;
    protected $mch_id;
    protected $notify_url;
    protected $values;       //请求数据
    protected $cert_pem;
    protected $key_pem;
    protected $url;          //要分享的url

    // 统一下单
    public $order_url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
    //退款接口
    public $refund = 'https://api.mch.weixin.qq.com/secapi/pay/refund';
    // 企业支付
    private $trans_url = "https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers";

    public $h5Url;
    public $oauth_url = 'https://api.weixin.qq.com/sns/oauth2/access_token?';    //鉴权地址
    public $user_info_url = 'https://api.weixin.qq.com/sns/userinfo?';    //获取用户信息

    public function wxInit(){

        $this->appid      = Config::get('api.wx.app_id');
        $this->secret     = Config::get('api.wx.app_secret');
        $this->key        = Config::get('api.wx.key');
        $this->mch_id     = Config::get('api.wx.mch_id');
        $this->h5Url      = Config::get('api.h5_uri');
        $this->notify_url = Config::get('api.wx.notify_url');

    }

    public function setValues($values)
    {
        $this->values = $values;
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }


    // 解析数组
    private function getJson($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        Log::info(__FUNCTION__.' '. $output .' ==== '.$url);
        curl_close($ch);
        return json_decode($output, true);
    }



    /*
     * 获取分享所需的参数
     */
    public function getSignPackage() {
        $jsapiTicket = $this->getJsApiTicket();
        // 注意 URL 一定要动态获取，不能 hardcode.
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        //    $url = "$protocol$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        $url = $this->url;
        $timestamp = time();
        $nonceStr = $this->createNonceStr();

        // 这里参数的顺序要按照 key 值 ASCII 码升序排序
        $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
        $signature = sha1($string);

        $signPackage = array(
            "appId"     => $this->appid,
            "nonceStr"  => $nonceStr,
            "timestamp" => $timestamp,
            "url"       => $url,
            "signature" => $signature,
            "rawString" => $string
        );
        return $signPackage;
    }

    /*
     * 获取access_token   判断有效期
     */
    private function getAccessToken($refresh = false) {
        // access_token 应该全局存储与更新
        $access_token = Cache::get('wx_access_token');
        if (empty($access_token) || $refresh) {
            // 如果是企业号用以下URL获取access_token
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/gettoken?corpid=$this->appId&corpsecret=$this->appSecret";
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appid&secret=$this->secret";
            $res = $this->httpGet($url);
            $access_token = $res["access_token"];
            if ($access_token) {
                Cache::put('wx_access_token', $access_token, 110);  // 缓存110分钟
            }
        }
        return $access_token;
    }

    /*
     * jsapi_ticket
     */
    private function getJsApiTicket() {

        $data = json_decode($this->get_php_file("./storage/wx/jsapi_ticket.php"));
        if (empty($data) || $data->expire_time < time()) {
            $accessToken = $this->getAccessToken();
            // 如果是企业号用以下 URL 获取 ticket
            // $url = "https://qyapi.weixin.qq.com/cgi-bin/get_jsapi_ticket?access_token=$accessToken";
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
            $res = $this->httpGet($url);
            $ticket = $res['ticket'];
            if ($ticket) {
                $data_input = new \stdClass();
                $data_input->expire_time = time() + 7000;
                $data_input->jsapi_ticket = $ticket;
                $this->set_php_file("./storage/wx/jsapi_ticket.php", json_encode($data_input));
            }
        } else {
            $ticket = $data->jsapi_ticket;
        }
        return $ticket;
    }

    /*
     * 生成随机字符串
     */
    private function createNonceStr($length = 16) {
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }

    /*
     * curl get
     */
    private function httpGet($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($ch);
        curl_close($ch);
        return json_decode($output, true);
    }

    /*
     * 获取PHP文件内容
     */
    private function get_php_file($filename) {
        return trim(substr(file_get_contents($filename), 15));
    }

    /*
     * 内容写入PHP文件
     */
    private function set_php_file($filename, $content) {
        $fp = fopen($filename, "w");
        fwrite($fp, "<?php exit();?>" . $content);
        fclose($fp);
    }

    /**
     * 输出xml字符
     *
     * @throws WxPayException
     *
     */
    private function ToXml()
    {
        if (! is_array($this->values) || count($this->values) <= 0) {
            throw new \Exception("数组数据异常！");
        }

        $xml = "<xml>";
        foreach ($this->values as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }


    /**
     * 附带证书的请求
     * @param unknown $url
     * @param unknown $vars
     * @param number $second
     * @param unknown $aHeader
     * @return mixed|boolean
     */
    private function curl_post_ssl($vars, $url, $second=30, $aHeader=array())
    {
        $ch = curl_init();
        //超时时间
        curl_setopt($ch,CURLOPT_TIMEOUT,$second);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch,CURLOPT_URL,$url);
        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,false);

        curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLCERT, $this->cert_pem);
        curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
        curl_setopt($ch,CURLOPT_SSLKEY, $this->key_pem);

        if( count($aHeader) >= 1 ){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $aHeader);
        }

        curl_setopt($ch,CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$vars);
        $data = curl_exec($ch);
        if($data){
            curl_close($ch);
            return $data;
        }
        else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new \Exception("call faild, errorCode:$error\n") ;
            return false;
        }
    }


    /**
     * 将xml转为array
     *
     * @param string $xml
     * @throws WxPayException
     */
    private function FromXml($xml)
    {
        if (! $xml) {
            throw new \Exception("xml数据异常！");
        }
        // 将XML转为array
        // 禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $this->values = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $this->values;
    }



    /**
     * curl请求数据
     *
     * @param string $data
     * @param string $url
     * @return mixed
     */
    private function WxCurl($data = "", $url = "", $type = "POST")
    {
        // 首先检测是否支持curl
        if (! extension_loaded("curl")) {
            trigger_error("请开启curl功能模块！", E_USER_ERROR);
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, 0);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10);
        if (strtoupper($type) == "POST") {
            curl_setopt($curl, CURLOPT_POST, true);
        }
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        $a = curl_exec($curl);
        curl_close($curl);
        return $a;
    }

    /**
     * 生成签名
     *
     * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
     */
    public function MakeSign()
    {
        // 签名步骤一：按字典序排序参数
        ksort($this->values);
        $buff = "";
        foreach ($this->values as $k => $v) {
            if ($k != "sign" && $v != "" && ! is_array($v)) {
                $buff .= $k . "=" . $v . "&";
            }
        }
        $buff = trim($buff, "&");
        $string = $buff;

        // 签名步骤二：在string后加入KEY
        $string = $string . "&key=" . $this->key;
        // Log::info("MD5前数据", [$string]);
        // 签名步骤三：MD5加密
        $string = md5($string);
        // 签名步骤四：所有字符转为大写
        $result = strtoupper($string);
        return $result;
    }

    /**
     * 响应微信回调
     * @param bool $state
     * @return string
     */
    public function notify_result($state = false)
    {
        if($state){
            $str = '<xml>';
            $str .= '<return_code><![CDATA[SUCCESS]]></return_code>';
            $str .= '<return_msg><![CDATA[OK]]></return_msg>';
            $str .= '</xml>';
        }else{
            $str = '<xml>';
            $str .= '<return_code><![CDATA[FAIL]]></return_code>';
            $str .= '<return_msg><![CDATA[FAIL]]></return_msg>';
            $str .= '</xml>';
        }
        echo $str;
        exit;
    }

    /**
     * 支付方式
     * @param $order
     * @param $member
     * @return array
     * @throws \Exception
     */
    public function charge($order, $member)
    {
        $nonce_str = $this->createNonceStr(8);   // 生成随机字符串
        $total_fee = $order['money'] * 100;             // 订单总金额，单位为分
        $trade_type = "JSAPI";

        switch ($order['type']){
            case 1:
                $body = '订阅课程';
                break;
            case 2:
                $body = '订阅专家';
                break;
            case 3:
                $body = '订阅周计划';
                break;
            default:
                $body = '购买商品';
        }

        $this->wxInit();
        $this->values['body']             = $body;
        $this->values['appid']            = $this->appid;
        $this->values['openid']           = $member->open_id;
        $this->values['mch_id']           = $this->mch_id;
        $this->values['nonce_str']        = $nonce_str;
        $this->values['total_fee']        = $total_fee;
        $this->values['notify_url']       = $this->notify_url;
        $this->values['trade_type']       = $trade_type;
        $this->values['time_start']       = date("YmdHis");
        $this->values['time_expire']      = date("YmdHis", time()+600);  //支付时限为十分钟
        $this->values['out_trade_no']     = $order['order_no'];
        $this->values['spbill_create_ip'] = $_SERVER["REMOTE_ADDR"];

        $this->values['sign'] = $this->makeSign();

        //转为xml格式
        $xml = $this->ToXml();
        Log::info("统一下单XML参数:", [$xml]);

        $xml = $this->WxCurl($xml, $this->order_url);
        $arr = $this->FromXml($xml);
        Log::info("统一下单结果：".json_encode($arr, 256));

        if($arr['return_code'] == "SUCCESS"){
            $ststime = time();
            // H5签名参数
            $this->values = [
                'appId'     => $arr["appid"],
                'nonceStr'  => $arr["nonce_str"],
                'package'   => "prepay_id=" . $arr["prepay_id"],
                'timeStamp' => $ststime,
                'signType'  => "MD5"
            ];
            $sign_new = $this->MakeSign();

            $res = array();
            $res["prepay_id"]    = $arr["prepay_id"];
            $res["sign"]         = $sign_new;
            $res["nonce_str"]    = $arr["nonce_str"];
            $res["timestamp"]    = $ststime;
            $res["appid"]        = $arr["appid"];
            $res["partnerid"]    = $this->mch_id;
            $res["package"]      = $this->values['package'];
            $res["out_trade_no"] = $order['order_no'];
            $res["total_fee"]    = $total_fee;

            return $res;
        }else{
            throw new \Exception($arr['return_msg']);
        }
    }

    /**
     * 发送微信模板消息
     * @param $info
     * @param bool $refresh
     * @param int $max
     * @return mixed
     */
    public function wx_template_msg($info, $refresh = false, $max = 0)
    {
        $this->wxInit();
        // Log::info(__FUNCTION__.' '.json_encode($info));
        $access_token = $this->getAccessToken($refresh);
        $url = "https://api.weixin.qq.com/cgi-bin/message/template/send?access_token=$access_token";
        $data = [
            "touser"        => $info['open_id'],
            "template_id"   => $info['template_id'],
            "url"           => $info['url'],
            "data"=>[
                "first" =>[
                    "value" => isset($info['first'])?$info['first']:"",
                    "color" => '#173177'
                ],
                "keyword1" =>[
                    "value" => isset($info['keyword1'])?$info['keyword1']:"",
                    "color" =>'#173177'
                ],
                "keyword2" =>[
                    "value" => isset($info['keyword2'])?$info['keyword2']:"",
                    "color" =>'#173177'
                ],
                "keyword3" =>[
                    "value" => isset($info['keyword3'])?$info['keyword3']:"",
                    "color" =>'#173177'
                ],
                "keyword4" =>[
                    "value" => isset($info['keyword4'])?$info['keyword4']:"",
                    "color" =>'#173177'
                ],
                "remark" =>[
                    "value" => isset($info['remark'])?$info['remark']:"",
                    "color" =>'#173177'
                ],
            ]
        ];
        $data  = json_encode($data);
        $result = $this->WxCurl($data, $url, 'POST');
        $check = json_decode($result);
        if($check->errcode == 40001 && $max < 3){
            //token无效 刷新后继续请求
            $max++;
            return $this->wx_template_msg($info, true, $max);
        }
        Log::info(__FUNCTION__.' '.json_encode($check));
        return $result;
    }


}