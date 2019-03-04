<?php
namespace App\Model\Recommend;

use Illuminate\Database\Eloquent\Model;

class ClubCourseRewardHistory extends Model
{
    /**
     * @var string
     */
    protected $table = 'club_course_reward_history';

    /**
     * 操作人
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function operator()
    {
        return $this->belongsTo('App\Model\ClubUser\ClubUser','operator_id');
    }
}