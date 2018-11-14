<?php
/**
 * 全局函数库
 * User: qizhao
 * Date: 2018/6/19
 * Time: 20:19
 */

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;
use OSS\OssClient;

/**
 * api 通用响应
 * @param int $code
 * @param string $msg
 * @param array $data
 * @return \Illuminate\Http\JsonResponse
 */
function response_data($code = 1, $msg = '成功', $result=[])
{
    $data = [
        'code'   => $code,
        'msg'    => $msg,
        'result' => collect($result)->toArray()
    ];
    if(Config::get('app.debug') == true){
        Log::info(__FUNCTION__, $data);
    }
    return response()->json($data,200, [], 256);
}


/**
 * api 成功响应
 * @return \Illuminate\Http\JsonResponse
 */
function response_success($result=[])
{
    return response_data(1, '成功', $result);
}

/**
 * api 失败响应
 * @return \Illuminate\Http\JsonResponse
 */
function response_error($msg='失败')
{
    return response_data(0, $msg);
}

/**
 * 创建登陆token
 * @param $member_id
 * @return string
 */
function create_token($member_id)
{
    $token = md5($member_id.Config::get('app.key'));
    Cache::put('token_' . $token, $member_id,  Config::get('api.token_ttl', 600));
    return $token;
}

/**
 * 验证手机号码格式
 * @param $phone
 * @return bool
 */
function check_phone($phone)
{
    if(preg_match("/^1[34578]{1}\d{9}$/",$phone)){
        return true;
    }else{
        return false;
    }
}

/**
 * 生成手机验证码
 * @return int
 */
function random_captcha()
{
    return rand(999, 9999);
}


/**
 * 实例化一个oss上传类
 * @return OssClient
 */
function create_oss_client()
{
    $accessKeyId = Config::get('api.aliyun_oss.key_id');
    $accessKeySecret = config::get('api.aliyun_oss.key_secret');
    $endpoint = config::get('api.aliyun_oss.end_point');
    return new OssClient($accessKeyId, $accessKeySecret, $endpoint);
}

/**
 * OSS 图片文件上传
 * @param string $process 图片处理参数
 * @return array 已上传文件路径
 */
function upload_file($file = '', $upload_dir = ''){
    $oss = create_oss_client();
    $oss_bucket = Config::get('api.aliyun_oss.bucket');
    $info = array();
    $upload_dir ? $upload_dir : $upload_dir = Config::get("api.upload_dir");
    $file? $files = [$file]: $files = $_FILES;
    foreach ($files as $k=>$v){
        $file = $v;

        //设置文件上传类型
        $allowExts = array('jpg','gif','png','jpeg');
        $pathinfo = pathinfo($file["name"]);
        if(empty($file["name"])){
            continue;
        }
        if(!in_array(strtolower($pathinfo['extension']), $allowExts)){
            throw new \Exception("文件类型只允许 'jpg','gif','png','jpeg'");
        }

        // 检测文件大小
        $maxSize = 30000000;
        if ($maxSize < $file["size"]) {
            throw new \Exception("文件过大");
        }

        $filename = $file["tmp_name"];
        $pinfo = pathinfo($file["name"]);
        $ftype = @$pinfo['extension'];
        $destination = $upload_dir.date("Ymd")."/" . date("YmdHis") . rand(100, 999) . "." . $ftype;
        try{
            $oss->uploadFile($oss_bucket, $destination, $filename);
            $info[] = $destination;
        }catch (\OSS\Core\OssException $e){
            Log::error(__FUNCTION__." OssException", [$e->getMessage(), $oss_bucket, $destination]);
        }catch (\Exception $e){
            Log::error(__FUNCTION__, [$e->getMessage()]);
        }
    }
    return $info;
}

/**
 * OSS 内容上传为文件
 * @param string $content   内存中的内容
 * @param string $upload_dir    保存的文件地址
 * @return string
 */
function put_object($content = '', $filetype='png', $upload_dir = ''){
    if(!$content){
        return '';
    }
    $oss = create_oss_client();
    $oss_bucket = Config::get('api.aliyun_oss.bucket');
    $upload_dir ? $upload_dir : $upload_dir = Config::get("api.upload_dir");
    $destination = $upload_dir.date("Ymd")."/" . date("YmdHis") . rand(100, 999).'.'.$filetype;
    try{
        $oss->putObject($oss_bucket, $destination, $content);
        return $destination;
    }catch (\OSS\Core\OssException $e){
        Log::error(__FUNCTION__." OssException : ".$e->getMessage());
    }catch (\Exception $e){
        Log::error(__FUNCTION__." : ".$e->getMessage());
    }
}


/**
 * 获取随机字符串
 * @param $len
 * @param null $chars
 * @return string
 */
function get_random_string($len, $chars=null)
{
    if (is_null($chars)){
        $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
    }
    mt_srand(10000000*(double)microtime());
    for ($i = 0, $str = '', $lc = strlen($chars)-1; $i < $len; $i++){
        $str .= $chars[mt_rand(0, $lc)];
    }
    return $str;
}


/**
 * 发送短信验证码
 * @param integer $code   验证码/随机密码
 * @param integer $phone    手机号
 * @return boolean
 */
function send_msg($code, $phone){
    $sms = new \extend\Aliyun\sms\Sms(
        Config::get('api.aliyun_oss.key_id'),
        Config::get('api.aliyun_oss.key_secret')
    );
    $response = $sms->sendSms(
        "家长学堂", // 短信签名
        "SMS_105120058", // 短信模板编号
        $phone, // 短信接收者
        Array(  // 短信模板中字段的值
            "code"=>$code,
            "product"=>"家长学堂"
        )
    );
    date_default_timezone_set('PRC');
    Log::info(__FUNCTION__, [$phone, $code, $response]);
    if($response->Code == 'OK'){
        return true;
    }else{
        return false;
    }

}

/**
 * 获取当前用户ID
 * @return mixed
 */
function get_member_id()
{
    return Session::get('member_id');
}

/**
 * 获取当前用户
 * @return mixed
 */
function get_member()
{
    return Session::get('member');
}

/**
 * 格式化评论时间
 * @param $datetime
 * @return false|string
 */
function comment_time_format($datetime)
{
    $time = strtotime($datetime);
    $diff = time() - $time;
    if($diff < 60 * 60){
        return floor($diff / 60) .'分钟前';
    }else if($diff < 24 * 60 * 60){
        return floor($diff / (60 * 60)) .'小时前';
    }else if($diff < 48 * 60 * 60){
        return '昨天'. date('H:i', $time);
    }else if($diff < 365 * 24 * 60 * 60){
        return date('m月d日 H:i', $time);
    }else{
        return date('Y年m月d日 H:i', $time);
    }
}

/**
 * 历史记录时间格式化
 * @param $datetime
 * @return false|string
 */
function history_time_format($datetime)
{
    $time = strtotime($datetime);
    if(date('Y', $time) < date('Y')){
        return date('Y', $time);    // 一年前
    }else if($datetime < date('Y-m-d')){
        return date('m-d', $time);  // 一天前
    }else{
        return date('H:i', $time);  // 当天
    }
}

/**
 * 解析url参数
 * @param $str
 * @return array
 */
function parse_url_param($str)
{
    $data = array();
    $arr=array();
    $p=array();
    $arr=explode('?', $str);
    if(empty($arr) || empty($arr[1])){
        return [];
    }
    $p = explode('&', $arr[1]);
    foreach ($p as $val) {
        $tmp = explode('=', $val);
        $data[$tmp[0]] = $tmp[1];
    }
    return $data;
}


/**
 * 计划事项按日期归类
 * @param $items
 * @return array
 */
function items_sort($items)
{
    $temp = [];
    foreach ($items as $v){
        if($v['week_num']){
            $temp[$v['week_num']][] = $v;
        }
    }

    $list = [];
    foreach ($temp as $k=>$v){
        $list[] = [
            'week_num' => $k,
            'week_day_title' => $v[0]['week_day_title'],
            'items' => $v
        ];
    }

    return $list;
}

/**
 * 获取当前进行的计划
 */
function get_current_plan()
{
    if(Cache::has('currentPlan')){
        return Cache::get('currentPlan');
    }else{
        $currentPlan = (new \App\Model\WxWeekPlan())->getCurrrentPlan();
        Cache::put('currentPlan', $currentPlan, 5);
        return $currentPlan;
    }
}

/**
 * 创建订单号
 * @return string
 */
function create_orderno()
{
    return strtoupper(MD5(substr(implode(NULL, array_map('ord', str_split(substr(uniqid(), 7, 13), 1))), 0, 8) . time()));
}

/**
 * 读取excel文件内容
 * @param $filename
 * @param $shifttitle   是否去除第一行记录
 * @return array
 */
function read_excel($filename, $shifttitle=true)
{
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);
    $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
    if($shifttitle){
        array_shift($sheetData);
    }
    return $sheetData;
}

/**
 * 导出文件
 */
function export_excel()
{

}

/**
 * 创建微信工具类
 * @return \extend\Wchat\Wchat
 */
function create_wchat()
{
    $wchat = new \extend\Wchat\Wchat();
    $wchat->setAppid(Config::get('api.wx.app_id'));
    $wchat->setSecret(Config::get('api.wx.app_secret'));
    return $wchat;
}

/**
 *
 * @param $arr
 */
function plan_item_tostring($items)
{
    $str = '';
    foreach ($items as $k=>$v){
        $str .= $k+1 . "、$v;";
    }
    return $str;
}
