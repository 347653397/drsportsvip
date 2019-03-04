<?php

namespace  App\Model\ClubCourseSignRecord;

use Illuminate\Database\Eloquent\Model;

class ClubCourseSignRecord extends Model
{
    /**
     * 关联的数据表
     * @var string
     */
    protected $table = 'club_course_sign_record';

    /**
     * 定义签到学员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent','student_id');
    }

    /**
     * 定义签到操作员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Model\ClubUser\ClubUser','operate_user_id');
    }
}
