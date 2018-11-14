<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 17:50
 */

namespace App\Model;


use Illuminate\Database\Eloquent\Model;

class ContentCourseDetail extends Model
{
    protected $table = 'content_course_detail';

    protected $primaryKey = 'id';

    public function getContentAttribute($value)
    {
        return htmlspecialchars_decode($value);
    }

    public function getResultsAttribute($value)
    {
        return empty($value)? []: explode("\r\n", trim($value));
    }
}