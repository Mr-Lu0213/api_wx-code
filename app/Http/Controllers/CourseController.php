<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 18:05
 */

namespace App\Http\Controllers;


use App\Jobs\ContentRead;
use App\Model\ContentCourse;
use App\Model\ContentCourseMaterial;

class CourseController extends Controller
{
    /**
     * 详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function postDetail()
    {
        $course_id = $this->request->input('course_id');

        $detail = (new ContentCourse())->courseDetail($course_id);

        if($detail){
            // 加入队列
            dispatch(new ContentRead([1, $detail, $this->member_id]));

            $result['course'] = $detail;
            return response_success($result);
        }else{
            return response_error('课程信息错误');
        }
    }

    /**
     * 播放章节
     * @return \Illuminate\Http\JsonResponse
     */
    public function postPlay()
    {
        $ma_id  = $this->request->input('ma_id');

        $material = (new ContentCourseMaterial())->getUrl($ma_id);
        if($material){
            // 加入队列
            dispatch(new ContentRead([4, $material, $this->member_id]));

            $result['ma_url'] = $material->ma_url;
            return response_success($result);
        }else{
            return response_data(-1, '尚未购买');
        }
    }
}