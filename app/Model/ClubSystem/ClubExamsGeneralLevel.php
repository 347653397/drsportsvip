<?php

namespace App\Model\ClubSystem;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 16:45
 */


use Illuminate\Database\Eloquent\Model;

class ClubExamsGeneralLevel extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_exams_general_level';
    public $timestamps = true;
}
