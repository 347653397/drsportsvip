<?php

namespace App\Model\Common;

use Illuminate\Database\Eloquent\Model;

class ClubProvince extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_province';

    /**
     * 表明模型是否应该被打上时间戳
     *
     * @var bool
     */
    public $timestamps = True;

    /**
     * 城市
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function city()
    {
        return $this->hasMany('App\Model\Common\ClubCity','provincecode','code');
    }
}