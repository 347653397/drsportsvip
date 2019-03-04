<?php

namespace  App\Model\ClubCoachRewardPenalty;

use Illuminate\Database\Eloquent\Model;

class ClubCoachRewardPenalty extends Model
{
    //
    protected $table = 'club_coach_reward_penalty';
    /**
     * 奖惩对应的教练
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function coach(){
        return $this->belongsTo('App\ClubCoach','coach_id');
    }



}
