<?php

namespace App\Model\Permission;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_department';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * 定义部门用户关系
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function user()
    {
        return $this->hasMany('App\Model\Permission\User', 'dept_id');
    }
}