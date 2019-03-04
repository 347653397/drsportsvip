<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/4
 * Time: 22:08
 */

namespace App\Model\ClubPayment;


use App\Model\Club\Club;
use Illuminate\Database\Eloquent\Model;

class ClubPayment extends  Model
{
    protected $table = 'club_payment';

    /**
     * 对应的班级类型
     */
    public function classType(){
        return $this->belongsTo('App\Model\ClubClassType\ClubClassType','type');
    }

    public function club()
    {
        return $this->belongsTo(Club::class,'club_id');
    }

    /**
     * 有效缴费方案
     * @param $query
     * @return mixed
     */
    public function scopeValid($query)
    {
        return $query->where('is_delete',0)->where('status',1);
    }

    /**
     * 在app端显示的缴费方案
     * @param $query
     * @return mixed
     */
    public function scopeShowInApp($query)
    {
        return $query->where('show_in_app',1);
    }

    /**
     * 定义缴费方案与缴费标签关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function paymentTag()
    {
        return $this->belongsTo('App\Model\ClubPaymentTag\ClubPaymentTag','tag');
    }
}