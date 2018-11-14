<?php

namespace App\Http\Middleware;

use App\Traits\ApiEncrypt;
use Closure;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class ApiMiddleware
{
    use ApiEncrypt;

    protected $enc_key;         // 加密key

    protected $device_type;     // 终端类型 1、H5

    protected $api_version;     // 版本号

    protected $request;

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if($request->input('env') == 'local' && Config::get('app.debug')){
            // 测试通道
            $accept_token = create_token($request->input('member_id'));
            $request->offsetSet('accept_token', $accept_token);
            $data = $request->input();
        }else{
            // 参数解密
            $this->device_type = $request->input('device_type', 1);
            $this->api_version = $request->input('api_version');
            $this->getKey();
            if($request->input('accept_sign') != $this->sign($request->data)){
                throw new \Exception('签名不匹配');
            }

            Log::info('data', [$this->decrypt($request->data)]);
            $data = json_decode($this->decrypt($request->data), true);

            foreach ($data as $k=>$v){
                $request->offsetSet($k, $v);
            }
        }

        // 过滤敏感词
        if(!$this->filterKeywords($data)){
            throw new \Exception('请勿输入敏感词汇');
        }

        Log::info('request_handle', [$request->path(), $request->all()]);

        $origin   = Config::get('api.cors_origin', '*');
        $response = $next($request);
        $response->header('Access-Control-Allow-Origin', $origin);
        $response->header('Access-Control-Allow-Headers', 'Origin, Content-Type, Cookie, Accept, multipart/form-data, application/json');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PATCH, PUT, OPTIONS');
        $response->header('Access-Control-Allow-Credentials', 'false');
        return $response;
    }

}
