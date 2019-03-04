<?php

namespace App\Model\ClubOperationLog;

use Illuminate\Database\Eloquent\Model;

class ClubOperationLog extends Model
{
    /**
     * 关联到模型的数据表
     * @var string
     */
    protected $table = 'club_operation_log';

    protected $fillable = ['operation_user_id','operation_user_name','module_id','operation_content','operation_desc'];
}