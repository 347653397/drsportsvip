<?php

namespace App\Model\ClubExamsItemsStudent;

use Illuminate\Database\Eloquent\Model;

class ClubExamsItemsStudent extends Model
{
    /**
     * 模型关联的数据表
     * @var string
     */
    protected $table = 'club_exam_items_student';

    /**
     * 测验某个项目
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function exam_item()
    {
        return $this->hasOne('App\Model\ClubSystem\ClubExamsItems','id','exam_items_id');
    }

    /**
     * 测验某个项目等级
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function exam_item_level()
    {
        return $this->hasOne('App\Model\ClubSystem\ClubExamsItemsLevel','id','exam_items_level_id');
    }
}