<?php

namespace App\Model\ClubSales;

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 16:45
 */

namespace App\Model\ClubSales;


use Illuminate\Database\Eloquent\Model;

class ClubSales extends Model
{

    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_sales';

    public function user()
    {
        return $this->belongsTo('App\Model\ClubUser\ClubUser','user_id');
    }

    /**
     * 未删除
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}
