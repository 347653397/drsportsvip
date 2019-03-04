<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/12
 * Time: 17:22
 */

namespace App\Model\ClubStudentRefund;

use Illuminate\Database\Eloquent\Model;

class ClubStudentRefund extends Model
{
    protected $table = 'club_student_refund';

    public function student(){
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent','student_id');
    }
}