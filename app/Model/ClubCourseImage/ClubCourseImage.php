<?php

namespace App\Model\ClubCourseImage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubCourseImage extends Model
{
    use SoftDeletes;

    /**
     * 应该被调整为日期的属性
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * 模型关联的数据表
     * @var string
     */
    protected $table = 'club_course_image';

}