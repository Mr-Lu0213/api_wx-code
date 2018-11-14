<?php

namespace App\Providers;

use App\Model\ContentArticle;
use App\Model\ContentCourse;
use App\Model\ContentExpert;
use App\Model\WxCollection;
use App\Model\WxComment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 新增收藏
        WxCollection::created(function($model){
            switch ($model->content_type){
                case 1:
                    ContentArticle::where(['article_id' => $model->content_id])->increment('collection_num');
                    break;
                case 2:
                    ContentCourse::where(['course_id' => $model->content_id])->increment('collection_num');
                    break;
                case 3:
                    ContentExpert::where(['expert_id' => $model->content_id])->increment('collection_num');
                    break;
            }
        });

        // 取消收藏
        WxCollection::deleted(function($model){
            switch ($model->content_type){
                case 1:
                    ContentArticle::where(['article_id' => $model->content_id])->decrement('collection_num');
                    break;
                case 2:
                    ContentCourse::where(['course_id' => $model->content_id])->decrement('collection_num');
                    break;
                case 3:
                    ContentExpert::where(['expert_id' => $model->content_id])->decrement('collection_num');
                    break;
            }
        });

        // 新增评论
        WxComment::created(function($model){
            switch ($model->content_type){
                case 1:
                    ContentArticle::where(['article_id' => $model->content_id])->increment('comment_num');
                    break;
                case 2:
                    ContentCourse::where(['course_id' => $model->content_id])->increment('comment_num');
                    break;
                case 3:
                    ContentExpert::where(['expert_id' => $model->content_id])->increment('comment_num');
                    break;
            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
