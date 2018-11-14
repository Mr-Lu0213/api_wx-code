<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 13:46
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class ContentArticleDetail extends Model
{
    protected $table = 'content_article_detail';

    public $timestamps = false;

    public $guarded = [];

    public function getContentAttribute($value)
    {
        return htmlspecialchars_decode($value);
    }

    public function setContentAttribute($value)
    {
        $this->attributes['content'] = htmlspecialchars($value);
    }


}