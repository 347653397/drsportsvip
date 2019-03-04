<?php

namespace  App\Model\ClubCourseSign;

use Illuminate\Database\Eloquent\Model;
use App\Facades\Util\Common;
use App\Model\ClubCourseTickets\CourseTickets;

class ClubCourseSign extends Model
{
    //
    protected $table = 'club_course_sign';

    /**
     * 获取签到状态名称
     * @param $signStatus
     * @return mixed
     */
   static public function getSignStatusName($signStatus)
    {
        if (empty($signStatus)) return '待出勤';
        $allSign = [
            '1' => '出勤',
            '2' => '缺勤',
            '3' => '事假',
            '4' => '病假',
            '5' => '冻结',
            '6' => 'PASS',
            '7' => 'AutoPass',
            '8' => '外勤预留',
        ];

        return $allSign[$signStatus];
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
     * 获取当前签到记录的学员是否缴费
     * @param $signId
     * @return mixed
     */
    public function getIsPay($signId)
    {
        return CourseTickets::where('sign_id',$signId)->exists();
    }

    /** 获取缴费金额（单张课程券的价格）
     * @param $signId
     * @return null
     */
    public function getPayAmount($signId)
    {
        $courseTickets = CourseTickets::with('Payment')
            ->where('sign_id',$signId)
            ->first();

        if (isset($courseTickets->payment->price)) {
            return number_format($courseTickets->payment->price/$courseTickets->payment->course_count,2);
        }

        return '0.00';
    }

    /**
     * 定义与学员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent','student_id','id');
    }

    /**
     * 定义与课程关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function course()
    {
        return $this->belongsTo('App\Model\ClubCourse\ClubCourse','course_id','id');
    }

    /** 对应的俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club','club_id','id');
    }

    /**
     * 对应的班级
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo('App\Model\ClubClass\ClubClass','class_id','id');
    }

    /**
     * 未删除的
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}
