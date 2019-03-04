<?php

namespace App\Model\ClubCourseTickets;

use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubPaymentTag\ClubPaymentTag;
use Illuminate\Database\Eloquent\Model;

class CourseTickets extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_course_tickets';

    /**
     * 定义与缴费方案关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function payment()
    {
        return $this->hasOne(ClubPayment::class,'id','payment_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function clubPayment()
    {
        return $this->hasOne(ClubPayment::class,'payment_id','id');
    }
}