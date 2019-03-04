<?php

namespace App\Model\Permission;

use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_permission';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = true;



}