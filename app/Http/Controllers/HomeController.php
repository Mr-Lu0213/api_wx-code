<?php

namespace App\Http\Controllers;

use App\Model\BasicTags;
use App\Model\ContentArticle;
use App\Model\ContentCourse;
use App\Model\ContentExpert;
use App\Model\WxBanner;


class HomeController extends Controller
{
    /**
     * 首页
     */
    public function postIndex()
    {
        $result['banner_list'] = (new WxBanner())->homeList(1);  // 首页banner

        $result['expert_list'] = (new ContentExpert())->homeList($this->getWxConfig('index_expert_num', 6) + 1);   // 专家列表

        $result['course_list'] = (new ContentCourse())->homeList($this->getWxConfig('index_course_num', 6) + 1);    // 课程列表

        $result['tag_list']    = (new BasicTags())->homeList($this->member_id);   // 推荐标签

        $result['hot_list']    = (new BasicTags())->hotList();   // 热门搜索标签

        return response_data(1, '成功', $result);
    }

    /**
     * 搜索
     */
    public function postSearch()
    {
        $type     = $this->request->input('type', 0);   // 0全部 1专家 2视频课 3资讯
        $keywords = $this->request->input('keywords');
        if($keywords){
            $tag = (new BasicTags())->where(['name'=>$keywords, 'level'=>0])->first();
            $keywords = array_merge([$keywords], empty($tag)? []: $tag->childrenList());
        }
        $result   = array();

        // 搜索专家
        if(in_array($type, [0, 1])){
            $result['expert_list'] = (new ContentExpert())->search($keywords)->toArray();
        }

        // 搜索课程
        if(in_array($type, [0, 2])){
            $result['course_list'] = (new ContentCourse())->search($keywords)->toArray();
        }

        // 搜索资讯
        if(in_array($type, [0, 3])){
            $result['article_list'] = (new ContentArticle())->search($keywords)->toArray();
        }

        return response_data(1, '成功', $result);

    }


}
