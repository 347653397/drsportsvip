<?php

namespace App\Model\ClubSystem;

use Illuminate\Database\Eloquent\Model;

class ClubExamsStudent extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_exams_student';

    /**
     * 测验关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function exam()
    {
        return $this->hasOne('App\Model\ClubSystem\ClubExams','id','exam_id');
    }

    /**
     * 学员关系
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function student()
    {
        return $this->hasOne('App\Model\ClubStudent\ClubStudent','id','student_id');
    }

    /**
     * 测验具体项目明细
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function exam_items()
    {
        return $this->hasMany('App\Model\ClubExamsItemsStudent\ClubExamsItemsStudent','exam_student_id','id');
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
