<?php

namespace App\Model\ClubExamsStudent;

use Illuminate\Database\Eloquent\Model;

class ClubExamsStudent extends Model
{
    /**
     * 模型关联的数据表
     * @var string
     */
    protected $table = 'club_exams_student';

    /**
     * 定义学员测验关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function exams()
    {
        return $this->belongsTo('App\Model\ClubExams\ClubExams', 'exam_id');
    }
}