<?php
namespace App\Facades\ClubStudent;

use Illuminate\Support\Facades\Facade;

class Subscribe extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'stu-subscribe';
    }
}