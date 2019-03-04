<?php

namespace App\Model\ClubSalesExamine;

use Illuminate\Database\Eloquent\Model;

class ClubSalesExamine extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_sales_examine';

    /**
     * 定义审核学员关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Model\ClubStudent\ClubStudent', 'student_id');
    }
}