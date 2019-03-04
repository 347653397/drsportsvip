<?php

namespace App\Model\Venue;

use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_venue';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = True;


}