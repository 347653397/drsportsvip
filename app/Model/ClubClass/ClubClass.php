<?php

namespace App\Model\ClubClass;

use Illuminate\Database\Eloquent\Model;
use App\Facades\Util\Common;

class ClubClass extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected  $table = 'club_class';

    /**
     * 班级对应的场馆
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function venue(){
        return $this->belongsTo('App\Model\ClubVenue\ClubVenue','venue_id');
    }

    /**
     * 班级对应的学员
     */
    public function student(){
        return $this->hasMany('App\Model\ClubClassStudent\ClubClassStudent','class_id');
    }

    /**
     * 班级对应的俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club','club_id');
    }

    /**
     * 班级对应的签到
     */
    public function sign()
    {
        return $this->hasMany('APP\ClubCourseSign','class_id');
    }

    /**
     * 定义班级时间关系
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function time()
    {
        return $this->hasMany('App\Model\ClubClassTime\ClubClassTime', 'class_id');
    }

    /**
     * 班级老师
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teachers()
    {
        return $this->hasMany('App\Model\ClubClassTeacher\Teacher','class_id','id');
    }

    /**
     * 班级课程教练
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function coachs()
    {
        return $this->hasMany('App\Model\ClubCourseCoach\ClubCourseCoach','class_id','id');
    }

    /**
     * 班级图片
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany('App\Model\ClubClassImage\ClubClassImage','class_id','id');
    }

    /**
     * 课程时间
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classtime()
    {
        return $this->hasMany('App\Model\ClubClass\ClubClassTime','class_id','id');
    }

    /**
     * 获取班级年龄阶段
     * @param $classId
     * @return mixed
     */
    public function getClassStudentMinAndMaxAge($classId)
    {
        return Common::getClassStudentMinAndMaxAge($classId);
    }

    /**
     * 获取班级上课时间（字符串拼接）
     * @param $classId
     * @return mixed
     */
    public function getClassTimeString($classId)
    {
        return Common::getClassTimeString($classId);
    }

    /**
     * 获取班级上课时间
     * @param $classId
     * @return mixed
     */
    public function getClassTimes($classId)
    {
        return Common::getClassTimes($classId);
    }

    /**
     * 获取班级第一个上课时间
     * @param $classId
     * @return mixed
     */
    public function getFirstClassTime($classId)
    {
        return Common::getFirstClassTime($classId);
    }

    /**
     * 获取单节课时长（单位：小时）
     * @param $classId
     * @return mixed
     */
    public function getEveryCourseTimeLong($classId)
    {
        return Common::getEveryCourseTimeLong($classId);
    }

    /**
     * 有效班级（处于生效状态）
     * @param $query
     * @return mixed
     */
    public function scopeValid($query)
    {
        return $query->where('is_delete',0)->where('status',1);
    }

    /**
     * 在app端显示的班级
     * @param $query
     * @return mixed
     */
    public function scopeShowInApp($query)
    {
        return $query->where('show_in_app',1);
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

}

