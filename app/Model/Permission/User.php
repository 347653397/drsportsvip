<?php

namespace App\Model\Permission;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_user';

    /**
     * 定义用户角色关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function role()
    {
        return $this->belongsTo('App\Model\Permission\Role', 'role_id');
    }

    /**
     * 定义用户部门关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function department()
    {
        return $this->belongsTo('App\Model\Permission\Department', 'department_id');
    }

}