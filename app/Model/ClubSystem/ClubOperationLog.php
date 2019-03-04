<?php

namespace App\Model\ClubSystem;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 16:45
 */


use Illuminate\Database\Eloquent\Model;

class ClubOperationLog extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_operation_log';
    public $timestamps = true;
}
