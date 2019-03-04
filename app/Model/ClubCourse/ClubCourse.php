<?php

namespace App\Model\ClubCourse;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Facades\Util\Common;

class ClubCourse extends Model
{
    //
    protected $table = 'club_course';

    /**
     * 课程对应的教练
     */
    public function coachCourse(){
        return $this->hasMany('App\Model\ClubCourseCoach\ClubCourseCoach','course_id');
    }

    /**
     * 课程场馆
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function venue()
    {
        return $this->belongsTo('App\Model\ClubVenue\ClubVenue','venue_id','id');
    }

    /**
     * 课程教练
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function coach()
    {
        return $this->hasOne('App\Model\ClubCoach\ClubCoach','id','coach_id');
    }

    /**
     * 课程所有教练
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function course_coach()
    {
        return $this->hasMany('App\Model\ClubCourseCoach\ClubCourseCoach','course_id','id');
    }

    /**
     * 课程对应的签到
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function course_sign()
    {
        return $this->hasMany('App\Model\ClubCourse\ClubCourseSign','course_id','id');
    }

    /**
     * 课程对应的俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club','club_id');
    }

    /**
     * 课程对应的班级
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo('App\Model\ClubClass\ClubClass','class_id');
    }

    /**
     * 定义班级上课时间关系
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function class_time()
    {
        return $this->hasMany('App\Model\ClubClassTime\ClubClassTime', 'class_id', 'class_id');
    }

    /**
     * 非删除的课程
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }

    /**
     * 处于上课的课程
     * @param $query
     * @return mixed
     */
    public function scopeCourseOn($query)
    {
        return $query->where('status',1);
    }

    public function getFirstClass($classId)
    {
        return Common::getFirstClass($classId);
    }

    /**
     * 获取距离
     * @param $lat
     * @param $lng
     * @param $veLat 场馆纬度
     * @param $veLng 场馆经度
     * @return mixed
     */
    public function getDistance($lat, $lng, $veLat, $veLng)
    {
        return Common::getDistance($lat, $lng, $veLat, $veLng);
    }


    public function scopeScreen($query, $venueId, $classId)
    {
        if (!empty($venueId) && empty($classId)) {
            return $query->where('venue_id', $venueId);
        }

        if (!empty($venueId) && !empty($classId)) {
            return $query->where('venue_id', $venueId)->orWhere('class_id', $classId);
        }

        if (!empty($startDate) && !empty($endDate)) {
            return $query->whereBetween('created_at', [$startDate, $endDate]);
        }
    }

    public function courseIncome()
    {
        return $this->hasMany('App\Model\ClubIncomeSnapshot\ClubIncomeSnapshot', 'course_id', 'id');
    }
}
