<?php

namespace App\Console\Commands;

use App\Jobs\SendWxMsg;
use Illuminate\Console\Command;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Log;

class PlanMsg extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'planmsg';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'plan msg';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $plan = get_current_plan();
        Log::info(__CLASS__, ['任务触发', $plan]);
        dispatch(new SendWxMsg([
            'type' => 3,
            'model'=> $plan
        ]));
    }
}
