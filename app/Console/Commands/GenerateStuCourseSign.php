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

class GenerateStuCourseSign extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'GenerateStuCourseSign';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'Generating course check-in data';

    /**
     * 生成课程签到数据
     * @return string
     * @throws \Exception
     */
    public function handle()
    {
        $today = Carbon::now()->format('Y-m-d');

        // 今日所有课程
        $todayCourse = ClubCourse::where('day', $today)
            ->where('status', 1)
            ->where('is_delete', 0)
            ->get();
        if ($todayCourse->isEmpty()) {
            throw new \Exception(config('今日无课！'));
        }

        $todayCourse = $todayCourse->toArray();
        foreach ($todayCourse as $key => $value) {
            $class = ClubClass::find($value['class_id']);

            // 每节课上课的学员
            $stuClass = ClubClassStudent::where('class_id', $value['class_id'])
                ->where('is_delete', 0)
                ->get();

            if ($stuClass->isEmpty()) continue;

            DB::transaction(function () use ($stuClass, $value, $class) {
                foreach ($stuClass->toArray() as $k => $v) {
                    // 学员不存在不生成签到
                    $student = ClubStudent::find($v['student_id']);
                    if (empty($student)) continue;

                    // 已冻结学员不生成签到
                    if ($student->is_freeze == 1) continue;

                    // 非正式学员不生成签到
                    // if ($student->status == 2 || $student->status == 3) continue;

                    // 与班级同类型课程券不足
                    $ticketCount = ClubCourseTickets::where('club_id', $student->club_id)
                        ->where('class_type_id', $class['type'])
                        ->where('student_id', $student->id)
                        ->count();
                    if ($ticketCount <= 0) continue;

                    // 已存在签到不生成签到
                    $isHaveSign = ClubCourseSign::where('club_id', $student->club_id)
                        ->where('class_id', $value['class_id'])
                        ->where('course_id', $value['id'])
                        ->where('student_id', $student->id)
                        ->exists();
                    if ($isHaveSign === true) continue;

                    $courseSign = new ClubCourseSign();
                    $courseSign->club_id = $value['club_id'];
                    $courseSign->class_id = $value['class_id'];
                    $courseSign->course_id = $value['id'];
                    $courseSign->course_day = $value['day'];
                    $courseSign->student_id = $v['student_id'];
                    $courseSign->class_type_id = $value['class_type_id'];
                    $courseSign->save();
                }
            });
        }

        unset($courses);

        return "success";
    }
}

