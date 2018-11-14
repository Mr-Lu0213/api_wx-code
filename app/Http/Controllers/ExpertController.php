<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 18:05
 */

namespace App\Http\Controllers;


use App\Jobs\ContentRead;
use App\Model\ContentExpert;

class ExpertController extends Controller
{
    /**
     * 详情
     * @return \Illuminate\Http\JsonResponse
     */
    public function postDetail()
    {
        $expert_id = $this->request->input('expert_id');

        $detail = (new ContentExpert())->detail($expert_id);

        if($detail){
            dispatch(new ContentRead([3, $detail, $this->member_id]));

            $result['expert'] = $detail;
            return response_success($result);
        }else{
            return response_error('专家信息错误');
        }

    }
}