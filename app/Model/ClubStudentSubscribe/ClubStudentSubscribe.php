<?php

namespace App\Model\ClubStudentSubscribe;

use App\Model\ClubChannel\ClubChannel;
use App\Model\ClubSales\ClubSales;
use Illuminate\Database\Eloquent\Model;
use App\Facades\Util\Common;

class ClubStudentSubscribe extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_student_subscribe';

    /**
     * 对应的学员
     */
    public function student()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent', 'student_id', 'id');
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
     * 定义销售关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function sale()
    {
        return $this->hasOne(ClubSales::class, 'id', 'sales_id');
    }

    /**
     * 定义课程关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function course()
    {
        return $this->hasOne('App\Model\ClubCourse\ClubCourse', 'id', 'course_id');
    }

    /**
     * 定义渠道关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function channel()
    {
        return $this->hasOne(ClubChannel::class, 'id', 'channel_id');
    }

    /**
     * 定义与签到关系
     * @return mixed
     * @date 2018/9/26
     * @author jesse
     */
    public function courseSign()
    {
        return $this->hasOne('App\Model\ClubCourse\ClubCourseSign', 'id', 'sign_id');
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

    /**
     * 获取预约状态名称
     * @param $subscribeStatus
     * @return mixed
     */
    public function getSubscribeStatusName($subscribeStatus)
    {
        $allStatus = ['待出勤', '已出勤', '未出勤', '已取消'];
        return $allStatus[$subscribeStatus] ?? '未知';
    }

    /**
     * 获取预约来源名称
     * @param $sourceType
     * @return mixed
     */
    public function getSubscribeSourceName($sourceType)
    {
        $allSource = ['App', '后台'];
        return $allSource[$sourceType - 1] ?? '未知';
    }

    /**
     * 获取班级学员年龄阶段
     * @param $classId
     * @return array
     */
    public function getClassStudentMinAndMaxAge($classId)
    {
        return Common::getClassStudentMinAndMaxAge($classId);
    }

    /**
     * 获取签到状态 (for app)
     * @param $dutyStatus
     * @return mixed
     */
    public function transformDutyStatusCodeForApp($dutyStatus)
    {
        return Common::transformDutyStatusCodeForApp($dutyStatus);
    }

    /**
     * 获取课程状态（for app）
     * @param $courseStatus
     * @param $courseDay
     * @param $courseStartTime
     * @param $courseEndTime
     * @return mixed
     */
    public function getCourseStatusForApp($courseStatus,$courseDay,$courseStartTime,$courseEndTime)
    {
        return Common::getCourseStatusForApp($courseStatus,$courseDay,$courseStartTime,$courseEndTime);
    }
}
