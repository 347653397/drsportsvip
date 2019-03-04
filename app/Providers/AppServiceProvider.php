<?php

namespace App\Providers;

use App\Services\Classes\ClassesService;
use App\Services\Coach\CoachService;
use App\Services\Permission\PermissionService;
use Illuminate\Support\ServiceProvider;
use App\Services\Common\CommonService;
use App\Services\Util\SmsService;
use App\Services\Subscribe\SubscribeService;
use App\Services\Student\StudentService;
use App\Services\Util\LogService;
use App\Services\Club\ClubService;
use DB;
use Illuminate\Support\Facades\App;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // 监听sql 语句
        /*DB::listen(function($sql) {
            foreach ($sql->bindings as $i => $binding)
            {
                if ($binding instanceof \DateTime) {
                    $sql->bindings[$i] = $binding->format('\'Y-m-d H:i:s\'');
                } else {
                    if (is_string($binding)) {
                        $sql->bindings[$i] = "'$binding'";
                    }
                }
            }

            $query = str_replace(array('%', '?'), array('%%', '%s'), $sql->sql);

            $query = vsprintf($query, $sql->bindings);

            echo $query, "\n\n";
        });*/

    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind('util-common', function () {
            return new CommonService();
        });

        $this->app->bind('util-sms', function () {
            return new SmsService();
        });

        $this->app->bind('stu-subscribe', function () {
            return new SubscribeService();
        });

        $this->app->bind('stu-student', function () {
            return new StudentService();
        });

        $this->app->bind('role-type', function () {
            return new PermissionService();
        });

        $this->app->bind('club-class', function () {
            return new ClassesService();
        });

        $this->app->bind('util-log', function () {
            return new LogService();
        });

        $this->app->bind('club-coach', function () {
            return new CoachService();
        });

        App::bind('App\Services\Club\IClubService', 'App\Services\Club\ClubService');
    }
}
