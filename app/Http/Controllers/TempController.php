<?php
/**
 * Created by qizhao
 * Date: 2018/7/3
 * Time: 11:40
 */

namespace App\Http\Controllers;


use App\Model\ContentArticle;
use App\Model\ContentCourse;
use App\Model\ContentExpert;
use App\Model\WxComment;
use App\Model\WxMember;
use App\Model\WxOrder;
use App\Model\WxWeekPlan;
use App\Model\WxWeekPlanItem;
use App\Model\WxWeekPlanJoin;
use App\Model\WxWeekPlanPoints;
use Carbon\Carbon;
use extend\Wchat\Wchat;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TempController extends Controller
{
    /**
     * 用户迁移
     */
    public function postMember()
    {
        set_time_limit(0);
        Log::info('用户迁移开始');
        DB::table('op_member_temp')->chunk(1000, function ($users){
            foreach ($users as $user){
                try{
                    WxMember::create([
                        'member_id' => $user->member_id,
                        'nick_name' => $user->nick_name,
                        'sex'       => empty($user->sex)? 0: $user->sex,
                        'photo'     => $user->photo,
                        'phone'     => $user->phone,
                        'source'    => ($user->source == 9)? 0: 1,
                        'open_id'   => $user->third_identifier,
                        'balance'   => $user->balance * 100,
                        'ip'        => long2ip($user->ip_number),
                    ]);
                } catch (\Exception $e){
                    Log::error('用户迁移出错', [$e->getMessage(), collect($user)->toArray()]);
                }
            }
        });
        Log::info('用户迁移完成');
        echo 'ok';
    }

    /**
     * 导入马甲用户
     */
    public function postNewMember()
    {
        $photofile = 'v3.0/operate_avatar/';
        $filename = '../storage/app/operate_member.xls';
        $data = read_excel($filename, true);
        Log::info('新增马甲用户开始', $data);
        foreach ($data as $v){
            try{
                WxMember::create([
                    'nick_name' => $v[0],
                    'photo'     => $photofile.$v[1],
                    'source'    => 0
                ]);
            }catch (\Exception $e){
                Log::error('新增马甲出错', [$e->getMessage(), $v]);
            }
        }
        Log::info('新增马甲用户结束');
        echo 'ok';
    }

    /**
     * 评论数据
     */
    public function postComment()
    {
        $filename = '../storage/app/operate_comments.xls';
        $data = read_excel($filename, true);
        $member = new WxMember();

        Log::info('新增评论开始');
        foreach ($data as $v){
            try{
                $content_type = $v[1];
                $member_id = $member->where([
                    'nick_name' => $v[3],
                    'source'    => 0
                ])->value('member_id');
                switch ($content_type){
                    case 1:
                        $content_id = ContentCourse::where('title', '=', $v[2])
                            ->value('course_id');
                        break;

                    case 2:
                        $content_id = ContentArticle::where('title', '=', $v[2])
                            ->value('article_id');
                        break;

                    case 3:
                        $content_id = ContentExpert::where('name', '=', $v[2])
                            ->value('expert_id');
                        break;
                }
                WxComment::create([
                    'member_id'     => $member_id,
                    'content_type'  => $content_type,
                    'content_id'    => $content_id,
                    'content'       => trim($v[0]),
                    'source'        => 0
                ]);
            }catch (\Exception $e){
                Log::error('新增评论出错', [$e->getMessage(), $v]);
            }
        }
        Log::info('新增评论结束');
        echo 'ok';

    }

    /**
     * 订购数据
     */
    public function postOrders()
    {
        $filename = '../storage/app/operate_orders.xls';
        $data = read_excel($filename, true);
        $member = new WxMember();
        $now = Carbon::now();
        Log::info('新增订单开始');
        foreach ($data as $v){
            try{
                $content_type = $v[0];
                $member_id = $member->where([
                    'nick_name' => $v[2],
                    'source'    => 0
                ])->value('member_id');
                switch ($content_type){
                    case 1:
                        $content_id = ContentCourse::where('title', '=', $v[1])
                            ->value('course_id');
                        break;

                    case 2:
                        $content_id = ContentExpert::where('name', '=', $v[1])
                            ->value('expert_id');
                        break;

                    default:
                        throw new \Exception('类型错误');
                }
                WxOrder::create([
                    'member_id'     => $member_id,
                    'type'          => $content_type,
                    'service_id'    => $content_id,
                    'order_no'      => create_orderno(),
                    'source'        => 0,
                    'status'        => 2,
                    'paid_time'     => $now
                ]);
            }catch (\Exception $e){
                Log::error('新增订单出错', [$e->getMessage(), $v]);
            }
        }
        Log::info('新增订单结束');
        echo 'ok';
    }

    /**
     * 资讯内容迁移
     */
    public function postArticles()
    {
        set_time_limit(0);
        Log::info('资讯迁移开始');
        DB::table('cms_article')->chunk(100, function ($items){
            foreach ($items as $item){
                DB::beginTransaction();
                try{
                    $article = new ContentArticle();
                    $article->title        = $item->cms_title;
                    $article->summary      = $item->summary;
                    $article->photo        = $item->photo_show;
                    $article->age          = $item->age;
                    $article->status       = $item->status;
                    $article->created_time = $item->addtime;
                    $article->source_type  = 2;
                    $article->save();

                    $article->detail()->create([
                        'content'      => htmlspecialchars_decode($item->content)
                    ]);
                    DB::commit();

                } catch (\Exception $e){
                    DB::rollBack();
                    Log::error('资讯迁移出错', [$e->getMessage()]);
                }
            }
        });
        Log::info('资讯迁移完成');
        echo 'ok';
    }

    public function postTest()
    {

        $wx      = create_wchat();
        $plan    = WxWeekPlan::find(34);

        $url     = Config::get('api.h5_uri').'plan';
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

                $today_task_str = plan_item_tostring($today_task);

                // 已延期任务
                $points = WxWeekPlanPoints::where(['join_id'=>$join->join_id])->lists('item_id');
                $unfinished_task = WxWeekPlanItem::where([
                    'plan_id'  => $plan->plan_id
                ])->where('week_num', '<', $today_num)
                    ->whereNotIn('item_id', $points)->lists('content_title');

                $unfinished_task_str = plan_item_tostring($unfinished_task);

                $msg_data = [
                    'first'       => '早上好，快速了解你今天的任务吧！',
                    'keyword1'    => $today_task_str,
                    'keyword2'    => $unfinished_task_str,
                    'remark'      => '感谢您参与',
                ];

                $open_id = $join->member->open_id;
                $wx->TemplateMessageSend($open_id, $tpl_id, $url, $msg_data);

            }catch (\Exception $e){
                Log::error(__FUNCTION__, [$e->getMessage()]);
            }
        });

    }

}