<?php
/**
 * 自定义配置文件
 * Created by PhpStorm.
 * User: qizhao
 * Date: 2018/6/14
 * Time: 11:09
 */
return [
    'upload_dir' => 'v3.0/',                  // 文件存放目录

    'token_ttl'  => env('token_ttl'),   // token过期时间(分钟)

    'res_url'    => env('res_url'),     // 静态资源地址

    'h5_uri'     => env('h5_uri'),      // 前端uri

    'cors_origin'=> env('cors_origin'), // 跨域来源

    'aliyun_oss' => [
        'key_id'        => env('oss_id'),
        'key_secret'    => env('oss_key'),
        'end_point'     => env('oss_point'),
        'bucket'        => env('oss_bucket'),
        'res_url'       => env('oss_res_url'),
    ],

    'wx'         => [
        'app_id'        => env('wx_app_id'),          // 应用id
        'app_secret'    => env('wx_app_secret'),      // 应用密钥
        'mch_id'        => env('wx_mch_id'),
        'key'           => env('wx_key'),
        'notify_url'    => env('wx_notify_url'),

        // 模板ID
        'temple_list' => [
            'order'     => env('wx_order_tpl'),         // 购买成功通知
            'plan_join' => env('wx_plan_join_tpl'),     // 成功报名
            'plan_task' => env('wx_plan_task_tpl'),     // 计划任务提醒
        ],
    ],
];