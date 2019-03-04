<?php
namespace App\Model\Recommend;

use App\Model\ClubStudentPayment\ClubStudentPayment;
use Illuminate\Database\Eloquent\Model;

class ClubRecommendReserveRecord extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_recommend_reserve_record';

    /**
     * 未删除的数据
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }

    /**
     * 推荐学员信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function student()
    {
        return $this->hasOne('App\Model\ClubStudent\ClubStudent','id','new_stu_id');
    }

    /**
     * 俱乐部信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function club()
    {
        return $this->hasOne('App\Model\Club\Club','id','club_id');
    }

    /**
     * 销售
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function sales()
    {
        return $this->hasOne('App\Model\ClubSales\ClubSales','id','sale_id');
    }

    /**
     * 推广预约奖励记录
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rewards()
    {
        return $this->hasMany('App\Model\Recommend\ClubRecommendRewardRecord','recommend_id','id');
    }

    /**
     * 推荐的学员缴费方案
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function recommend_payment()
    {
        return $this->hasOne('App\Model\ClubStudentPayment\ClubStudentPayment','reserve_record_id','id');
    }
}