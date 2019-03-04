<?php

namespace App\Model\ClubCoachImage;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClubCoachImage extends Model
{
    //
    use SoftDeletes;

    protected $table = 'club_coach_image';

    protected $dates = ['deleted_at'];
}
