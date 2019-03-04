<?php

namespace App\Services\Classes;

use App\Facades\Util\Log;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClassTime;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentFreeze\ClubStudentFreeze;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubVenue\ClubVenue;
use Carbon\Carbon;
use App\Model\ClubCourseSignRecord\ClubCourseSignRecord;

class ClassesService
{
    /**
     * 统计班级数量
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return int
     */
    public function getClassCount($clubId, $venueId, $classId)
    {
        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $count = Club::where('id', $clubId)
                ->value('class_count');
        }
        elseif (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $count = ClubVenue::where('id', $venueId)
                ->value('class_count');
        }
        else {
            $count = 1;
        }

        return $count;
    }

    /**
     * 统计有效班级数
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return int
     */
    public function getEffectClassCount($clubId, $venueId, $classId)
    {
        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $count = Club::where('id', $clubId)
                ->value('effect_class_count');
        }
        elseif (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $count = ClubVenue::where('id', $venueId)
                ->value('effect_class_count');
        }
        else {
            $count = 1;
        }

        return $count;
    }

    /**
     * 统计失效班级数
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return int
     */
    public function getNotEffectClassCount($clubId, $venueId, $classId)
    {
        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $count = Club::where('id', $clubId)
                ->value('not_effect_class_count');
        }
        elseif (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $count = ClubVenue::where('id', $venueId)
                ->value('not_effect_class_count');
        }
        else {
            $count = 1;
        }

        return $count;
    }

    /**
     * 统计学员数量
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return mixed
     */
    public function getStudentCount($clubId, $venueId, $classId)
    {
        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $count = ClubStudent::where('club_id', $clubId)->count();
        }
        elseif (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $count = ClubStudent::where('venue_id', $clubId)->count();
        }
        if (!empty($clubId) && !empty($venueId) && !empty($classId)) {
            $count = ClubClassStudent::where('class_id', $classId)->count();
        }

        return $count;
    }

    /**
     * 统计活跃学员数
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return mixed
     */
    public function getActiveStudentCount($clubId, $venueId, $classId)
    {
        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $count = ClubStudent::where('club_id', $clubId)
                ->where('status', 1)
                ->where('is_freeze', 0)
                ->count();
        }
        if (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $count = ClubStudent::where('venue_id', $venueId)
                ->where('status', 1)
                ->where('is_freeze', 0)
                ->count();
        }
        if (!empty($clubId) && !empty($venueId) && !empty($classId)) {
            $stuIds = ClubClassStudent::where('class_id', $classId)
                ->distinct()
                ->pluck('student_id')
                ->toArray();

            $count = ClubStudent::whereIn('id', $stuIds)
                ->where('status', 1)
                ->where('is_freeze', 0)
                ->count();
        }

        return $count;
    }

    /**
     * 统计冻结学员数
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return mixed
     */
    public function getFreezeStudentCount($clubId, $venueId, $classId)
    {
        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $count = ClubStudent::where('club_id', $clubId)
                ->where('status', 1)
                ->where('is_freeze', 0)
                ->count();
        }
        if (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $count = ClubStudent::where('venue_id', $venueId)
                ->where('status', 1)
                ->where('is_freeze', 0)
                ->count();
        }
        if (!empty($clubId) && !empty($venueId) && !empty($classId)) {
            $stuIds = $this->getOneClassAllStuId($classId);

            $count = ClubStudent::whereIn('id', $stuIds)
                ->where('status', 1)
                ->where('is_freeze', 0)
                ->count();
        }

        return $count;
    }

    /**
     * 通过时间获取班级
     * @param $classId
     * @param $clubId
     * @return ClubClassTime[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getClassByTime($classId, $clubId)
    {
        $result = ClubClassTime::with('class')
            ->where('class_id', $classId)
            ->get();
        // 班级下所有学员id
        $stuIds = $this->getOneClassAllStuId($classId);

        $list = $result->transform(function ($items) use ($stuIds, $clubId, $classId) {
            $arr['className'] = $items->class->name;
            $arr['venueName'] = ClubVenue::where('id', $items->class->venue_id)->value('name');
            $arr['startTime'] = $this->packageClassTime($items->day, $items->start_time, $items->end_time);
            $arr['activeCount'] = $this->getActiveStudentCount($clubId, $items->class->venue_id, $classId);
            $arr['freezeCount'] = ClubStudent::where('is_freeze', 1)->where('club_id', $clubId)->whereIn('id', $stuIds)->count();
            $arr['maxStudent'] = $items->class->student_limit;
            $arr['showInApp'] = $items->class->show_in_app;
            $arr['paymentName'] = $items->class->pay_tag_name;
            $arr['activeAge'] = $this->activeAgeRange($stuIds);
            return $arr;
        });
        return $list;
    }

    /**
     * 获取班级上课时间
     * @param $classId
     * @param $clubId
     * @return ClubClassTime[]|\Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public function getClassSelectTime($classId, $clubId)
    {
        $result = ClubClassTime::with('class')
            ->where('class_id', $classId)
            ->get();
        // 班级下所有学员id
        $stuIds = $this->getOneClassAllStuId($classId);

        $list = $result->transform(function ($items) use ($stuIds, $clubId, $classId) {
            $arr['classTime'] = $this->packageClassTime($items->day, $items->start_time, $items->end_time).' '.$this->activeAgeRange($stuIds);
            return $arr;
        });
        return $list;
    }

    /**
     * 获取班级上课时间段
     * @param $day
     * @param $startTime
     * @param $endTime
     * @return string
     */
    public function packageClassTime($day, $startTime, $endTime)
    {
        $week = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
        return $week[$day-1] . ' ' . $startTime . '-' .$endTime;
    }

    /**
     * 获取班级最小最大年龄段
     * @param $studentIdArr
     * @return string
     */
    public function activeAgeRange($studentIdArr)
    {
        $minBirth = ClubStudent::whereIn('id', $studentIdArr)
            ->where('status', 1)
            ->min('birthday');
        $maxBirth = ClubStudent::whereIn('id', $studentIdArr)
            ->where('status', 1)
            ->max('birthday');
        $minAge = Carbon::parse($maxBirth)->diffInYears();
        $maxAge = Carbon::parse($minBirth)->diffInYears();
        return $minAge .'岁' . ' ~ ' . $maxAge . '岁';
    }

    /**
     * 获取班级上课时间
     * @param $id
     * @return mixed
     */
    public function classStartTime($id)
    {
        $classTime = ClubClassTime::where('class_id', $id)->get();
        $data = $classTime->transform(function ($items) {
            $arr['day'] = $items->day;
            $arr['startTime'] = $items->start_time;
            $arr['endTime'] = $items->end_time;
            return $arr;
        });
        return $data;
    }

    /**
     * 获取班级下所有学员
     * @param $classId
     * @return mixed
     */
    public function getOneClassAllStuId($classId)
    {
        $stuIds = ClubClassStudent::where('class_id', $classId)
            ->where('is_delete', 0)
            ->distinct()
            ->pluck('student_id')
            ->toArray();

        return $stuIds;
    }

    /**
     * 统计班级上次出勤总数
     * @param $clubId
     * @param $venueId
     * @param $classId
     * @return mixed
     */
    public function getLastStuAttendanceTotalNum($clubId, $venueId, $classId)
    {
        $lastDate = Carbon::now()->subWeek()->format('Y-m-d');

        Log::setGroup('StuSignError')->error('统计班级上次出勤总数', ['lastDate' => $lastDate]);

        if (!empty($clubId) && empty($venueId) && empty($classId)) {
            $CourseId = ClubCourse::where('club_id', $clubId)
                ->where('day', $lastDate)
                ->where('is_delete', 0)
                ->distinct()
                ->pluck('id')
                ->toArray();
        }

        if (!empty($clubId) && !empty($venueId) && empty($classId)) {
            $CourseId = ClubCourse::where('club_id', $clubId)
                ->where('day', $lastDate)
                ->where('venue_id', $venueId)
                ->where('is_delete', 0)
                ->distinct()
                ->pluck('id')
                ->toArray();
        }

        if (!empty($clubId) && !empty($venueId) && !empty($classId)) {
            $CourseId = ClubCourse::where('club_id', $clubId)
                ->where('day', $lastDate)
                ->where('venue_id', $venueId)
                ->where('class_id', $classId)
                ->where('is_delete', 0)
                ->distinct()
                ->pluck('id')
                ->toArray();
        }

        $count = ClubCourseSign::whereIn('course_id', $CourseId)
            ->where('sign_status', 1)
            ->count();

        return $count;
    }

    /**
     * 某个课程上次出勤数
     * @param $classId
     * @return mixed
     */
    public function getClassLastAttendanceNum($courseDay,$classId)
    {
        $lastDate = Carbon::parse()->subWeek()->format('Y-m-d');

        $CourseId = ClubCourse::where('class_id', $classId)
            ->where('day', $lastDate)
            ->where('is_delete', 0)
            ->distinct()
            ->pluck('id')
            ->toArray();

        $count = ClubCourseSign::whereIn('course_id', $CourseId)
            ->where('sign_status', 1)
            ->count();

        return $count;
    }

    /**
     * 体验券
     * @param $clubId
     * @param $studentId
     * @return mixed
     */
    public function getSubscribeExTicket($clubId, $studentId)
    {
        $freePayment = ClubPayment::where('club_id', $clubId)
            ->where('tag', 1)
            ->where('is_free', 1)
            ->where('is_default', 1)
            ->first();

        Log::setGroup('StuSignError')->error('预约体验券', ['clubId' => $clubId,'studentId' => $studentId, 'freePayment' => $freePayment]);

        $stuPay = ClubStudentPayment::where('club_id', $clubId)
            ->where('payment_id', $freePayment->id)
            ->where('student_id', $studentId)
            ->first();

        Log::setGroup('StuSignError')->error('预约体验券', ['stuPay' => $stuPay]);

        $ticket = ClubCourseTickets::where('club_id', $clubId)
            ->where('payment_id', $stuPay->id)
            ->where('student_id', $studentId)
            ->where('status', 2)
            ->first();

        Log::setGroup('StuSignError')->error('预约体验券', ['ticket' => $ticket]);

        return $ticket;
    }

    /**
     * 正常签到课程券
     * @param $clubId
     * @param $studentId
     * @param $classType
     * @return mixed
     */
    public function getStudentSignTicket($clubId, $studentId, $classType)
    {
        $stuPayArr = ClubStudentPayment::where('club_id', $clubId)
            ->where('payment_tag_id', 1)
            ->where('student_id', $studentId)
            ->where('payment_class_type_id', $classType)
            ->pluck('id')
            ->toArray();

        Log::setGroup('StuSignError')->error('体验券', ['stuPayArr' => $stuPayArr]);

        $ticket = ClubCourseTickets::where('club_id', $clubId)
            ->whereIn('payment_id', $stuPayArr)
            ->where('student_id', $studentId)
            ->where('status', 2)
            ->first();

        Log::setGroup('StuSignError')->error('体验券', ['ticket' => $ticket]);

        if (empty($ticket)) {
            $stuPayArr = ClubStudentPayment::where('club_id', $clubId)
                ->where('payment_tag_id', 2)
                ->where('student_id', $studentId)
                ->where('payment_class_type_id', $classType)
                ->pluck('id')
                ->toArray();

            Log::setGroup('StuSignError')->error('正式券', ['stuPayArr' => $stuPayArr]);

            $ticket = ClubCourseTickets::where('club_id', $clubId)
                ->whereIn('payment_id', $stuPayArr)
                ->where('student_id', $studentId)
                ->where('status', 2)
                ->first();

            Log::setGroup('StuSignError')->error('正式券', ['ticket' => $ticket]);

            if (empty($ticket)) {
                $stuPayArr = ClubStudentPayment::where('club_id', $clubId)
                    ->where('payment_tag_id', 3)
                    ->where('student_id', $studentId)
                    ->where('payment_class_type_id', $classType)
                    ->pluck('id')
                    ->toArray();

                Log::setGroup('StuSignError')->error('赠送券', ['stuPayArr' => $stuPayArr]);

                $ticket = ClubCourseTickets::where('club_id', $clubId)
                    ->whereIn('payment_id', $stuPayArr)
                    ->where('student_id', $studentId)
                    ->where('status', 2)
                    ->first();

                Log::setGroup('StuSignError')->error('赠送券', ['ticket' => $ticket]);
            }
        }

        return $ticket;
    }

    /**
     * 添加签到修改记录
     * @param $clubId
     * @param $signId
     * @param $stuId
     * @param $status
     * @param $userId
     * @throws \Throwable
     */
    public function recordStuCourseSign($clubId, $signId, $stuId, $status, $userId)
    {
        $record = new ClubCourseSignRecord();
        $record->club_id = $clubId;
        $record->sign_id = $signId;
        $record->student_id = $stuId;
        $record->sign_status = $status;
        $record->sign_date = Carbon::now()->format('Y-m-d H:i:s');
        $record->operate_user_id = $userId;
        $record->saveOrFail();
    }

    /**
     * 学员最近四次签到记录
     * @param $signId
     * @return array
     */
    public function stuFourTimesAgoSign($signId)
    {
        $record = ClubCourseSignRecord::with('student', 'user')
            ->where('sign_id', $signId)
            ->where('is_delete', 0)
            ->orderBy('sign_date', 'desc')
            ->offset(0)
            ->limit(4)
            ->get();

        $list = $record->transform(function ($items) {
            $arr['signDatetime'] = $items->sign_date;
            $arr['studentName'] = isset($items->student->name) ? $items->student->name : '';
            $arr['signStatus'] = $items->sign_status;
            $arr['operationUser'] = isset($items->user->username) ? $items->user->username : '';
            return $arr;
        });

        return $list;
    }

    /**
     * 解冻签出勤冻结学员
     * @param $stuId
     * @param $freezeId
     */
    public function stuSignAutoUnfreeze($stuId, $freezeId)
    {
        ClubStudent::where('id', $stuId)
            ->update(['is_freeze' => 0]);

        ClubStudentFreeze::where('id', $freezeId)
            ->update(['freeze_end_date' => Carbon::now()->format('Y-m-d')]);
    }
}