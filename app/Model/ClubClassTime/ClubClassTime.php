<?php

namespace App\Model\ClubClassTime;

use Illuminate\Database\Eloquent\Model;

class ClubClassTime extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected $table = 'club_class_time';

    /**
     * 定义时间班级关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function class()
    {
        return $this->belongsTo('App\Model\ClubClass\ClubClass', 'class_id', 'id');
    }
}