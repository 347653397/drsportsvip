<?php

namespace App\Model\ClubCoach;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ClubCoach extends Model
{
    protected $appends = ['validPerson'];

    /**
     * @var string
     */
    protected $table = 'club_coach';

    /**
     * 教练对应的惩奖
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function reward(){
        return $this->hasMany('App\Model\ClubCoachRewardPenalty\ClubCoachRewardPenalty','coach_id');
    }

    /**
     * 教练对应的用户
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(){
        return $this->belongsTo('App\Model\ClubUser\ClubUser','user_id','id');
    }

    /**
     * 教练对应的课程编号
     */
    public function course(){
        return $this->hasMany('App\Model\ClubCourse\ClubCourse','coach_id');
    }

    /**
     * 教练对应的班级
     */
    public function classes(){
        return $this->belongsToMany('App\Model\ClubClass\ClubClass','club_course','coach_id','class_id');
    }

    /**
     * 教练对应的快照表
     */
    public function cost_snapshot(){
        return $this->hasMany('App\Model\ClubCoachCostSnapshot\ClubCoachCostSnapshot','coach_id');
    }
}
