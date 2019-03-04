<?php

namespace App\Model\Common;

use Illuminate\Database\Eloquent\Model;

class ClubCity extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_city';

    /**
     * 区/县
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function district()
    {
        return $this->hasMany('App\Model\Common\ClubDistrict','citycode','code');
    }
}