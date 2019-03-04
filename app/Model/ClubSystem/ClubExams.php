<?php

namespace App\Model\ClubSystem;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 16:45
 */


use App\Model\Club\Club;
use Illuminate\Database\Eloquent\Model;

class ClubExams extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_exams';

    /**
     * 测验与俱乐部
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function club()
    {
        return $this->hasOne(Club::class,'id','club_id');
    }

    /**
     * 测验与测验项目
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function examItems()
    {
        return $this->hasMany(ClubExamsItems::class,'exam_id','id');
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
