<?php
namespace App\Facades\Util;

use Illuminate\Support\Facades\Facade;

class Sms extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'util-sms';
    }
}