<?php

namespace App\Model\ClubCourseCoach;

use Illuminate\Database\Eloquent\Model;

class ClubCourseCoach extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_course_coach';

    /**
     * 定义课程关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function course ()
    {
        return $this->belongsTo('App\Model\ClubCourse\ClubCourse','course_id');
    }

    /**
     * 定义教练关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coach()
    {
        return $this->belongsTo('App\Model\ClubCoach\ClubCoach','coach_id');
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
