<?php

namespace App\Model\ClubCoachVideo;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubCoachVideo extends Model
{
    //
    use SoftDeletes;

    protected $table = 'club_coach_video';

    protected $dates = ['deleted_at'];
}
