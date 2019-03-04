<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/16
 * Time: 10:17
 */

namespace App\Model\ClubStudentFeedback;


use Illuminate\Database\Eloquent\Model;

class ClubStudentFeedback extends Model
{
    /**
     * 关联的数据表
     * @var string
     */
    protected  $table = 'club_student_feedback';

    /**
     * 定义原因关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reason()
    {
        return $this->belongsTo('App\Model\ClubStudentFeedbackReason\ClubStudentFeedbackReason', 'reason_id');
    }
}