<?php

namespace App\Facades\Classes;

use Illuminate\Support\Facades\Facade;

class Classes extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'club-class';
    }
}