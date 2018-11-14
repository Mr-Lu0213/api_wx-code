<?php
/**
 * 内容相关操作
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 18:05
 */

namespace App\Http\Controllers;


use App\Model\BasicTags;
use App\Model\WxCollection;
use App\Model\WxComment;
use App\Model\WxMemberTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ContentController extends Controller
{

    /**
     * 添加或取消收藏
     * @return \Illuminate\Http\JsonResponse
     */
    public function postCollection()
    {
        $data = $this->request->all();
        $validate = Validator::make($data, [
            'content_type' => 'required',
            'content_id'   => 'required',
            'action_type'  => 'required',
        ]);
        if($validate->fails()){
            return response_error($validate->errors()->first());
        }

        $where = [
            'content_type' => $data['content_type'],
            'content_id' => $data['content_id'],
            'member_id' => $this->member_id
        ];
        try{
            if($data['action_type'] == 1 && WxCollection::create($where)){
                // 添加收藏
                return response_success();
            }else if($data['action_type'] == 2 && $Model = WxCollection::where($where)->first()){
                // 取消收藏
                $Model->delete();
                return response_success();
            }else{
                return response_error();
            }
        }catch (\Exception $e){
            Log::error(__FUNCTION__, [$e->getMessage(), $where]);
            return response_error();
        }

    }

    /**
     * 提交评论
     */
    public function postComment()
    {
        $data = $this->request->all();
        $validate = Validator::make($data, [
            'content_type' => 'required',
            'content_id'   => 'required',
            'content'      => 'required|max:500',
        ]);
        if($validate->fails()){
            return response_error($validate->errors()->first());
        }

        $data['member_id'] = $this->member_id;
        if(WxComment::create($data)){
            return response_success();
        }else{
            return response_error();
        }

    }

    /**
     * 获取评论列表
     * @return \Illuminate\Http\JsonResponse
     */
    public function postCommentList()
    {
        $data = $this->request->all();
        $validate = Validator::make($data, [
            'content_type' => 'required',
            'content_id'   => 'required',
        ]);
        if($validate->fails()){
            return response_error($validate->errors()->first());
        }

        $list = (new WxComment())->with(['member'=>function($query){
            $query->select('member_id', 'nick_name', 'photo');
        }])->where([
            'content_type'=> $data['content_type'],
            'content_id'  => $data['content_id'],
            'status'      => 1
        ])->orderBy('comment_id', 'desc')
            ->skip($this->page_skip)->take($this->page_size)
            ->select('comment_id', 'member_id', 'content', 'created_time')->get();

        $result['comment_list'] = $list;
        return response_success($result);
    }

    /**
     * 用户标签
     * @return \Illuminate\Http\JsonResponse
     */
    public function postTags()
    {
        $list = BasicTags::where(['parent_id'=>0])->select('tag_id','name')->get();
        $member_tags = WxMemberTag::where(['member_id' => $this->member_id])
            ->lists('tag_id')->toArray();
        foreach ($list as $k=>$v){
            if(in_array($v->tag_id, $member_tags)){
                $list[$k]->selected = 1;
            }else{
                $list[$k]->selected = 0;
            }
        }
        $result['tag_list'] = $list;
        return response_success($result);
    }

    /**
     * 用户标签提交
     */
    public function postMemberTags()
    {
        $tags = $this->request->input('tags');
        DB::beginTransaction();
        try{
            WxMemberTag::where(['member_id' => $this->member_id])->delete();
            foreach ($tags as $v){
                WxMemberTag::create([
                    'member_id' => $this->member_id,
                    'tag_id' => $v
                ]);
            }
            DB::commit();
            return response_success();
        }catch (\Exception $e){
            DB::rollback();
            Log::error(__FUNCTION__, [$e->getMessage()]);
            return response_error($e->getMessage());
        }
    }


}