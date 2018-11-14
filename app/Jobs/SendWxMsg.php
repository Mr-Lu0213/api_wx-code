<?php
/**
 * 发送微信模板消息
 */

namespace App\Jobs;

use App\Model\WxCoupon;
use App\Model\WxCouponDetail;
use App\Model\WxWeekPlan;
use App\Model\WxWeekPlanInvites;
use App\Model\WxWeekPlanItem;
use App\Model\WxWeekPlanJoin;
use App\Model\WxWeekPlanPoints;
use Carbon\Carbon;
use extend\Payment\WxPay;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class SendWxMsg extends Job implements SelfHandling, ShouldQueue
{
    protected $type;                // 任务来源类型   1、订单成功  2、报名成功  3、任务提醒
    protected $model;               // 主体模型
    protected $plan;                // 计划模型
    protected $member;              // 用户模型
    protected $tpl_id;              // 模板ID
    protected $open_id;             // 用户open_id
    protected $msg_data;            // 消息主体
    protected $url;                 // 跳转链接
    protected $url_order_course;    // 我的课程订单
    protected $url_order_expert;    // 我的专家订单
    protected $url_plan;            // 计划页面


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        $this->type  = $data['type'];
        $this->model = $data['model'];
        Log::info(__CLASS__, collect($data)->toArray());
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try{
            switch ($this->type){
                // 1、订单成功  2、报名成功  3、任务提醒
                case 1:
                    $this->orderData();
                    $this->send();
                    break;

                case 2:
                    $this->plan   = $this->model->plan;
                    $this->member = $this->model->member;
                    $this->joinData();
                    $this->send();
                    break;

                case 3:
                    $this->taskData();
                    break;

                default:
                    return;

            }

        }catch (\Exception $e){
            Log::error(__CLASS__, [$e->getMessage()]);
        }
    }

    /**
     * 执行发送
     */
    public function send()
    {
        $wx = create_wchat();
        $wx->TemplateMessageSend($this->open_id, $this->tpl_id, $this->url, $this->msg_data);
    }

    /**
     * 订单
     * @throws \Exception
     */
    public function orderData()
    {
        $this->selUrl($this->model->type);

        if(in_array($this->model->type, [1, 2])){
            // 课程、专家订单
            $this->open_id = $this->model->member->open_id;
            $this->tpl_id  = Config::get('api.wx.temple_list.order');
            $this->msg_data = [
                'first'       => '你好，已收到新订单',
                'keyword1'    => $this->model->order_id,
                'keyword2'    => "{$this->model->money}元",
                'keyword3'    => date("Y-m-d H:i"),
                'keyword4'    => abs($this->model->original_money - $this->model->money)."元",
                'remark'      => '请尽快处理订单',
            ];

        }else if($this->model->type == 3){
            // 周计划报名
            $this->plan   = (new WxWeekPlan())->find($this->model->service_id);
            $this->member = $this->model->member;
            $this->joinData();

        }else{
            throw new \Exception('订单类型错误-'.$this->model->type);
        }
    }

    /**
     * 报名
     */
    public function joinData()
    {
        $this->open_id = $this->member->open_id;
        $this->tpl_id  = Config::get('api.wx.temple_list.plan_join');
        $this->msg_data = [
            'first'       => '恭喜您，报名成功！',
            'keyword1'    => $this->member->nick_name,
            'keyword2'    => $this->plan->title,
            'keyword3'    => date("Y年m月d日", strtotime($this->plan->start_date)),
            'remark'      => '感谢您参与',
        ];
    }

    /**
     * 任务
     */
    public function taskData()
    {
        $wx      = create_wchat();
        $plan    = $this->model;

        if($plan->start_date > Carbon::today()){
            return;
        }
        $this->selUrl(3);
        $url     = $this->url;
        $tpl_id  = Config::get('api.wx.temple_list.plan_task');
        WxWeekPlanJoin::with(['member'=>function($query){
            $query->select('member_id', 'open_id');
        }])->where([
            'plan_id' => $plan->plan_id,
            'status'  => 2
        ])->chunk(1, function ($join) use ($wx, $tpl_id, $plan, $url) {
            try{
                $join = $join[0];
                Log::info(__FUNCTION__, [$join]);
                // 查询今日任务
                $today_num = Carbon::parse($plan->start_date)->diffInDays() + 1;  // 当前进度（第几天）
                $today_task = WxWeekPlanItem::where([
                    'plan_id'  => $plan->plan_id,
                    'week_num' => $today_num
                ])->lists('content_title');
                if(!empty($today_task)){
                    $today_task_str = plan_item_tostring($today_task);

                    // 已延期任务
                    $points = WxWeekPlanPoints::where(['join_id'=>$join->join_id])->lists('item_id');
                    $unfinished_task = WxWeekPlanItem::where([
                        'plan_id'  => $plan->plan_id
                    ])->where('week_num', '<', $today_num)
                      ->whereNotIn('item_id', $points)->lists('content_title');
                    $unfinished_task_str = empty($unfinished_task)? "无": plan_item_tostring($unfinished_task);

                    $msg_data = [
                        'first'       => '早上好，快速了解你今天的任务吧！',
                        'keyword1'    => $today_task_str,
                        'keyword2'    => $unfinished_task_str,
                        'remark'      => '感谢您参与',
                    ];

                    $open_id = $join->member->open_id;
                    $wx->TemplateMessageSend($open_id, $tpl_id, $url, $msg_data);
                }

            }catch (\Exception $e){
                Log::error(__FUNCTION__, [$e->getMessage()]);
            }
        });
    }

    /**
     * 跳转链接
     * @param $type
     */
    public function selUrl($type)
    {
        switch ($this->type){
            // 1、课程订单  2、专家订单  3、周计划
            case 1:
                $this->url_order_course = Config::get('api.h5_uri').'myCourseOrder';
                break;

            case 2:
                $this->url_order_expert = Config::get('api.h5_uri').'myExpertOrder';
                break;

            case 3:
                $this->url_plan         = Config::get('api.h5_uri').'plan';
                break;
        }
    }


}
