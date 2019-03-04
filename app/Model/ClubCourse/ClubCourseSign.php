<?php

namespace App\Model\ClubCourse;

use Illuminate\Database\Eloquent\Model;

class ClubCourseSign extends Model
{
    protected $table = 'club_course_sign';

    /**
     * 定义签到和俱乐部的关系
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club','club_id');
    }

    /**
     * 定义班级关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function class()
    {
        return $this->hasOne('App\Model\ClubClass\ClubClass', 'id', 'class_id');
    }

    /**
     * 定义课程关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function course()
    {
        return $this->hasOne(ClubCourse::class, 'id', 'course_id');
    }

    /**
     * 未删除
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}
