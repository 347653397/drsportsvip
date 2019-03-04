<?php

namespace App\Model\Club;

use App\Model\Common\ClubCity;
use Illuminate\Database\Eloquent\Model;

class Club extends Model
{
    /**
     * 关联到模型的数据表
     *
     * @var string
     */
    protected $table = 'club_club';

    /**
     * 有效俱乐部
     * @param $query
     * @return mixed
     */
    public function scopeValid($query)
    {
        return $query->where('status',1);
    }

    /**
     * 俱乐部图片
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function images()
    {
        return $this->hasMany('App\Model\Club\ClubImage','club_id','id');
    }

    /**
     * 俱乐部学员
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function club_stdents()
    {
        return $this->hasMany('App\Model\ClubStudent\ClubStudent','club_id','id');
    }

    /**
     * 筛选相关运动类型的俱乐部
     * @param $query
     * @param $sportId
     * @return mixed
     */
    public function scopeSportsType($query,$sportId)
    {
        return $query->where('type',$sportId);
    }

    /**
     * 未删除俱乐部
     * @param $query
     * @return mixed
     */
    public function scopeNotDelete($query)
    {
        return $query->where('is_delete',0);
    }
}