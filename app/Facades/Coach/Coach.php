<?php

namespace App\Facades\Coach;

use Illuminate\Support\Facades\Facade;

class Coach extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'club-coach';
    }
}
