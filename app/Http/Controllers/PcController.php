<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 18:05
 */

namespace App\Http\Controllers;

use App\Model\PcCourse;
use App\Model\PcExpert;
use App\Model\PcShow;

class PcController extends Controller
{
    /**
     * 详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function postShow()
    {
        $result['show_list'] = (new PcShow())->showList();

        return response_data(1, '成功', $result);
    }

    public function postExpert()
    {
        $result['expert_list'] = (new PcExpert())->Experts();

        return response_data(1, '成功', $result);
    }

    public function postCourse()
    {
        $result['course_list'] = (new PcCourse())->Courses();

        return response_data(1, '成功', $result);
    }

}