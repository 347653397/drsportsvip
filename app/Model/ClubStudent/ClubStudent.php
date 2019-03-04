<?php

namespace App\Model\ClubStudent;

use Illuminate\Database\Eloquent\Model;

class ClubStudent extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected $table = 'club_student';

    protected $fillable = ['left_course_count','is_pay_again','status'];

    /**
     * 对应的退款
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function refund(){
        return $this->hasMany('App\Model\ClubStudentRefund\ClubStudentRefund','student_id');
    }

    /**
     * 定义学员班级关系
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function class()
    {
        return $this->belongsTo('App\Model\ClubClass\ClubClass', 'main_class_id');
    }

    /**
     * 定义学员场馆关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function venue()
    {
        return $this->belongsTo('App\Model\Venue\Venue', 'venue_id');
    }

    /**
     * 定义学员俱乐部关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function club()
    {
        return $this->belongsTo('App\Model\Club\Club', 'club_id');
    }

    /**
     * 定义学员与销售之间的关系
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function sales()
    {
        return $this->belongsTo('App\Model\ClubSales\ClubSales','sales_id');
    }

    /**
     * 定义学员与购买记录关系
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function payments()
    {
        return $this->hasMany('App\Model\ClubStudentPayment\ClubStudentPayment','student_id','id');
    }

    /**
     * 定义学员与签到之间的关系
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function signs()
    {
        return $this->hasMany('App\Model\ClubCourse\ClubCourseSign','student_id');
    }

    /**
     * 定义学员与课程券的关系
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function tickets()
    {
        return $this->hasMany('App\Model\ClubCourseTickets\ClubCourseTickets','student_id');
    }

    /**
     * 定义学员预约关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function studentSubscribe()
    {
        return $this->belongsTo('App\Model\ClubStudentSubscribe\ClubStudentSubscribe', 'id', 'student_id');
    }

    /**
     * 定义班级学员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classStudent()
    {
        return $this->belongsTo('App\Model\ClubClassStudent\ClubClassStudent', 'student_id');
    }

    /**
     * 正式学员
     * @param $query
     * @return mixed
     */
    public function scopeOfficalStudents($query)
    {
        return $query->where('status',1);
    }

    /**
     * 学员核心信息（club_student_core）
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function core()
    {
        return $this->belongsTo('App\Model\ClubStudentCore\ClubStudentCore','core_id');
    }

    /**
     * 学员绑定的账户
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function binduser()
    {
        return $this->hasMany('App\Model\ClubStudentBindApp\ClubStudentBindApp','student_id','id')->where('is_delete',0);
    }


    /**
     * 非冻结学员
     * @param $query
     * @return mixed
     */
    public function scopeNotFreeze($query)
    {
        return $query->where('is_freeze',0);
    }

    /**
     * 未删除的学员
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}
