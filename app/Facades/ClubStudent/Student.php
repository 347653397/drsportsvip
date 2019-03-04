<?php

namespace App\Facades\ClubStudent;

use Illuminate\Support\Facades\Facade;

class Student extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'stu-student';
    }
}