<?php

namespace App\Model\ClubSales;
use Illuminate\Database\Eloquent\Model;

class ClubSalesPerformanceBySeasonSnashot extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_sales_performance_by_season_snapshot';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = true;


}