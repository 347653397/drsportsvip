<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        /*if (env('APP_DEBUG')) {
            $schedule->command('SendGoToClassSms')->everyTenMinutes()->withoutOverlapping();
        } else {
            $schedule->command('SendGoToClassSms')->hourly()->withoutOverlapping();
        }*/

        // 生成课程签到数据
        $schedule->command('GenerateStuCourseSign')->dailyAt('00:00')->withoutOverlapping();

        // 每月15日教练快照
        $schedule->command('CoachLastMonthSalary')->monthlyOn(15, '08:00')->withoutOverlapping();

        // 超过2个月课时为0且未上课的学员自动失效
        $schedule->command('StudentAutoModifyStatus')->dailyAt('02:00')->withoutOverlapping();

        // 给推荐了学员买单的用户赠送奖励课时（满足赠送条件时）
        $area = env('APP_AREA');

        if ($area == 'product') {
            $schedule->command('GiveRewardCourseToRecommendStudent')->dailyAt('01:00')->withoutOverlapping();
        } else {
            $schedule->command('GiveRewardCourseToRecommendStudent')->everyFiveMinutes()->withoutOverlapping();
        }

    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
