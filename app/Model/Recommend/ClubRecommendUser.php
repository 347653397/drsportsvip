<?php
namespace App\Model\Recommend;

use Illuminate\Database\Eloquent\Model;

class ClubRecommendUser extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_recommend_user';

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
        return $this->hasOne('App\Model\ClubStudent\ClubStudent','id','student_id');
    }

    /**
     * 俱乐部信息
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function club()
    {
        return $this->hasOne('App\Model\Club\Club','id','club_id');
    }
}