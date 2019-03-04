<?php

namespace App\Model\ClubClass;

use Illuminate\Database\Eloquent\Model;

class ClubClassTime extends Model
{
    protected $table = 'club_class_time';

    public function class()
    {
        return $this->belongsTo('App\Model\ClubClass\ClubClass','class_id');
    }
}
