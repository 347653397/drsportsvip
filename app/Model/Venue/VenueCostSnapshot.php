<?php

namespace App\Model\Venue;

use Illuminate\Database\Eloquent\Model;

class VenueCostSnapshot extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_venue_cost_snaoshot';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = True;


}