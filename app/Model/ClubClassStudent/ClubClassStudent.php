<?php

namespace App\Model\ClubClassStudent;

use Illuminate\Database\Eloquent\Model;

class ClubClassStudent extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected $table = 'club_class_student';

    protected $fillable = ['left_course_count','sales_id','club_id','student_id'];

    /**
     * 定义班级学员与学员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent', 'student_id');
    }

    /**
     * 对应的班级
     */
    public function classes(){
        return $this->belongsToMany('App\Model\ClubClass\ClubClass','club_course','coach_id','class_id');
    }

    /**
     * 班级学员对应的班级
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo('App\Model\ClubClass\ClubClass','class_id');
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