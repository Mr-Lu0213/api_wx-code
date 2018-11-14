<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the controller to call when that URI is requested.
|
*/

Route::group(['middleware' => 'api'], function () {

    Route::controller('home', 'HomeController');            // 首页

    Route::controller('article', 'ArticleController');      // 资讯

    Route::controller('course', 'CourseController');        // 课程

    Route::controller('expert', 'ExpertController');        // 专家

    Route::controller('content', 'ContentController');      // 内容相关

    Route::controller('column', 'ColumnController');        // 微课堂

    Route::controller('center', 'CenterController');        // 个人中心

    Route::controller('plan', 'PlanController');            // 周计划

    Route::controller('order', 'OrderController');          // 订单

    Route::controller('feedback', 'FeedbackController');    // 反馈

    Route::controller('temp', 'TempController');    // 临时

    Route::controller('pc', 'PcController');    // 轮播图

});

Route::get('oauth', 'OpenController@oauth')->name('oauth');   // 微信授权
Route::any('notify-wx', 'OpenController@notifyWx')->name('notify-wx');   // 微信支付回调