<?php

namespace App\Console\Commands;

use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubStudent\ClubStudent;
use App\Model\Permission\Permission;
use App\Model\Permission\RoleMenu;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class StudentAutoModifyStatus extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'StudentAutoModifyStatus';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'auto modify student status';

    /**
     * 生成课程签到数据
     * @return string
     * @throws \Exception
     */
    public function handle()
    {

    }
}

