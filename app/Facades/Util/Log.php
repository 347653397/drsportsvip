<?php
namespace App\Facades\Util;

use Illuminate\Support\Facades\Facade;

class Log extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'util-log';
    }
}