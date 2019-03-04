<?php

namespace App\Model\ClubStudentBindApp;

use App\Model\ClubStudent\ClubStudent;
use Illuminate\Database\Eloquent\Model;

class ClubStudentBindApp extends Model
{
    /**
     * 模型关联的数据表
     * @var string
     */
    protected $table = 'club_student_bind_app';

    /**
     * 定义APP用户学员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent', 'student_id');
    }

    /**
     * 学员所属俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student_club()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent','student_id');
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