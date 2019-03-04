<?php

namespace App\Model\ClubStudentPayment;

use Illuminate\Database\Eloquent\Model;

class ClubStudentPayment extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_student_payment';

    protected $fillable = ['is_delete'];

    /**
     * 对应的销售员
     */
    public function sales(){
        return $this->belongsTo('App\Model\ClubSales\ClubSales','sales_id');
    }

    /**
     * 对应的缴费方案
     */
    public function payment()
    {
        return $this->belongsTo('App\Model\ClubPayment\ClubPayment','payment_id');
    }

    /**
     * 对应的学生信息
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public  function student(){
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent','student_id');
    }

    /**
     * 对应的操作员
     */
    public function operator(){
        return $this->belongsTo('App\Model\ClubUser\ClubUser','operation_user_id');
    }

    /**
     * 对应的退款信息
     */
    public function refund(){
        return $this->hasOne('App\Model\ClubStudentRefund\ClubStudentRefund','student_payment_id');
    }

    /**
     * 对应的课程卷
     */
    public function ticket(){
        return $this->hasMany('App\Model\ClubCourseTickets\ClubCourseTickets','payment_id');
    }

    /**
     * 未删除的数据
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }

}