<?php
namespace App\Facades\Util;

use Illuminate\Support\Facades\Facade;

class Common extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'util-common';
    }
}