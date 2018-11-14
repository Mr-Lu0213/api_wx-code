<?php
/**
 * 内容阅读
 */

namespace App\Jobs;

use App\Model\WxHistory;
use App\Model\WxWeekPlanItem;
use App\Model\WxWeekPlanJoin;
use App\Model\WxWeekPlanPoints;
use Carbon\Carbon;
use Illuminate\Contracts\Bus\SelfHandling;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class ContentRead extends Job implements SelfHandling, ShouldQueue
{
    protected $type;        // 1课程 2资讯 3专家 4课程章节
    protected $data;        // 内容模型
    protected $member_id;   // 用户ID

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data)
    {
        Log::info(__CLASS__, $data);
        $this->type      = $data[0];
        $this->data      = $data[1];
        $this->member_id = $data[2];
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        // 我的历史
        $more      = '';
        $target_id = 0;
        $history   = new WxHistory();

        // 1课程 2资讯 3专家 4课程播放
        switch ($this->type){
            case 1:
                $target_id = $this->data->course_id;
                break;

            case 2:
                $this->data->increment('look_num');
                $target_id = $this->data->article_id;
                break;

            case 3:
                $target_id = $this->data->expert_id;
                break;

            case 4:
                $this->data->course->increment('play_num');
                $target_id = $this->data->course_id;
                $more      = json_encode(['ma_id' => $this->data->ma_id]);
                $this->type= 1;     // 按课程处理
                break;
        }

        $history->type      = $this->type;
        $history->more      = $more;
        $history->target_id = $target_id;
        $history->member_id = $this->member_id;
        $history->save();

    }
}
