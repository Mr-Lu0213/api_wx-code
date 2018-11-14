<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 18:05
 */

namespace App\Http\Controllers;


use App\Jobs\ContentRead;
use App\Model\ContentArticle;

class ArticleController extends Controller
{

    public function anyDetail()
    {
        $article_id = $this->request->input('article_id');

        $detail = (new ContentArticle())->articleDetail($article_id);

        if($detail){
            dispatch(new ContentRead([2, $detail, $this->member_id]));

            $result['article'] = $detail;
            return response_success($result);
        }else{
            return response_error('资讯信息错误');
        }
    }
}