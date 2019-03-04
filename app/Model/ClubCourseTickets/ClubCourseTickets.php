<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 18:52
 */

namespace App\Model\ClubCourseTickets;


use Illuminate\Database\Eloquent\Model;

class ClubCourseTickets extends Model
{
    protected  $table = 'club_course_tickets';

    protected $fillable = ['status','is_delete'];

    //对应的缴费方案
    public function studentPayment(){
        return $this->belongsTo('App\Model\ClubStudentPayment\ClubStudentPayment','payment_id');
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