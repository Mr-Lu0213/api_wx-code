<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */
namespace App\Model;

use Illuminate\Database\Eloquent\Model;

class WxFeedbackReply extends Model
{
    protected $table = 'wx_feedback_reply';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

    /**
     * 更新已读状态
     */
    public function read()
    {
        if($this->type == 2 && $this->status == 1){
            $this->status = 0;
            $this->save();
        }
    }

}