<?php

namespace App\Facades\Permission;

use Illuminate\Support\Facades\Facade;

class Permission extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'role-type';
    }
}