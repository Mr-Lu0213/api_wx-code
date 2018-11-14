<?php

namespace App\Http\Controllers;

use App\Model\WxConfig;
use App\Traits\ApiEncrypt;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

abstract class Controller extends BaseController
{
    use ApiEncrypt;
    use ValidatesRequests;

    protected $member_id;           // 用户ID

    protected $member;              // 用户信息

    protected $request;             // 请求信息

    protected $accept_token;        // 用户token

    protected $token_prefix;        // token缓存前缀

    protected $wx_config;           // 运营自定义配置

    protected $page_skip;           // 分页页码

    protected $page_size;           // 分页数量

    public function __construct(Request $request)
    {
        $this->request = $request;

        $this->accept_token = $request->accept_token;

        $this->getMemberId();

        $this->initWxConfig();

        $this->getPageInfo();

    }

    /**
     * 初始化运营配置
     */
    public function initWxConfig()
    {
        $this->wx_config = WxConfig::lists('value', 'key');
    }

    /**
     * 获取运营配置
     * @param $key
     * @param string $default
     */
    public function getWxConfig($key, $default='')
    {
        return isset($this->wx_config[$key])? $this->wx_config[$key]: $default;
    }

    /**
     * 获取分页参数
     */
    public function getPageInfo()
    {
        $page = $this->request->input('page', 1);
        $size = $this->request->input('size', 20);
        $this->page_skip = ($page - 1) * $size;
        $this->page_size = $size;
    }

}
