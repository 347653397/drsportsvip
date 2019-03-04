<?php

namespace App\Model\ClubStudentSalesHistory;

use Illuminate\Database\Eloquent\Model;

class ClubStudentSalesHistory extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_student_sales_history';

    /**
     * 操作员
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function operator()
    {
        return $this->hasOne(ClubUser::class,'id','operation_user_id');
    }
}