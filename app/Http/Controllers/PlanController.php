<?php
/**
 * 周计划
 * Created by qizhao
 * Date: 2018/6/26
 * Time: 16:02
 */

namespace App\Http\Controllers;


use App\Jobs\SendWxMsg;
use App\Model\WxWeekPlan;
use App\Model\WxWeekPlanInvites;
use App\Model\WxWeekPlanItem;
use App\Model\WxWeekPlanJoin;
use App\Model\WxWeekPlanPoints;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlanController extends Controller
{
    private $currentPlan;

    public function __construct(Request $request)
    {
        parent::__construct($request);

        $this->currentPlan = get_current_plan();
    }

    /**
     * 周计划首页
     */
    public function postIndex()
    {
        $plan = $this->currentPlan;
        // 查询用户当前进度
        $plan->join_status();
        $plan = collect($plan)->toArray();
        if(!empty($plan['items'])){
            // 计划事项按日期归类
            $plan['items'] = items_sort($plan['items']);
        }

        return response_success(['plan'=>$plan]);
    }

    /**
     * 参与记录
     */
    public function postJoinList()
    {
        $where = [
            'plan_id' => $this->request->input('plan_id'),
            'status'  => 2
        ];
        $list = WxWeekPlanJoin::with('member')->where($where)
            ->select('join_id', 'member_id', 'type', 'created_time')->get();
        foreach ($list as $k=>$v){
            $list[$k]->member->phone = $v->member->phone_text;
        }
        return response_success(['join_list' => $list]);
    }

    /**
     * 开启邀请流程
     */
    public function postInvites()
    {
        $plan_id = $this->request->input('plan_id');

        if($plan_id != $this->currentPlan->plan_id){
            return response_error('计划ID错误');
        }else if($this->currentPlan->join_status() > 0){
            return response_error('请勿重复申请');
        }
        try{
            $join = WxWeekPlanJoin::create([
                'plan_id'   => $plan_id,
                'member_id' => $this->member_id,
                'type'      => 2
            ]);

            // 消息通知
            dispatch(new SendWxMsg([
                'type' => 2,
                'model'=> $join
            ]));

            return response_success();
        }catch (\Exception $e) {
            return response_error();
        }
    }

    /**
     * 邀请进度
     */
    public function postInvitesState()
    {
        $plan_id = $this->request->input('plan_id');

        $result['join'] = WxWeekPlanJoin::where([
            'type'      => 2,
            'plan_id'   => $plan_id,
            'member_id' => $this->member_id,
        ])->select('status', 'invites_num')->first();

        $invited_list = WxWeekPlanInvites::with('invited')->where([
            'plan_id'   => $plan_id,
            'member_id' => $this->member_id,
        ])->select('invited_id')->get();
        $result['invited_list'] = collect($invited_list)->pluck('invited');

        return response_success($result);
    }

    /**
     * 执行进度
     */
    public function postItemsDetail()
    {
        $plan_id = $this->request->input('plan_id');

        $join = WxWeekPlanJoin::with('points')->where([
            'plan_id'   => $plan_id,
            'member_id' => $this->member_id,
        ])->first();
        if(!empty($join->points)){
            $item_point = collect($join->points)->pluck('item_id')->toArray();  // 已得分项目
        }else{
            $item_point = [];
        }

        $items = $this->currentPlan->items;

        foreach ($items as $k=>$v){
            $items[$k]->is_pointed = 0;
            if(in_array($v->item_id, $item_point)){
                $items[$k]->is_pointed = 1;
            }
        }

        $plan_items = items_sort(collect($items)->toArray());
        $items_total = 0;                // 总进度数
        $pointed_items_total = 0;        // 已完成进度
        foreach($plan_items as $k=>$v){
            $flag = true;
            foreach ($v['items'] as $vo){
                $plan_items[$k]['content_type'] = $vo['content_type'];
                if(!$vo['is_pointed']){
                    $flag = false;
                    continue;
                }
                $pointed_items_total++;     // 已完成进度+1
            }
            $items_total += count($v['items']);
            $plan_items[$k]['all_pointed'] = $flag;     // 当天事项是否全部完成
        }

        $result['pointed_items_total'] = $pointed_items_total;
        $result['plan_items']   = $plan_items;
        $result['points_total'] = $join->points_total;  // 当前得分总数
        $result['items_total']  = $items_total;         // 总进度
        return response_success($result);

    }

    /**
     * 得分记录
     */
    public function postMyPoints()
    {
        $plan_id = $this->request->input('plan_id');

        $join = WxWeekPlanJoin::with('points')->where([
            'plan_id'   => $plan_id,
            'member_id' => $this->member_id,
        ])->first();

        // 按日统计总得分数并排序
        $temp = [];
        if(!empty($join->points)){
            foreach ($join->points as $k=>$v){
                if(empty($v->item)){
                    continue;
                }
                if(isset($temp[$v->item->week_num])){
                    $temp[$v->item->week_num] += $v->item->points;
                }else{
                    $temp[$v->item->week_num] = $v->item->points;
                }
            }
            ksort($temp);
        }

        // 整理得分和日期
        $week_now = Carbon::now()->dayOfWeek;
        $week_points = [];
        foreach ($temp as $k=>$v){
            $week_points[] = [
                'week_num' => $k,
                'week_date' => date('m-d', time()- ($week_now - $k) * 86400),
                'points_total' => $v
            ];
        }
        $result['my_points'] = $week_points;
        $result['points_total'] = $join->points_total;     // 周总得分

        // 周得分排行榜
        $rank_list = WxWeekPlanJoin::with(['member' => function($query){
            $query->select('member_id', 'nick_name', 'photo');
        }])->where([
            'plan_id' => $plan_id,
            'status'  => 2
        ])->orderBy('points_total', 'desc')
            ->select('join_id', 'member_id', 'points_total')->get();
        $result['rank_list'] = $rank_list;

        $my_rank_info = '';
        $my_rank = '';
        foreach ($rank_list as $k=>$v){
            if($v->member_id == $this->member_id){
                $my_rank_info = $v;
                $my_rank = $k+1;
                break;
            }
        }
        $result['my_rank'] = $my_rank;
        $result['my_rank_info'] = $my_rank_info;

        return response_success($result);

    }

    /**
     * 执行内容
     */
    public function postItemAction()
    {
        $item_id = $this->request->input('item_id', 0);

        $currentPlan = get_current_plan();
        if($currentPlan->status == 1 && $join = (new WxWeekPlanJoin())->where([
                'plan_id'   => $currentPlan->plan_id,
                'member_id' => $this->member_id,
                'status'    => 2
            ])->first()){

            // 查询可执行的内容
            $week_day = Carbon::parse($currentPlan->start_date)->diffInDays() + 1;  // 当前进度（第几天）
            $item = (new WxWeekPlanItem())->where([
                'plan_id' => $currentPlan->plan_id,
                'item_id' => $item_id
            ])->where('week_num', '<=', $week_day)->first();

            try{
                $data = WxWeekPlanPoints::firstOrNew([
                    'join_id' => $join->join_id,
                    'item_id' => $item->item_id
                ]);
                if(!$data->id){
                    $data->points = $item->points;
                    $data->save();
                    $join->increment('points_total', $item->points);
                }

                $result['content'] = $item->content;
                return response_success($result);
            }catch (\Exception $e){
                Log::error(__FUNCTION__, [$e->getMessage()]);
            }
        }
    }


}