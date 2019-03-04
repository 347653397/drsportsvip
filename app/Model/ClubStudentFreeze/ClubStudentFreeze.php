<?php

namespace App\Model\ClubStudentFreeze;

use Illuminate\Database\Eloquent\Model;

class ClubStudentFreeze extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected $table = 'club_student_freeze';

    /**
     * 定义操作用户关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function operationUser()
    {
        return $this->belongsTo('App\Model\ClubUser\ClubUser','operation_user_id');
    }
}