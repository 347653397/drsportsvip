<?php

namespace App\Model\ClubCoachCostSnapshot;

use Illuminate\Database\Eloquent\Model;

class ClubCoachCostSnapshot extends Model
{
    /**
     * 关联的数据表
     * @var string
     */
    protected  $table = 'club_coach_cost_snapshot';

    /**
     * 定义月快照教练关系
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function coach()
    {
        return $this->belongsTo('App\Model\ClubCoach\ClubCoach', 'coach_id');
    }
}
