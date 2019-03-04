<?php

namespace App\Api\Controllers\Classes;

use App\Facades\Classes\Classes;
use App\Facades\Util\Common;
use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassImage\ClubClassImage;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubClassTeacher\Teacher;
use App\Model\ClubClassTime\ClubClassTime;
use App\Model\ClubClassType\ClubClassType;
use App\Model\ClubClassVenueCost\ClubClassVenueCost;
use App\Model\ClubCoach\ClubCoach;
use App\Model\ClubCoachCostByCourse\ClubCoachCostByCourse;
use App\Model\ClubCoachManageCost\ClubCoachManageCost;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourseCoach\ClubCourseCoach;
use App\Model\ClubCourseImage\ClubCourseImage;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Model\ClubCourseSignSickImage\ClubCourseSignSickImage;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubIncomeSnapshot\ClubIncomeSnapshot;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;
use App\Model\ClubVenue\ClubVenue;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\Recommend\ClubRecommendRewardRecord;
use App\Services\Common\CommonService;
use App\Services\Subscribe\SubscribeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use function Sodium\increment;
use Exception;
use App\Facades\Util\Log;
use App\Model\Recommend\ClubCourseReward;
use App\Facades\ClubStudent\Student;

class ClassesController extends Controller
{
    /**
     * @var SubscribeService
     */
    public $subscribe;

    private $common;

    /**
     * ClassesController constructor.
     * @param SubscribeService $subscribeService
     */
    public function __construct(SubscribeService $subscribeService, CommonService $common)
    {
        $this->subscribe = $subscribeService;
        $this->common = $common;
    }

    /**
     * 新增班级
     * @param Request $request
     * @return array
     */
    public function addClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'className' => 'required|string',
            'classType' => 'required|numeric',
            'paymentTag' => 'required|string',
            'venueId' => 'required|numeric',
            'teacherId' => 'required|numeric',
            'maxStudent' => 'required|numeric',
            'classTime' => 'required|string',
            'remark' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $club = Club::find($input['user']['club_id']);
        $venue = ClubVenue::find($input['venueId']);

        // 同一场馆下班级不能重复添加
        $classBool = ClubClass::where('venue_id', $input['venueId'])
            ->where('name', $input['className'])
            ->where('club_id', $input['user']['club_id'])
            ->exists();
        if ($classBool === true) {
            return returnMessage('1421', config('error.class.1421'));
        }

        $remark = isset($input['remark']) ? $input['remark'] : '';
        $timeArr = json_decode($input['classTime'], true);
        $insertId = DB::transaction(function () use ($input, $timeArr, $remark, $club, $venue) {
            // 添加班级信息
            $insert['club_id'] = $input['user']['club_id'];
            $insert['name'] = $input['className'];
            $insert['type'] = $input['classType'];
            $insert['pay_tag_name'] = $input['paymentTag'];
            $insert['venue_id'] = $input['venueId'];
            $insert['venue_name'] = ClubVenue::where('id', $input['venueId'])->value('name');
            $insert['student_limit'] = $input['maxStudent'];
            $insert['teacher_id'] = $input['teacherId'];
            $insert['remark'] = $remark;
            $insert['created_at'] = date('Y-m-d H:i:s');
            $insert['updated_at'] = date('Y-m-d H:i:s');
            $classId = ClubClass::insertGetId($insert);

            // 添加班主任
            $teacher = new Teacher();
            $teacher->club_id = $input['user']['club_id'];
            $teacher->venue_id = $input['venueId'];
            $teacher->class_id = $classId;
            $teacher->teacher_id = $input['teacherId'];
            $teacher->teacher_name = ClubSales::where('id', $input['teacherId'])->value('sales_name');
            $teacher->status = 1;
            $teacher->save();

            // 添加上课时间
            foreach ($timeArr as $value) {
                $clubClassTime = new ClubClassTime();
                $clubClassTime->class_id = $classId;
                $clubClassTime->day = $value['day'];
                $clubClassTime->start_time = $value['startTime'];
                $clubClassTime->end_time = $value['endTime'];
                $clubClassTime->save();
            }

            // 增加俱乐部班级数
            $club->class_count = $club->class_count + 1;
            $club->effect_class_count = $club->effect_class_count + 1;
            $club->save();

            // 增加场馆班级数
            $venue->class_count = $venue->class_count + 1;
            $venue->effect_class_count = $venue->effect_class_count + 1;
            $venue->save();

            return $classId;
        });

        $class = ClubClass::find($insertId);
        $arr = Classes::getClassByTime($insertId, $input['user']['club_id']);
        $arr['status'] = $class->status;
        return returnMessage('200', '', $arr);
    }

    /**
     * 修改班级-数据
     * @param Request $request
     * @return array
     */
    public function editClassData(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $class = ClubClass::find($input['id']);
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $result['classId'] = $class->id;
        $result['className'] = $class->name;
        $result['classType'] = $class->type;
        $result['paymentTag'] = $class->pay_tag_name;
        $result['venueId'] = $class->venue_id;
        $result['teacherId'] = $class->teacher_id;
        $result['maxStudent'] = $class->student_limit;
        $result['classTime'] = Classes::classStartTime($input['id']);
        $result['remark'] = $class->remark;
        return returnMessage('200', '', $result);
    }

    /**
     * 修改班级
     * @param Request $request
     * @return array
     */
    public function editClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'classId' => 'required|numeric',
            'className' => 'required|string',
            'classType' => 'required|numeric',
            'paymentTag' => 'required|string',
            'venueId' => 'required|numeric',
            'teacherId' => 'required|numeric',
            'maxStudent' => 'required|numeric',
            'classTime' => 'required|string',
            'remark' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 班级不存在
        $class = ClubClass::find($input['classId']);
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        // 同一场馆下班级不能重复添加
        $classBool = ClubClass::where('venue_id', $input['venueId'])
            ->where('name', $input['className'])
            ->where('club_id', $input['user']['club_id'])
            ->where('id', '<>', $input['classId'])
            ->exists();
        if ($classBool === true) {
            return returnMessage('1421', config('error.class.1421'));
        }

        $remark = isset($input['remark']) ? $input['remark'] : '';
        $timeArr = json_decode($input['classTime'], true);
        DB::transaction(function () use ($input, $timeArr, $remark, $class) {
            // 修改班级信息
            $class->name = $input['className'];
            $class->type = $input['classType'];
            $class->pay_tag_name = $input['paymentTag'];
            $class->venue_id = $input['venueId'];
            $class->venue_name = ClubVenue::where('id', $input['venueId'])->value('name');
            $class->student_limit = $input['maxStudent'];
            $class->teacher_id = $input['teacherId'];
            $class->remark = $remark;
            $class->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $class->save();

            // 先删除，再修改
            ClubClassTime::where('class_id', $input['classId'])->delete();

            // 修改上课时间
            foreach ($timeArr as $value) {
                $clubClassTime = new ClubClassTime();
                $clubClassTime->class_id = $input['classId'];
                $clubClassTime->day = $value['day'];
                $clubClassTime->start_time = $value['startTime'];
                $clubClassTime->end_time = $value['endTime'];
                $clubClassTime->save();
            }

            // 更新场馆班级数
            if ($class->venue_id != $input['venueId']) {
                ClubVenue::where('id', $input['venueId'])
                    ->increment('effect_class_count');

                ClubVenue::where('id', $class->venue_id)
                    ->decrement('effect_class_count');
            }
        });
        return returnMessage('200', '');
    }

    /**
     * 班级列表
     * @param Request $request
     * @return array
     */
    public function classList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'searchVal' => 'nullable|string',
            'venueId' => 'nullable|numeric',
            'classId' => 'nullable|numeric',
            'status' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $searchVal = isset($input['searchVal']) ? $input['searchVal'] : '';
        $venueId = isset($input['venueId']) ? $input['venueId'] : 0;
        $classId = isset($input['classId']) ? $input['classId'] : 0;
        $status = isset($input['status']) ? $input['status'] : 0;

        // 班级列表
        $class = ClubClass::with('time')
            ->where('club_id', $input['user']['club_id'])
            ->where(function ($query) use ($searchVal) {
                if (!empty($searchVal)) {
                    $query->where('id', $searchVal)->orWhere('name', 'like', '%'.$searchVal.'%');
                }
            })
            ->where(function ($query) use ($venueId, $classId) {
                if (!empty($venueId) && empty($classId)) {
                    $query->where('venue_id', $venueId);
                }
            })
            ->where(function ($query) use ($venueId, $classId) {
                if (!empty($venueId) && !empty($classId)) {
                    $query->where('venue_id', $venueId)->where('id', $classId);
                }
            })
            ->where(function ($query) use ($status) {
                if (!empty($status)) {
                    $query->where('status', $status);
                }
            })
            ->where('is_delete', 0)
            ->orderBy('show_in_app', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['classCount'] = Classes::getClassCount($input['user']['club_id'], $venueId, $classId);
        $list['effectClassCount'] = Classes::getEffectClassCount($input['user']['club_id'], $venueId, $classId);
        $list['notEffectClassCount'] = Classes::getNotEffectClassCount($input['user']['club_id'], $venueId, $classId);
        $list['studentCount'] = Classes::getStudentCount($input['user']['club_id'], $venueId, $classId);
        $list['activeStudentCount'] = Classes::getActiveStudentCount($input['user']['club_id'], $venueId, $classId);
        $list['totalNum'] = $class->total();
        $list['result'] = $class->transform(function ($items) use ($input) {
            $arr['classId'] = $items->id;
            $arr['status'] = $items->status;
            $arr['list'] = Classes::getClassByTime($items->id, $input['user']['club_id']);
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 班级生效/失效
     * @param Request $request
     * @return array
     */
    public function modifyClassStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 班级不存在，无法删除
        $clubClass = ClubClass::find($input['id']);
        if (empty($clubClass)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        // 关联学员无法删除，必须先转移
        $stuCount = ClubClassStudent::where('club_id', $input['user']['club_id'])
            ->where('class_id', $input['id'])
            ->where('is_delete', 0)
            ->count();
        if ($stuCount > 0) {
            return returnMessage('1434', config('error.class.1434'));
        }

        $club = Club::find($input['user']['club_id']);
        $venue = ClubVenue::find($clubClass->venue_id);

        DB::transaction(function () use ($input, $clubClass, $club, $venue) {
            $clubClass->status = $input['type'];
            $clubClass->save();

            // 更新班级数
            if ($input['type'] == 0) {
                // 课程失效
                ClubCourse::where('class_id', $input['id'])
                    ->update(['status' => 0]);

                $club->effect_class_count = $club->effect_class_count - 1;
                $club->not_effect_class_count = $club->not_effect_class_count + 1;
                $club->save();

                $venue->effect_class_count = $venue->effect_class_count - 1;
                $venue->not_effect_class_count = $venue->not_effect_class_count + 1;
                $venue->save();
            }
            else {
                $club->effect_class_count = $club->effect_class_count + 1;
                $club->not_effect_class_count = $club->not_effect_class_count - 1;
                $club->save();

                $venue->effect_class_count = $venue->effect_class_count + 1;
                $venue->not_effect_class_count = $venue->not_effect_class_count - 1;
                $venue->save();
            }
        });
        return returnMessage('200', '');
    }

    /**
     * 删除班级
     * @param Request $request
     * @return array
     */
    public function deleteClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $clubClass = ClubClass::find($input['id']);
        // 班级不存在
        if (empty($clubClass)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        // 班级存在学员无法删除
        $studentNum = ClubClassStudent::where('class_id', $input['id'])->count();
        if ($studentNum > 0) {
            return returnMessage('1430', config('error.class.1430'));
        }

        // 班级存在课程无法删除
        $courseNum = ClubCourse::where('class_id', $input['id'])->count();
        if ($courseNum > 0) {
            return returnMessage('1404', config('error.class.1404'));
        }

        $club = Club::find($input['user']['club_id']);
        $venue = ClubVenue::find($clubClass->venue_id);

        DB::transaction(function () use ($clubClass, $input, $club, $venue) {
            $clubClass->is_delete = 1;
            $clubClass->save();

            // 更新班级数
            if ($clubClass->status == 1) {
                $club->class_count = $club->class_count - 1;
                $club->effect_class_count = $club->effect_class_count - 1;
                $club->save();
            }
            else {
                $club->class_count = $club->class_count - 1;
                $club->not_effect_class_count = $club->not_effect_class_count - 1;
                $club->save();
            }
        });

        return returnMessage('200', '');
    }

    /**
     * 班主任报告
     * @param Request $request
     * @return array
     */
    public function classTeacherReport(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'nullable|numeric',
            'classId' => 'nullable|numeric',
            'startDate' => 'nullable|date:Y-m-d',
            'endDate' => 'nullable|date:Y-m-d',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $clubId = $input['user']['club_id'];
        $venueId = isset($input['venueId']) ? $input['venueId'] : 0;
        $classId = isset($input['classId']) ? $input['classId'] : 0;
        $startDate = isset($input['startDate']) ? $input['startDate'] : "";
        $endDate = isset($input['endDate']) ? $input['endDate'] : "";

        // 开始时间不能大于结束时间
        if (strtotime($startDate) > strtotime($endDate)) {
            return returnMessage('1048', config('error.class.1048'));
        }

        // 场馆总数
        $list['venueNum'] = ClubVenue::where('club_id', $clubId)->count();
        // 班主任总数
        $list['teacherNum'] = Teacher::where('club_id', $clubId)->count();
        // 活跃学员总数
        $list['activeStudentNum'] = Classes::getActiveStudentCount($clubId, $venueId, $classId);
        // 上次出勤总数
        $list['lastAttendanceNum'] = Classes::getLastStuAttendanceTotalNum($clubId, $venueId, $classId);
        // 总招生上限
        $list['maxStudent'] = ClubClass::where('club_id', $clubId)->where('is_delete', 0)->sum('student_limit');
        // 总可招生数
        $list['enrolmentNum'] = $list['maxStudent'] - $list['activeStudentNum'];
        if ($list['enrolmentNum'] < 0) {
            $list['enrolmentNum'] = 0;
        }
        // 满班率
        if (empty($activeStudentNum) || empty($maxStudent)) {
            $list['fullClassRate'] = 0;
        } else {
            $list['fullClassRate'] = $list['activeStudentNum']/$list['maxStudent'] * 100;
            $list['fullClassRate'] = number_format($list['fullClassRate'], 2, '.', '');
        }
        // 冻结学员总数
        $list['freezeStudentNum'] = Classes::getFreezeStudentCount($clubId, $venueId, $classId);
        // 最小年龄
        $minBirthday = ClubStudent::where('club_id', $clubId)->max('birthday');
        $mostSmallAge = Carbon::parse()->diffInYears($minBirthday);
        $list['mostSmallAge'] = !empty($mostSmallAge) ? $mostSmallAge : 0;
        // 最大年龄
        $maxBirthday = ClubStudent::where('club_id', $clubId)->min('birthday');
        $mostBigAge = Carbon::parse()->diffInYears($maxBirthday);
        $list['mostBigAge'] = !empty($mostBigAge) ? $mostBigAge : 0;

        // 班级总数
        $list['classCount'] = ClubCourse::where('club_id', $clubId)->count();

        // 班级数据
        $class = ClubCourse::with('venue', 'class', 'course_sign')
            ->where('club_id', $clubId)
            ->screen($venueId, $classId)
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        // 处理结果集
        $list['totalNum'] = $class->total();
        $list['result']['list'] = $class->transform(function ($items) use ($clubId) {
            $arr['classId'] = $items->class_id;
            $arr['courseTime'] = Classes::packageClassTime(Carbon::parse($items->day)->dayOfWeekIso, $items->start_time, $items->end_time);
            $arr['venueName'] = !empty($items->venue) ? $items->venue->name : "";
            $arr['teacherName'] = !empty($items->class) ? ClubSales::where('id', $items->class->teacher_id)->value('sales_name') : "";
            // 班级活跃学员数
            $arr['activeStudentNum'] = Classes::getStudentCount($clubId, $items->class->venue_id, $items->class_id);
            // 上次出勤学员数
            $arr['lastAttendanceNum'] = Classes::getClassLastAttendanceNum($items->day, $items->class_id);
            // 班级招生上限
            $arr['maxStudent'] = $items->class->student_limit;
            // 班级可招生数
            $arr['enrolmentNum'] = $items->class->student_limit - $items->class->active_student_count;
            // 班级满班率
            if (empty($arr['activeStudentNum']) || empty($arr['maxStudent'])) {
                $arr['fullClassRate'] = 0;
            } else {
                $arr['fullClassRate'] = ($arr['activeStudentNum']/$arr['maxStudent']) * 100;
                $arr['fullClassRate'] = number_format($arr['fullClassRate'], 2, '.', '');
            }
            // 冻结学员数
            $arr['freezeStudentNum'] = Classes::getFreezeStudentCount($clubId, $items->class->venue_id, $items->class_id);
            // 年龄范围
            $ageRange = Common::getClassStudentMinAndMaxAge($items->class_id);
            $arr['mostSmallAge'] = $ageRange['min'];
            $arr['mostBigAge'] = $ageRange['max'];
            $arr['remark'] = ClubClass::where('id', $items->class_id)->value('remark');
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 班级概况
     * @param Request $request
     * @return array
     */
    public function classProfile(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 班级信息
        $class = ClubClass::find($input['id']);
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }
        // 班级下所有学员id
        $studentIds = ClubClassStudent::where('class_id', $input['id'])
            ->pluck('student_id')
            ->toArray();
        $studentIds = array_unique($studentIds);
        // 总开课数
        $openedCourseTotal = ClubCourse::where('class_id', $input['id'])->count();
        // 结款次数
        $endFundsTimes = ClubClassVenueCost::where('class_id', $input['id'])->count();
        // 签到收入
        $signIncomeTotal = ClubIncomeSnapshot::where('class_id', $input['id'])
            ->where('is_delete', 0)
            ->sum('money');
        // 盈利
        $expenditure = ClubCoachCostByCourse::where('class_id', $input['id'])->sum('coach_manage_cost');
        $profitTotal = $signIncomeTotal - $expenditure;
        // 有效学员
        $effectStudent = ClubStudent::whereIn('id', $studentIds)
            ->whereIn('status', [1, 2])
            ->count();
        // 失效学员
        $notEffectStudent = ClubStudent::whereIn('id', $studentIds)
            ->where('status', 3)
            ->count();
        // 冻结学员
        $freezeStudent = ClubStudent::where('status', 1)
            ->whereIn('id', $studentIds)
            ->where('is_freeze', 1)
            ->count();
        // 活跃学员
        $activeStudent = ClubStudent::where('status', 1)
            ->whereIn('id', $studentIds)
            ->where('is_freeze', 0)
            ->count();
        // 班级时间
        $classTime = ClubClassTime::with('class')
            ->where('class_id', $input['id'])
            ->get();
        // 班级图片
        $classImg = ClubClassImage::where('class_id', $input['id'])->where('is_delete', 0)->get();
        // 活跃学员最小年龄和最大年龄
        $activeMaxBirth = ClubStudent::where('status', 1)
            ->whereIn('id', $studentIds)
            ->where('is_freeze', 0)
            ->max('birthday');
        $activeMinBirth = ClubStudent::where('status', 1)
            ->whereIn('id', $studentIds)
            ->where('is_freeze', 0)
            ->min('birthday');
        $activeMinAge = Carbon::parse($activeMaxBirth)->diffInYears();
        $activeMaxAge = Carbon::parse($activeMinBirth)->diffInYears();
        // 教练支出
        $coachExpenditure = ClubCoachCostByCourse::where('club_id', $input['user']['club_id'])
            ->where('class_id', $input['id'])
            ->sum('coach_cost');
        // 亏损
        $defectiveFee = $signIncomeTotal - $coachExpenditure;
        if ($defectiveFee > 0) {
            $defectiveFee = 0;
        }
        if ($defectiveFee < 0) {
            $defectiveFee = abs($defectiveFee);
        }

        $list['openedCourseTotal'] = $openedCourseTotal;
        $list['totalIncome'] = $signIncomeTotal;
        $list['profitTotal'] = $profitTotal;
        $list['endFundsTimes'] = $endFundsTimes;
        $list['signIncomeTotal'] = $signIncomeTotal;
        $list['totalExpenditure'] = $coachExpenditure;
        $list['coachExpenditure'] = $coachExpenditure;
        $list['defectiveFee'] = $defectiveFee;
        $list['effectStudent'] = $effectStudent;
        $list['notEffectStudent'] = $notEffectStudent;
        $list['freezeStudent'] = $freezeStudent;
        $list['activeStudent'] = $activeStudent;
        $list['classId'] = $class->id;
        $list['className'] = $class->name;
        $list['paymentName'] = $class->pay_tag_name;
        $list['startTime'] = $classTime->transform(function ($items) {
            $arr['dateTime'] = Classes::packageClassTime($items->day, $items->start_time, $items->end_time);
            return $arr;
        });
        $list['maxStudent'] = $class->student_limit;
        $list['activeStudentAge'] = $activeMinAge .'-'. $activeMaxAge;
        $list['classImg'] = $classImg->transform(function ($items) {
            $arr['img'] = env('IMG_DOMAIN').$items->file_path;
            return $arr;
        });
        $list['showInApp'] = $class->show_in_app;
        $list['trafficInfo'] = ClubVenue::where('id', $class->venue_id)->value('traffic_info');
        $list['remark'] = $class->remark;
        // 班级上课时间

        $list['classTime'] = Classes::classStartTime($input['id']);
        return returnMessage('200', '', $list);
    }

    /**
     * 班级概况
     * @param Request $request
     * @return array
     */
    public function classBasicData(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'startTime' => 'required|date:Y-m-d',
            'endTime' => 'required|date:Y-m-d',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $startDate = strtotime($input['startTime']);
        $endDate = strtotime($input['endTime']);
        $type = $input['type'];

        // 开始时间必须大于结束时间
        if ($endDate < $startDate) {
            return returnMessage('1408', config('error.class.1408'));
        }

        // 签到收入
        $signIncomeTotal = $this->signIncomeTotal($input['id'], $startDate, $endDate);
        // 盈利
        $profitTotal = $this->profitTotal($input['id'], $startDate, $endDate);
        // 月、周
        $dateRange = getSummer($startDate, $endDate, $type);
        $arr = [];
        foreach ($dateRange as $key => $value) {
            $arr[$key]['dateTime'] = $value['start'] . '~' . $value['end'];
            $arr[$key]['coachExpenditure'] = ClubCoachCostByCourse::where('class_id', $input['id'])
                ->whereBetween('created_at', [$value['start'], $value['end']])
                ->sum('coach_manage_cost');
            $arr[$key]['signIncome'] = ClubIncomeSnapshot::where('class_id', $input['id'])
                ->whereBetween('created_at', [$value['start'], $value['end']])
                ->where('is_delete', 0)
                ->sum('money');
            $arr[$key]['expenditure'] = ClubCoachCostByCourse::where('class_id', $input['id'])
                ->whereBetween('created_at', [$value['start'], $value['end']])
                ->sum('coach_manage_cost');
            $arr[$key]['income'] = ClubIncomeSnapshot::where('class_id', $input['id'])
                ->whereBetween('created_at', [$value['start'], $value['end']])
                ->where('is_delete', 0)
                ->sum('money');
            $arr[$key]['profit'] = $arr[$key]['expenditure'] - $arr[$key]['income'];
        }
        $list['profitTotal'] = $profitTotal;
        $list['signIncomeTotal'] = $signIncomeTotal;
        $list['result'] = $arr;
        return returnMessage('200', '', $list);
    }
    // 某时间段签到收入
    public function signIncomeTotal($classId, $startTime, $endTime)
    {
        $signIncomeTotal = ClubIncomeSnapshot::where('class_id', $classId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->where('is_delete', 0)
            ->sum('money');
        return $signIncomeTotal;
    }
    // 某时间段盈利
    public function profitTotal($classId, $startTime, $endTime)
    {
        $expenditure = ClubCoachCostByCourse::where('class_id', $classId)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->sum('coach_manage_cost');
        $profitTotal = $expenditure - $expenditure;
        return $profitTotal;
    }

    // 班级概况-是否APP端显示
    public function classShowInApp(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'showInApp' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $class = ClubClass::find($input['id']);
        $class->show_in_app = $input['showInApp'];
        $class->save();
        return returnMessage('200', '');
    }

    /**
     * 班级设置-显示数据
     * @param Request $request
     * @return array
     */
    public function classSetData(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 班级时间
        $classTime = ClubClassTime::with('class')
            ->where('class_id', $input['id'])
            ->get();
        // 班级图片
        $classImg = ClubClassImage::where('class_id', $input['id'])->where('is_delete', 0)->get();

        $class = ClubClass::find($input['id']);
        $result['classId'] = $class->id;
        $result['className'] = $class->name;
        $result['classType'] = ClubClassType::where('id', $class->type)->value('name');
        $result['paymentName'] = ClubPayment::where('id', $class->pay_plan_type_id)->value('name');
        $result['maxStudent'] = $class->student_limit;
        $result['classTime'] = $classTime->transform(function ($items) {
            $arr['dateTime'] = Classes::packageClassTime($items->day, $items->start_time, $items->end_time);
            return $arr;
        });
        $result['showInApp'] = $class->show_in_app;
        $result['trafficInfo'] = ClubVenue::where('id', $class->venue_id)->value('traffic_info');
        $result['remark'] = $class->remark;
        $result['classImg'] = $classImg->transform(function ($items) {
            $arr['img'] = env('IMG_DOMAIN').$items->file_path;
            $arr['id'] = $items->id;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * 班级设置-图片上传
     * @param Request $request
     * @return array
     */
    public function uploadClassImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'imgKey' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $imgArr = explode(',', $input['imgKey']);
        foreach ($imgArr as $value) {
            $maxSort = ClubClassImage::where('class_id', $input['id'])->max('sort');
            $classImg = new ClubClassImage();
            $classImg->class_id = $input['id'];
            $classImg->file_path = $value;
            $classImg->sort = $maxSort + 1;
            $classImg->save();
        }
        return returnMessage('200', '');
    }

    /**
     * 班级设置-图片删除
     * @param Request $request
     * @return array
     */
    public function deleteClassImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $img = ClubClassImage::find($input['id']);
        $img->is_delete = 1;
        $img->save();

        return returnMessage('200', '');
    }

    /**
     * 班级设置-学员列表
     * @param Request $request
     * @return array
     */
    public function classStudentList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'hide' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $hide = isset($input['hide']) ? $input['hide'] : 0;

        $student = ClubClassStudent::with('student')
            ->where('class_id', $input['id'])
            ->where('club_id', $input['user']['club_id'])
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $student->total();

        $arr = [];
        foreach ($student as $key => $value) {
            if ($hide == 1 && $value['student']['left_course_count'] == 0) continue;
            $arr[$key]['studentId'] = $value['student_id'];
            $arr[$key]['studentName'] = $value['student_name'];
            $arr[$key]['age'] = Carbon::parse($value['student']['birthday'])->diffInYears();
            $arr[$key]['status'] = $value['student']['status'];
            $arr[$key]['courseCount'] = $value['student']['left_course_count'];
            $arr[$key]['phone'] = $value['student']['guarder_mobile'];
        }
        $list['result'] = $arr;

        return returnMessage('200', '', $list);
    }

    /**
     * 班级设置-课程列表
     * @param Request $request
     * @return array
     */
    public function classCourseList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'courseId' => 'nullable|numeric',
            'startTime' => 'nullable|date:Y-m-d',
            'endTime' => 'nullable|date:Y-m-d',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $courseId = isset($input['courseId']) ? $input['courseId'] : 0;
        $startDate = isset($input['startTime']) ? $input['startTime'] : '';
        $endDate = isset($input['endTime']) ? $input['endTime'] : '';

        $course = ClubCourse::with('courseIncome', 'course_sign')
            ->where('class_id', $input['id'])
            ->where(function ($query) use ($courseId) {
                if (!empty($courseId)) {
                    return $query->where('id', $courseId);
                }
            })
            ->where(function ($query) use ($startDate, $endDate) {
                if (!empty($startDate) && !empty($endDate)) {
                    return $query->whereBetween('day', [$startDate, $endDate]);
                }
            })
            ->where('is_delete', 0)
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $signIncome = 0;
        $attendanceNum = 0;
        $experienceNum = 0;
        $fieldPersonNum = 0;
        $list['totalNum'] = $course->total();
        $list['result'] = $course->transform(function ($items) use ($signIncome, $attendanceNum, $experienceNum, $fieldPersonNum) {
            $arr['courseId'] = $items->id;
            $arr['courseTime'] = $items->day;

            // 教练信息
            $coach = ClubCoach::find($items->coach_id);

            // 教练费用
            if (empty($coach)) {
                $arr['coachFee'] = 0;
            }
            else {
                if ($coach->course_time == 0) {
                    $arr['coachFee'] = $coach->basic_salary;
                }
                else {
                    $arr['coachFee'] = $coach->basic_salary/$coach->course_time;
                }
            }

            if ($items->courseIncome->isEmpty()) {
                $arr['signIncome'] = $signIncome;
            }
            else {
                foreach ($items->courseIncome as $key => $value) {
                    $signIncome = $signIncome + $value->money;
                }
                $arr['signIncome'] = $signIncome;
            }

            if ($items->course_sign->isEmpty()) {
                $arr['attendanceNum'] = $attendanceNum;
                $arr['experienceNum'] = $experienceNum;
                $arr['fieldPersonNum'] = $fieldPersonNum;
            }
            else {
                foreach ($items->course_sign as $key => $value) {
                    if ($value->sign_status == 1) {
                        $arr['attendanceNum'] = $attendanceNum++;
                    }
                    if ($value->is_subscribe == 1) {
                        $arr['experienceNum'] = $experienceNum++;
                    }
                    if ($value->is_outside == 1) {
                        $arr['fieldPersonNum'] = $fieldPersonNum++;
                    }
                }
            }
            $arr['status'] = $items->status;

            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 班级设置-删除课程
     * @param Request $request
     * @return array
     */
    public function deleteClassCourse(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $course = ClubCourse::find($input['id']);
        // 已开课的课程不能删除
        $openCourseTime = $course->day . ' ' . $course->start_time;
        if ($openCourseTime < date('Y-m-d H:i:s', time())) {
            return returnMessage('1405', config('error.class.1405'));
        }

        DB::transaction(function () use ($input, $course) {
            $course->is_delete = 1;
            $course->save();

            ClubCourseCoach::where('course_id', $input['id'])
                ->update(['is_delete' => 1]);

            ClubCoachCostByCourse::where('course_id', $input['id'])
                ->update(['is_delete' => 1]);
        });

        return returnMessage('200', '');
    }

    /**
     * 班级设置-班级上课时间
     * @param Request $request
     * @return array
     */
    public function classStartCourseTime(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $time = ClubClassTime::where('class_id', $input['id'])->get();
        $result = $time->transform(function ($items) {
            $arr['day'] = $items->day;
            $arr['starTime'] = $items->start_time;
            $arr['endTime'] = $items->end_time;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * 班级设置-新增课程
     * @param Request $request
     * @return array
     */
    public function addClassCourse(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'startDate' => 'required|date:Y-m-d',
            'coachId' => 'required|numeric',
            'startTime' => 'required|date_format:H:i:s',
            'endTime' => 'required|date_format:H:i:s'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $classId = $input['id'];
        $startDate = $input['startDate'];
        // 日期转换星期
        $dateToWeek = Carbon::parse($startDate)->dayOfWeekIso;

        // 创建日期不能小于当前日期
        if ($startDate < Carbon::now()->format('Y-m-d')) {
            return returnMessage('2004', config('error.course.2004'));
        }

        // 课程是否已存在
        $course = ClubCourse::where('club_id', $input['user']['club_id'])
            ->where('class_id', $classId)
            ->where('coach_id', $input['coachId'])
            ->where('day', $startDate)
            ->where('week', $dateToWeek)
            ->where('start_time', $input['startTime'])
            ->where('end_time', $input['endTime'])
            ->where('is_delete', 0)
            ->exists();
        if (!empty($course)) {
            return returnMessage('2003', config('error.course.2003'));
        }

        // 课程班级信息
        $class = ClubClass::where('id', $classId)->first();
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        // 当月教练的管理费用
        $manageCost = $this->getCoachManageCost($input['user']['club_id']);

        // 请设置当月教练的管理费用
        if ($manageCost == 0) {
            return returnMessage('1701', config('error.coach.1701'));
        }

        // 教练信息
        $coach = ClubCoach::find($input['coachId']);

        // 请设置选择教练的基本工资
        if ($coach->basic_salary == 0) {
            return returnMessage('1702', config('error.coach.1702'));
        }

        DB::transaction(function () use ($class, $input, $dateToWeek, $coach, $manageCost) {
            // 添加课程
            $data = [
                'club_id' => $class->club_id,
                'venue_id' => $class->venue_id,
                'class_id' => $class->id,
                'class_type_id' => $class->type,
                'day' => $input['startDate'],
                'week' => $dateToWeek,
                'coach_id' => $input['coachId'],
                'coach_name' => $coach->name,
                'start_time' => $input['startTime'],
                'end_time' => $input['endTime'],
                'created_at' => date('Y-m-d H:i:s', time()),
                'updated_at' => date('Y-m-d H:i:s', time())
            ];
            $courseId = ClubCourse::insertGetId($data);

            // 添加课程教练
            $courseCoach = new ClubCourseCoach();
            $courseCoach->course_id = $courseId;
            $courseCoach->class_id = $class->id;
            $courseCoach->club_id = $class->club_id;
            $courseCoach->class_type_id = $class->type;
            $courseCoach->coach_id = $input['coachId'];
            $courseCoach->coach_name = $coach->name;
            $courseCoach->manage_cost = $manageCost;
            $courseCoach->save();

            // 教练每节课的费用
            $coachCost = new ClubCoachCostByCourse();
            $coachCost->course_id = $courseId;
            $coachCost->course_date = $input['startDate'];
            $coachCost->class_id = $class->id;
            $coachCost->class_type_id = $class->type;
            $coachCost->venue_id = $class->venue_id;
            $coachCost->club_id = $class->club_id;
            $coachCost->coach_id = $input['coachId'];
            if (empty($coach->basic_salary) || empty($coach->course_time)) {
                $coachCost->coach_cost = 0;
            } else {
                $coachCost->coach_cost = $coach->basic_salary/$coach->course_time;
            }
            $coachCost->coach_manage_cost = $manageCost;
            $coachCost->save();
        });

        return returnMessage('200', '');
    }

    /**
     * 班级设置-批量新增
     * @param Request $request
     * @return array
     */
    public function addMonthClassCourse(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'date' => 'required|date:Y-m'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $classId = $input['id'];
        $date = $input['date'];
        // 当月的日期
        $startDate = date('Y-m-d', strtotime("first day of $date"));
        $endDate = date('Y-m-d', strtotime("last day of $date"));
        $dateRange = dateFromRange($startDate, $endDate);

        // 不可创建晚于当月的课程
        $mowMonth = Carbon::today()->format('Y-m');
        $todayDate = Carbon::today()->format('Y-m-d');
        if ($input['date'] < $mowMonth) {
            return returnMessage('1416', config('error.class.1416'));
        }

        // 班级上课星期
        $classTimeArr = ClubClassTime::where('class_id', $classId)
            ->pluck('day')
            ->toArray();

        // 最新一条课程信息
        $maxCourseId = ClubCourse::where('class_id', $classId)
            ->where('club_id', $input['user']['club_id'])
            ->where('coach_id', '<>', '')
            ->max('id');
        $courseCoach = ClubCourse::find($maxCourseId);

        // 没有含有教练的最新课程，提示创建单个课程
        if (empty($courseCoach)) {
            return returnMessage('1417', config('error.class.1417'));
        }

        // 教练信息
        $coach = ClubCoach::find($courseCoach->coach_id);

        // 请设置选择教练的基本工资
        if ($coach->basic_salary == 0) {
            return returnMessage('1701', config('error.coach.1701'));
        }

        // 请设置选择教练的额定课时
        if ($coach->course_time == 0) {
            return returnMessage('1701', config('error.coach.1701'));
        }

        // 课程班级信息
        $class = ClubClass::where('id', $classId)->first();

        DB::transaction(function () use ($dateRange, $classId, $class, $classTimeArr, $coach, $todayDate) {

            foreach ($dateRange as $value) {

                if (in_array(Carbon::parse($value)->dayOfWeekIso, $classTimeArr)) {

                    // 日期晚于当前日期，跳出
                    if ($value < $todayDate) continue;

                    // 某星期某天的上课时间
                    $weekTime = $this->returnClassWeekTime($classId, Carbon::parse($value)->dayOfWeekIso);

                    // 课程已存在直接跳出不创建
                    $bool = ClubCourse::where('club_id', $class->club_id)
                        ->where('venue_id', $class->venue_id)
                        ->where('class_id', $class->id)
                        ->where('class_type_id', $class->type)
                        ->where('day', $value)
                        ->where('week', Carbon::parse($value)->dayOfWeekIso)
                        ->where('is_delete', 0)
                        ->exists();
                    if ($bool === true) continue;

                    // 添加课程
                    $data['club_id'] = $class->club_id;
                    $data['venue_id'] = $class->venue_id;
                    $data['class_id'] = $class->id;
                    $data['class_type_id'] = $class->type;
                    $data['day'] = $value;
                    $data['week'] = Carbon::parse($value)->dayOfWeekIso;
                    $data['coach_id'] = $coach->id;
                    $data['coach_name'] = $coach->name;
                    $data['start_time'] = $weekTime['startTime'];
                    $data['end_time'] = $weekTime['startTime'];
                    $courseId = ClubCourse::insertGetId($data);

                    // 添加课程教练
                    $courseCoach = new ClubCourseCoach();
                    $courseCoach->course_id = $courseId;
                    $courseCoach->class_id = $class->id;
                    $courseCoach->club_id = $class->club_id;
                    $courseCoach->class_type_id = $class->type;
                    $courseCoach->coach_id = $coach->id;
                    $courseCoach->coach_name = $coach->name;
                    $courseCoach->manage_cost = $this->getCoachManageCost($class->club_id);
                    $courseCoach->saveOrFail();

                    // 教练每节课的费用
                    $coachCost = new ClubCoachCostByCourse();
                    $coachCost->course_id = $courseId;
                    $coachCost->course_date = $value;
                    $coachCost->class_id = $class->id;
                    $coachCost->class_type_id = $class->type;
                    $coachCost->venue_id = $class->venue_id;
                    $coachCost->club_id = $class->club_id;
                    $coachCost->coach_id = $coach->id;
                    if (empty($coach->basic_salary) || empty($coach->course_time)) {
                        $coachCost->coach_cost = 0;
                    } else {
                        $coachCost->coach_cost = $coach->basic_salary/$coach->course_time;
                    }
                    $coachCost->coach_manage_cost = $this->getCoachManageCost($class->club_id);
                    $coachCost->saveOrFail();
                }
            }
        });
        return returnMessage('200', '');
    }

    /**
     * 某天的上课时间
     * @param $classId
     * @param $weekDay
     * @return array
     */
    public function returnClassWeekTime($classId, $weekDay)
    {
        $time = ClubClassTime::where('class_id', $classId)
            ->where('day', $weekDay)
            ->first();
        return [
            'startTime' => $time->start_time,
            'endTime' => $time->end_time
        ];
    }

    /**
     * 班级设置-最近一年未停课的课程
     * @param Request $request
     * @return array
     */
    public function thisYearNotStopClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $year = date('Y', time());
        $courseList = ClubCourse::where('class_id', $input['id'])
            ->where('day', 'like', $year . '%')
            ->where('status', 1)
            ->get();
        $result = $courseList->transform(function ($items) {
            $arr['courseId'] = $items->id;
            $arr['courseDate'] = $items->day;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * 班级设置-批量停课
     * @param Request $request
     * @return array
     */
    public function stopClassCourse(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $courseIdArr = explode(',',$input['id']);

        DB::transaction(function () use ($courseIdArr) {
            foreach ($courseIdArr as $value) {
                $course = ClubCourse::find($value);
                $course->status = 0;
                $course->save();
            }
        });

        return returnMessage('200', '');
    }

    /**
     * 班主任列表
     * @param Request $request
     * @return array
     */
    public function teacherList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $teacherList = Teacher::where('class_id', $input['id'])
            ->where('club_id', $input['user']['club_id'])
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $teacherList->total();
        $list['result'] = $teacherList->transform(function ($items) {
            $arr['teacherId'] = $items->id;
            $arr['teacherName'] = $items->teacher_name;
            $arr['startDate'] = date('Y-m-d', strtotime($items->created_at));
            $arr['endDate'] = $items->end_date;
            $arr['status'] = $items->status;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 班主任列表-新增
     * @param Request $request
     * @return array
     */
    public function addTeacher(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'classId' => 'required|numeric',
            'salesId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $sales = ClubSales::find($input['salesId']);
        if ($sales->status == 0) {
            return returnMessage('1432', config('error.class.1432'));
        }

        $isHave = Teacher::where('club_id', $input['user']['club_id'])
            ->where('class_id', $input['classId'])
            ->where('teacher_id', $input['salesId'])
            ->where('status', 1)
            ->exists();
        if ($isHave === true) {
            return returnMessage('1431', config('error.class.1431'));
        }

        $teacher = new Teacher();
        $teacher->club_id = ClubClass::where('id', $input['classId'])->value('club_id');
        $teacher->venue_id = ClubClass::where('id', $input['classId'])->value('venue_id');
        $teacher->class_id = $input['classId'];
        $teacher->teacher_id = $input['salesId'];
        $teacher->teacher_name = ClubSales::where('id', $input['salesId'])->value('sales_name');
        $teacher->end_date = "2100-01-01";
        $teacher->status = 1;
        $teacher->save();
        return returnMessage('200', '');
    }

    /**
     * 班主任列表-失效
     * @param Request $request
     * @return array
     */
    public function modifyTeacherStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $teacher = Teacher::find($input['id']);
        // 班主任不存在
        if (empty($teacher)) {
            return returnMessage('1407', config('error.class.1407'));
        }
        $teacher->end_date = date('Y-m-d', time());
        $teacher->status = 0;
        $teacher->save();
        return returnMessage('200', '');
    }

    /**
     * 课程列表-课程概况
     * @param Request $request
     * @return array
     */
    public function courseSurvey(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $course = ClubCourse::find($input['id']);
        // 出勤人数
        $attendanceNum = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 1)
            ->count();
        // 缺勤人数
        $absenceNum = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 2)
            ->count();
        // 病假人数
        $sickLeaveNum = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 4)
            ->count();
        // 事假人数
        $personLeaveNum = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 3)
            ->count();
        // 外勤人数
        $fieldPersonNum = ClubCourseSign::where('course_id', $input['id'])
            ->where('is_outside', 1)
            ->count();
        // 总收入
        $totalIncome = ClubIncomeSnapshot::where('course_id', $input['id'])
            ->where('is_delete', 0)
            ->sum('money');
        // 出勤收入
        $signId = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 1)
            ->pluck('id');
        $attendanceFee = ClubIncomeSnapshot::whereIn('sign_id', $signId)
            ->where('is_delete', 0)
            ->sum('money');
        // 缺勤收入
        $signId = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 2)
            ->pluck('id');
        $absenceFee = ClubIncomeSnapshot::whereIn('sign_id', $signId)
            ->where('is_delete', 0)
            ->sum('money');
        // 事假收入
        $signId = ClubCourseSign::where('course_id', $input['id'])
            ->where('sign_status', 3)
            ->pluck('id');
        $personLeaveFee = ClubIncomeSnapshot::whereIn('sign_id', $signId)
            ->where('is_delete', 0)
            ->sum('money');
        // 教练费用
        $coachFee = ClubCoachCostByCourse::where('course_id', $input['id'])->value('coach_manage_cost');
        // 盈利
        $profitTotal = $totalIncome - $coachFee;

        $list['attendanceNum'] = $attendanceNum;
        $list['absenceNum'] = $absenceNum;
        $list['sickLeaveNum'] = $sickLeaveNum;
        $list['personLeaveNum'] = $personLeaveNum;
        $list['fieldPersonNum'] = $fieldPersonNum;
        $list['totalIncome'] = $totalIncome;
        $list['attendanceFee'] = $attendanceFee;
        $list['absenceFee'] = $absenceFee;
        $list['totalExpenditure'] = $coachFee;
        $list['personLeaveFee'] = $personLeaveFee;
        $list['totalExpenditure'] = $coachFee;
        $list['coachFee'] = $coachFee;
        $list['profitTotal'] = $profitTotal;
        $list['courseId'] = $course->id;
        $list['classId'] = $course->class_id;
        $list['className'] = ClubClass::where('id', $course->class_id)->value('name');
        $list['courseTime'] = $course->day .' '. $course->start_time .'~'.$course->end_time;
        $list['status'] = $course->status;
        $list['remark'] = $course->remark;
        return returnMessage('200', '', $list);
    }

    /**
     * 课程列表-课程概况-停课上课
     * @param Request $request
     * @return array
     */
    public function modifyCourseStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $course = ClubCourse::find($input['id']);
        $course->status = $input['type'];
        $course->save();
        return returnMessage('200', '');
    }

    /**
     * 课程列表-课程概况-备注
     * @param Request $request
     * @return array
     */
    public function modifyCourseRemark(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'content' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $course = ClubCourse::find($input['id']);
        $course->remark = $input['content'];
        $course->save();
        return returnMessage('200', '');
    }

    /**
     * 课程列表-签到列表
     * @param Request $request
     * @return array
     */
    public function courseStudentList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'hideNotOrder' => 'nullable|numeric',
            'hideOutside' => 'nullable|numeric',
            'hideNotJoin' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $studentList = ClubCourseSign::with('student')
            ->where('course_id', $input['id'])
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $studentList->total();
        $list['result'] = $studentList->transform(function ($items) {
            $arr['signId'] = $items->id;
            $arr['venueName'] = ClubVenue::where('id', ClubClass::where('id', $items->class_id)->value('venue_id'))->value('name');
            $arr['courseId'] = $items->course_id;
            $arr['studentId'] = $items->student_id;
            $arr['studentName'] = $items->student->name;
            // 是否是预约签到
            $arr['isSubscribe'] = $items->is_subscribe;
            if ($items->is_subscribe == 1) {
                $arr['isSubscribeStatus'] = $this->getSubscribeStatus($items->student_id, $items->club_id);
            }
            // 是否是外勤签到
            $arr['isOutside'] = $items->is_outside;
            // 最近是否有外勤
            $arr['nearestOutside'] = $this->nearestOutsideNum($items->student_id, $items->class_id);
            $arr['age'] = Carbon::parse($items->student->birthday)->diffInYears();
            $arr['status'] = $items->sign_status;
            $arr['isMvp'] = $items->ismvp;
            $arr['leftCourseNum'] = $items->student->left_course_count;
            $arr['sickLeaveImg'] = $items->sick_leave_image;
            $arr['remark'] = $items->remark;
            $arr['signLog'] = Classes::stuFourTimesAgoSign($items->id);
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 是否有外勤记录
     * @param $studentId
     * @param $classId
     * @return mixed
     */
    public function nearestOutsideNum($studentId, $classId)
    {
        $num = ClubCourseSign::where('student_id', $studentId)
            ->where('class_id', '<>', $classId)
            ->count();
        return $num;
    }

    /**
     * 获取预约状态
     * @param $studentId
     * @param $clubId
     * @return mixed
     */
    public function getSubscribeStatus($studentId, $clubId)
    {
        $subscribe = ClubStudentSubscribe::where('student_id', $studentId)
            ->where('club_id', $clubId)
            ->first();
        return $subscribe->status;
    }

    /**
     * 预约标记取消
     * @param Request $request
     * @return array
     */
    public function courseStudentSubscribeSign(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'signId' => 'required|numeric',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $courseSign = ClubCourseSign::find($input['signId']);
        // 签到记录不存在
        if (empty($courseSign)) {
            return returnMessage('1412', config('error.class.1412'));
        }

        // 处理预约逻辑
        DB::transaction(function () use ($input, $courseSign) {
            ClubStudentSubscribe::where('student_id', $courseSign->student_id)
                ->where('club_id', $courseSign->club_id)
                ->update(['status' => $input['type']]);
        });

        return returnMessage('200', '');
    }

    /**
     * 课程列表-学员列表-修改签到状态
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function modifyCourseSignStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'signId' => 'required|numeric',
            'status' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 签到课程
        $course = ClubCourse::notDelete()->courseOn()->find($input['courseId']);
        Log::setGroup('StuSignError')->error('签到课程',['course' => $course]);
        if (empty($course)) {
            return returnMessage('1415', config('error.class.1415'));
        }

        // 签到班级
        $class = ClubClass::valid()->with(['venue:id,name'])->find($course->class_id);
        Log::setGroup('StuSignError')->error('签到班级',['class' => $class]);
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $clubId = $input['user']['club_id'];
        $signId = $input['signId'];
        $status = $input['status'];
        $courseId = $input['courseId'];
        $classType = $class->type;
        $operateUserId = $input['user']['id'];

        // 签到记录
        $courseSign = ClubCourseSign::notDelete()->find($input['signId']);
        Log::setGroup('StuSignError')->error('签到记录',['courseSign' => $courseSign]);
        if (empty($courseSign)) {
            return returnMessage('1412', config('error.class.1412'));
        }

        // 签到学员
        $student = ClubStudent::notDelete()->find($courseSign->student_id);
        Log::setGroup('StuSignError')->error('签到学员',['student' => $student]);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        // 冻结学员只能签出勤、冻结
        if ($student->is_freeze == 1 && !in_array($input['status'], [1, 5])) {
            return returnMessage('1413', config('error.class.1413'));
        }

        // 学员对应班级类型缴费记录
        $stuPayIdArr = ClubStudentPayment::where('club_id', $clubId)
            ->where('student_id', $courseSign->student_id)
            ->where('payment_class_type_id', $classType)
            ->pluck('id')
            ->toArray();

        // 没有与班级类型匹配的缴费
        if (count($stuPayIdArr) <= 0) {
            return returnMessage('2404', config('error.sign.2404'));
        }

        // todo 外勤签到
        if ($courseSign->is_outside == 1) {
            $subscribe = ClubStudentSubscribe::where('student_id', $student->id)
                ->where('is_delete', 0)
                ->first();
            Log::setGroup('StuSignError')->error('预约学员外勤签到',['subscribe' => $subscribe]);

            if (!empty($subscribe)) {//预约
                if ($courseSign->is_used == 0) {// 首次签到
                    // 签到使用的课程券
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $classType);
                    Log::setGroup('StuSignError')->error('签到课程券',['ticket' => $ticket]);

                    // 课程券不足
                    if (empty($ticket)) {
                        return returnMessage('1423', config('error.class.1423'));
                    }

                    try {
                        DB::transaction(function () use ($student,$signId,$status,$class,$courseId,$ticket,$courseSign,$clubId,$operateUserId,$course) {
                            $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                            $this->firstSignForSubscribeWithOutDuty($signId, $status, $class, $courseId, $ticket, $courseSign, $student, $clubId, $operateUserId, $course);
                            Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                            if ($student->is_freeze == 1 && $status == 1) {
                                Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                            }
                        });
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                } else {//非首次签到
                    try {
                        DB::transaction(function () use ($student,$signId,$courseSign, $clubId, $status, $operateUserId, $course) {
                            $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                            $this->notFirstSignForSubscribeWithOutDuty($courseSign, $student, $clubId, $status, $operateUserId, $course);
                            Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                            if ($student->is_freeze == 1 && $status == 1) {
                                Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                            }
                        });
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                }
            } else {//非预约
                if ($courseSign->is_used == 0) {// 首次签到
                    // 签到使用的课程券
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $classType);
                    Log::setGroup('StuSignError')->error('签到课程券',['ticket' => $ticket]);

                    // 课程券不足
                    if (empty($ticket)) {
                        return returnMessage('1423', config('error.class.1423'));
                    }

                    $salesId = ClubStudentPayment::where('student_id', $student->id)->where('id', $ticket->payment_id)->value('sales_id');

                    try {
                        DB::transaction(function () use ($student,$signId,$status,$class,$course,$ticket,$courseSign,$clubId,$salesId,$operateUserId) {
                            $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                            $this->firstSignWithOutDuty($signId,$status,$class,$course,$ticket,$courseSign,$student,$clubId,$salesId,$operateUserId);
                            Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                            Log::setGroup('StuSignError')->error('冻结签出勤自动解冻',['is_freeze' => $student->is_freeze, 'status' => $status]);
                            if ($student->is_freeze == 1 && $status == 1) {
                                Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                            }
                        });
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                } else {//非首次签到
                    try {
                        DB::transaction(function () use ($student,$signId,$courseSign, $course, $status, $class, $clubId, $operateUserId) {
                            $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                            $this->notFirstSignWithOutDuty($student,$courseSign, $course, $status, $signId, $class, $clubId, $operateUserId);
                            Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                            Log::setGroup('StuSignError')->error('冻结签出勤自动解冻',['is_freeze' => $student->is_freeze, 'status' => $status]);
                            if ($student->is_freeze == 1 && $status == 1) {
                                Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                            }
                        });
                    } catch (Exception $e) {
                        return returnMessage($e->getCode(),$e->getMessage());
                    }
                }
            }

            return returnMessage('200', '');
        } elseif ($courseSign->is_subscribe == 1) {  // todo 预约签到
            $subscribe = ClubStudentSubscribe::where('student_id', $student->id)
                ->where('sign_id',$courseSign->id)
                ->where('is_delete', 0)
                ->first();
            Log::setGroup('StuSignError')->error('预约学员非外勤签到',['subscribe' => $subscribe]);

            if (!in_array($status, [1, 2])) {//预约体验签到只能签出勤、缺勤
                return returnMessage('1419', config('error.class.1419'));
            }

            if ($courseSign->is_used == 0) {// 首次签到
                // 签到使用的课程券
                $ticket = Classes::getSubscribeExTicket($clubId, $student->id);
                Log::setGroup('StuSignError')->error('签到课程券',['ticket' => $ticket]);

                // 课程券不足
                if (empty($ticket)) {
                    return returnMessage('1423', config('error.class.1423'));
                }

                try {
                    DB::transaction(function () use ($student,$signId,$status,$class, $courseId, $ticket, $courseSign, $clubId, $operateUserId, $course) {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                        $this->firstSignForSubscribeWithNotOutDuty($signId, $status, $class, $courseId, $ticket, $courseSign, $student, $clubId, $operateUserId, $course);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                        if ($student->is_freeze == 1 && $status == 1) {
                            Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                        }
                    });
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            } else {
                try {
                    DB::transaction(function () use ($student,$signId,$courseSign, $clubId, $status, $operateUserId, $course) {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                        $this->notFirstSignForSubscribeWithNotOutDuty($courseSign, $student, $clubId, $status, $operateUserId, $course);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                        if ($student->is_freeze == 1 && $status == 1) {
                            Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                        }
                    });
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            }

            return returnMessage('200', '');
        } else {  // todo 正式签到
            if ($courseSign->is_used == 0) {// 首次签到
                // 签到使用的课程券
                $ticket = Classes::getStudentSignTicket($clubId, $student->id, $classType);
                Log::setGroup('StuSignError')->error('签到课程券',['ticket' => $ticket]);

                // 课程券不足
                if (empty($ticket)) {
                    return returnMessage('1423', config('error.class.1423'));
                }

                $salesId = ClubStudentPayment::where('student_id', $student->id)->where('id', $ticket->payment_id)->value('sales_id');

                try {
                    DB::transaction(function () use ($student,$signId, $status, $class, $course, $ticket, $courseSign, $clubId, $salesId,$operateUserId) {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                        $this->firstSignWithNotOutDuty($signId, $status, $class, $course, $ticket, $courseSign, $student, $clubId, $salesId,$operateUserId);
                        // 记录学员签到记录签到状态的改变
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                        // 冻结学员签出勤解冻成为活跃学员
                        if ($student->is_freeze == 1 && $status == 1) {
                            Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                        }
                    });
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            } else {//非首次签到
                try {
                    DB::transaction(function () use ($student,$signId, $courseSign, $course, $status, $class, $clubId, $stuPayIdArr,$operateUserId) {
                        $this->addOrCancelTryRewardToRecommendStudent($student,$signId,$status);
                        $this->notFirstSignWithNotOutDuty($student, $courseSign, $course, $status, $signId, $class, $clubId, $stuPayIdArr,$operateUserId);
                        Classes::recordStuCourseSign($clubId, $signId, $student->id, $status, $operateUserId);
                        if ($student->is_freeze == 1 && $status == 1) {
                            Classes::stuSignAutoUnfreeze($student->id, $student->freeze_id);
                        }
                    });
                } catch (Exception $e) {
                    return returnMessage($e->getCode(),$e->getMessage());
                }
            }

            //签到成功，发送一个课程评价推送
            $appUserMobiles = Common::getStudentBindAppUserMobiles($student->id);
            $paramData = [
                'classId' => $class->id,
                'className' => $class->name,
                'venueName' => $class->venue ? $class->venue->name : '',
                'courseDate' => $course->day,
                'appUserMobiles' => $appUserMobiles ? implode(',',$appUserMobiles) : ''
            ];

            $res = Common::addClubCourseCommentNotice($paramData);

            if ($res['code'] != '200') {
                $arr = [
                    'code' => $res['code'],
                    'msg' => $res['msg'],
                    'paramData' => $paramData
                ];
                Log::setGroup('StuSignError')->error('推送课程通知有误',[$arr]);
            }

            return returnMessage('200', '');
        }
    }

    /**
     * 签到扣课时
     * @param $studentId
     */
    public function decrementCourseCount($studentId)
    {
        ClubStudent::where('id', $studentId)
            ->decrement('left_course_count');
    }

    /**
     * 设置MVP
     * @param Request $request
     * @return array
     */
    public function modifyCourseMvp(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'signId' => 'required|numeric',
            'status' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $courseSign = ClubCourseSign::find($input['signId']);
        if (empty($courseSign)) {
            return returnMessage('1412', config('error.class.1412'));
        }

        // 设置mvp
        if ($input['status'] == 1) {
            DB::transaction(function () use ($courseSign, $input) {
                $courseSign->ismvp = $input['status'];
                $courseSign->save();

                ClubStudent::where('id', $courseSign->student_id)->increment('mvp_count');
            });

            return returnMessage('200', '');
        }

        // 取消mvp
        DB::transaction(function () use ($courseSign, $input) {
            $courseSign->ismvp = $input['status'];
            $courseSign->save();

            ClubStudent::where('id', $courseSign->student_id)->decrement('mvp_count');
        });

        return returnMessage('200', '');
    }

    /**
     * 课程列表-学员列表-清除
     * @param Request $request
     * @return array
     */
    public function clearCourseSignStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'signId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        ClubCourseSign::where('id', $input['signId'])
            ->update(['sign_status' => 0]);

        return returnMessage('200', '');
    }

    /**
     * 添加外勤-搜索select
     * @param Request $request
     * @return array
     */
    public function addOutsideStudentSelect(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'searchVal' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $searchVal = $input['searchVal'];

        $studentList = ClubStudent::where('club_id', $input['user']['club_id'])
            ->where(function ($query) use ($searchVal) {
                if (!empty($searchVal)) {
                    return $query->where('name', 'like', '%' . $searchVal . '%')->orWhere('id', $searchVal);
                }
            })
            ->where('status', 1)
            ->get();
        $result = $studentList->transform(function ($items) {
            $arr['studentId'] = $items->id;
            $arr['studentInfo'] = $items->name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * 拼接学员信息
     * @param $studentId
     * @param $studentName
     * @param $classId
     * @return string
     */
    public function splitStudentInfo($studentId, $studentName, $classId)
    {
        $class = ClubClass::where('id', $classId)->first();
        $className = $class->name;
        $classTime = ClubClassTime::where('class_id', $classId)->first();
        $week = ['周一', '周二', '周三', '周四', '周五', '周六', '周日'];
        $starTime = $classTime->start_time;
        $studentInfo = $studentId .'.'. $studentName .' '. $className .' '. $week[$classTime->day] . ' ' . $starTime;
        return $studentInfo;
    }

    /**
     * 课程列表-学员列表-添加外勤
     * @param Request $request
     * @return array
     */
    public function addOutsideStudent(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'studentId' => 'required|numeric',
            'status' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 课程
        $course = ClubCourse::where('id', $input['courseId'])->first();

        // 班级
        $class = ClubClass::find($course->class_id);

        // 班级类型相同才能添加
        $stuPayTypeArr = ClubStudentPayment::where('student_id', $input['studentId'])
            ->where('club_id', $input['user']['club_id'])
            ->pluck('payment_class_type_id')
            ->toArray();

        if (!in_array($class->type, $stuPayTypeArr)) {
            return returnMessage('1433', config('error.class.1433'));
        }

        // 同课程下不可重复添加
        $stuSign = ClubCourseSign::where('student_id', $input['studentId'])
            ->where('course_id', $input['courseId'])
            ->where('club_id', $input['user']['club_id'])
            ->where('class_id', $course->class_id)
            ->exists();
        if ($stuSign === true) {
            return returnMessage('1422', config('error.class.1422'));
        }

        $courseSign = new ClubCourseSign();
        $courseSign->club_id = $input['user']['club_id'];
        $courseSign->class_id = $course->class_id;
        $courseSign->course_id = $input['courseId'];
        $courseSign->student_id = $input['studentId'];
        $courseSign->sign_status = 0;
        $courseSign->sign_date = date('Y-m-d', time());
        $courseSign->is_outside = 1;
        $courseSign->is_subscribe = 0;
        $courseSign->class_type_id = $course->class_type_id;
        $courseSign->save();

        return returnMessage('200', '');
    }

    /**
     * 课程列表-学员列表-修改备注
     * @param Request $request
     * @return array
     */
    public function modifyCourseSignRemark(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'signId' => 'required|numeric',
            'content' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        ClubCourseSign::where('id', $input['signId'])
            ->update(['remark' => $input['content']]);

        return returnMessage('200', '');
    }

    /**
     * 课程列表-教练列表
     * @param Request $request
     * @return array
     */
    public function courseCoachList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 当月教练管理费
        $manageCost = $this->getCoachManageCost($input['user']['club_id']);

        $coachList = ClubCourseCoach::with('coach')
            ->where('course_id', $input['courseId'])
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $coachList->total();
        $list['result'] = $coachList->transform(function ($items) use ($manageCost) {
            $arr['id'] = $items->id;
            $arr['coachName'] = $items->coach->name;
            $arr['coachFee'] = $manageCost;
            $arr['status'] = $items->status;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 当月教练管理费
     * @param $clubId
     * @return mixed
     */
    public function getCoachManageCost($clubId)
    {
        $years = Carbon::now()->year;
        $month = Carbon::now()->month;

        $manageCost = ClubCoachManageCost::where('club_id', $clubId)
            ->where('year', $years)
            ->where('month', $month)
            ->value('cost');

        return $manageCost;
    }

    /**
     * 课程列表-教练列表-添加教练
     * @param Request $request
     * @return array
     */
    public function addCourseCoach(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'coachId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 已存在未失效的教练不可重复添加
        $isHave = ClubCourseCoach::where('club_id', $input['user']['club_id'])
            ->where('course_id', $input['courseId'])
            ->where('coach_id', $input['coachId'])
            ->where('status', 1)
            ->exists();
        if ($isHave === true) {
            return returnMessage('1427', config('error.class.1427'));
        }

        $courseCoach = new ClubCourseCoach();
        $courseCoach->course_id = $input['courseId'];
        $courseCoach->coach_id = $input['coachId'];
        $courseCoach->manage_cost = $input['manageFee'];
        $courseCoach->save();
        return returnMessage('200', '');
    }

    /**
     * 课程列表-教练列表-失效
     * @param Request $request
     * @return array
     */
    public function modifyCourseCoachStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'status' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $courseCoach = ClubCourseCoach::find($input['id']);
        $courseCoach->status = $input['status'];
        $courseCoach->save();
        return returnMessage('200', '');
    }

    /**
     * 课程列表-修改教练费用
     * @param Request $request
     * @return array
     */
    public function modifyCourseCoachFee(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'manageFee' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $courseCoach = ClubCourseCoach::find($input['id']);
        $courseCoach->manage_cost = $input['manageFee'];
        $courseCoach->save();
        return returnMessage('200', '');
    }

    /**
     * 签到表&照片-照片上传
     * @param Request $request
     * @return array
     */
    public function uploadClassCourseSignImage(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'imgKey' => 'required|string',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 原本存在就删除掉
        $images = ClubCourseImage::where('type', $input['type'])
            ->where('course_id', $input['courseId'])
            ->where('is_delete', 0)
            ->first();
        if (!empty($images)) {
            ClubCourseImage::where('id', $images->id)->update(['is_delete' => 1]);
        }

        // 上传图片
        $courseImg = new ClubCourseImage();
        $courseImg->club_id = $input['user']['club_id'];
        $courseImg->course_id = $input['courseId'];
        $courseImg->type = $input['type'];
        $courseImg->file_path = $input['imgKey'];
        $courseImg->save();

        return returnMessage('200', '');
    }

    /**
     * 课程列表-签到表&照片
     * @param Request $request
     * @return array
     */
    public function getCourseSignImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $images = ClubCourseImage::where('type', $input['type'])
            ->where('course_id', $input['courseId'])
            ->where('is_delete', 0)
            ->first();

        if (empty($images)) {
            return returnMessage('200', '');
        }

        $list['id'] = $images->id;
        $list['filePath'] = env('IMG_DOMAIN').$images->file_path;

        return returnMessage('200', '', $list);
    }

    /**
     * 课程列表-签到表&照片-删除
     * @param Request $request
     * @return array
     */
    public function deleteCourseSignImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'imgId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        ClubCourseImage::where('id', $input['imgId'])
            ->update(['is_delete' => 1]);

        return returnMessage('200', '');
    }

    /**
     * 教练评价-获取
     * @param Request $request
     * @return array
     */
    public function getCourseCoachComment(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $course = ClubCourse::find($input['id']);
        if (empty($course)) {
            return returnMessage('1415', config('error.class.1415'));
        }

        $arr['content'] = $course->coach_comment;

        return returnMessage('200', '', $arr);
    }

    /**
     * 课程列表-课程评价
     * @param Request $request
     * @return array
     */
    public function courseEvaluate(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'courseId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $coachComment = ClubCourse::where('id', $input['courseId'])
            ->value('coach_comment');

        $list['coachComment'] = $coachComment;

        return returnMessage('200', '', $list);
    }

    /**
     * 教练评价-保存
     * @param Request $request
     * @return array
     */
    public function modifyCourseCoachComment(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'content' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 是否存在课程
        $course = ClubCourse::find($input['id']);
        if (empty($course)) {
            return returnMessage('1415', config('error.class.1415'));
        }

        // 教练评语最多300字符
        if (mb_strlen($input['content'], 'UTF8') > 300) {
            return returnMessage('1426', config('error.class.1426'));
        }

        $data = [
            'coach_comment' => $input['content']
        ];
        ClubCourse::where('id', $input['id'])
            ->update($data);

        $course = ClubCourse::find($input['id']);
        $arr['content'] = $course->coach_comment;

        return returnMessage('200', '', $arr);
    }

    /**
     * 签到表导出
     * @param Request $request
     * @return array
     */
    public function signExport(Request $request)
    {
        $input = $request->all();
        $validate = \Validator::make($input, [
            'courseId' => 'required|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('1001', config('error.common.1001'));
        }

        $studentInfo = ClubCourseSign::where('course_id',$input['courseId'])
            ->where('club_id',$input['clubId'])
            ->whereHas('student',function($query){
                $query->where('status',1)->where('is_freeze',0)->orWhere('ex_status',3);
            })
            ->get();

        $course = ClubCourse::find($input['courseId']);
        $date = Carbon::parse($course->day)->format('Y年m月d日');
        $courseDate = Carbon::parse($course->start_time)->format('H:i').'-'.Carbon::parse($course->end_time)->format('H:i');
        $week = $this->common->getWeekName($course->week);
        $clubName = ClubCourse::find($input['courseId'])->club()->value('name');
        $venueName = ClubCourse::find($input['courseId'])->venue()->value('name');
        $className = ClubCourse::find($input['courseId'])->class()->value('name');
        $cellData = [
            ['课程签到时间：'.$date.$week.'星期 课程时间：'.$courseDate.'  '.$clubName.'(俱乐部) - '.$venueName.'(场馆) - '.$className.'(班级) - '.$input['courseId'].'(课程号)'],
            [],
            ['序号','学员姓名','学员年龄','获得本次MVP','剩余课时数','签到状态'],
        ];
        if(count($studentInfo) > 0){
            $num = 0;
            foreach ($studentInfo as $val){
                $cellData[] = [
                    $num += 1,
                    $val->student->name.$this->getStudentStatus($val->student->status,$val->student->is_freeze),
                    $val->student->age,
                    '',
//                    empty($val->ismvp) ? '否' : '是',
                    $val->student->left_course_count,
                    '',
//                    ClubCourseSign::getSignStatusName($val->sign_status)
                ];
            }
        }
        Excel::create('签到表',function ($excel) use ($cellData) {
            $excel->sheet('sign in',function ($sheet) use ($cellData) {
                $sheet->setWidth(array( 'A' => 10,'B' => 10,'C' => 10,'D' => 14,'E'=> 12,'F' => 10));
                $sheet->rows($cellData);
                $sheet->getStyle('A1:F2')->getAlignment()->setWrapText(true);
                $sheet->cells('A1:F2',function ($cells) use ($cellData) {
                    $cells->setValignment('center');
                    $cells->setAlignment('center');
                });
                $sheet->mergeCells('A1:F2');
            });
        })->export('xls');
    }

    /**
     * 学员状态
     * @param $status
     * @param $freeze
     * @return string
     */
    protected function getStudentStatus($status,$freeze){
        if($status == 1 && $freeze == 0){
            $aliaz = '(活跃学员)';
        }else{
            $aliaz = '(预约学员)';
        }
        return $aliaz;
    }

    /**
     * 给推荐学员发送获取取消体验奖励
     * @param ClubStudent $student
     * @param $signId
     * @param $signStatus
     * @throws Exception
     * @throws \Throwable
     */
    public function addOrCancelTryRewardToRecommendStudent(ClubStudent $student,$signId,$signStatus)
    {
        Log::setGroup('RecommendError')->error('发送奖励开始');

        //不是推荐的学员，不走奖励逻辑
        if ($student->from_stu_id == 0) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-没有推荐学员');
            return;
        }

        //判断是否首次签到
        $courseSign = ClubCourseSign::notDelete()
            ->where('student_id',$student->id)
            ->where('is_subscribe',1)
            ->first();

        //该学员没有进行首次体验出勤签到，不走奖励逻辑
        if (empty($courseSign) || $courseSign->id != $signId) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-签到不存在或者签到id不匹配');
            return;
        }

        $reserveRecords = ClubRecommendReserveRecord::notDelete()
            ->where('new_stu_id',$student->id)
            ->where('stu_id',$student->from_stu_id)
            ->first();

        if (empty($reserveRecords)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-二维码预约没有生成奖励记录',['stuId' => $student->id,'newStuId' => $student->from_stu_id]);
            return;
        }

        //体验奖励记录
        $rewardRecords = ClubRecommendRewardRecord::where('recommend_id',$reserveRecords->id)
            ->where('event_type',1)
            ->first();

        if (empty($rewardRecords)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-体验奖励记录不存在',['recommendId' => $reserveRecords->id]);
            return;
        }

        $tryRewardNum = $rewardRecords->reward_course_num;  //体验奖励课时数

        //首次出勤签到
        if ($courseSign->is_used == 0) {
            if ($signStatus == 1) {//出勤发奖励
                Log::setGroup('RecommendError')->error('推广奖励记录异常-数据操作有异常',['来了这里']);
                if ($tryRewardNum > 0) {
                    Log::setGroup('RecommendError')->error('推广奖励记录异常-数据操作有异常',['来了这里2']);
                    try {
                        $this->addPaymentRecordsAndTickets($tryRewardNum,$student,$rewardRecords->club_id,$rewardRecords->id);

                        $this->addCourseCountToStudent($student->from_stu_id,$tryRewardNum);
                        $rewardRecords->settle_status = 2;
                        $rewardRecords->saveOrFail();

                        $reserveRecords->recommend_status = 2;
                        $reserveRecords->saveOrFail();
                    } catch (Exception $e) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常-数据操作有异常',['msg' => $e->getMessage()]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }

                    Log::setGroup('RecommendError')->error('推广奖励记录（首次出勤签到）-追回奖励成功');
                }
            }

            return;
        }

        //更改出勤签到
        if ($signStatus == 1 && $courseSign->sign_status != 1) {//出勤
            //防止更改签到两次都为出勤
            if ($reserveRecords->recommend_status == 2 && $rewardRecords->settle_status == 2) {
                Log::setGroup('RecommendError')->error('推广奖励记录异常-奖励已经发放，不能再次发放',['stuId' => $student->id,'newStuId' => $student->from_stu_id]);
                return;
            }

            if ($reserveRecords->recommend_status == 1 || $reserveRecords->recommend_status == 3) {
                if ($rewardRecords->settle_status == 2) {
                    Log::setGroup('RecommendError')->error('推广奖励记录异常-奖励已经结算了',['stuId' => $student->id,'newStuId' => $student->from_stu_id]);
                    return;
                }

                //给推荐学员发放奖励
                if ($tryRewardNum > 0) {
                    try {
                        $this->addPaymentRecordsAndTickets($tryRewardNum,$student,$rewardRecords->club_id,$reserveRecords->id);
                        $this->addCourseCountToStudent($student->from_stu_id,$tryRewardNum);

                        $rewardRecords->settle_status = 2;
                        $rewardRecords->saveOrFail();
                    } catch (Exception $e) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-发送奖励操作异常',['msg' => $e->getMessage()]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }

                    Log::setGroup('RecommendError')->error('推广奖励记录（更改出勤签到）-发放奖励成功');
                }

                if ($reserveRecords->recommend_status == 1) {
                    try {
                        $reserveRecords->recommend_status = 2;
                        $reserveRecords->saveOrFail();
                    } catch (Exception $e) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-更改结算状态操作异常',['msg' => $e->getMessage()]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }
                    Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-更改结算状态操作成功');
                    return;
                }
            }
            return;
        }

        //第一次出勤，改为其他状态
        if ($courseSign->sign_status != 1 && $reserveRecords->recommend_status == 2 && $rewardRecords->settle_status == 2) {
            //追回奖励
            try {
                $this->cancelTryRewardToRecommendStudent($student,$reserveRecords,$rewardRecords);
            } catch (Exception $e) {
                throw new Exception($e->getMessage(),$e->getCode());
            }

            Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-追回奖励成功');
        }
    }

    /**
     * 给推荐学员增加体验缴费方案跟体验券
     * @param $tryRewardNum
     * @param $student
     * @param $clubId
     * @param $rewardRecordId
     * @throws Exception
     * @throws \Throwable
     */
    public function addPaymentRecordsAndTickets($tryRewardNum,$student,$clubId,$rewardRecordId)
    {
        if ($tryRewardNum <= 0) return;

        //查找是否有活动缴费方案
        $freePayment = ClubPayment::valid()->where('club_id',$clubId)
            ->where('tag',3)
            ->where('is_default', 1)
            ->first();

        if (empty($freePayment)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-找不到二维码活动缴费方案',['clubId' => $clubId]);
            throw new Exception(config('error.Payment.2105'),'2105');
        }

        if ($student->sales_id > 0) {
            $sales = ClubSales::notDelete()->find($student->sales_id);
        } else {
            $sales = Student::getDefaultStuData($clubId, 3);
        }

        if (empty($sales)) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-销售员不存在',['salesId' => $student->sales_id]);
            throw new Exception(config('error.Student.1683'),'1683');
        }

        try {
            for ($i=0;$i<$tryRewardNum;$i++) {
                $stuPayment = $this->addPaymentsForStudent($student->from_stu_id,$clubId,$freePayment,$sales,$rewardRecordId);
                $this->addTicketsForStudent($stuPayment,$student->from_stu_id,$clubId,$freePayment);
            }
        } catch (Exception $e) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-给推荐学员增加缴费方案和券操作异常',['msg' => $e->getMessage()]);
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 给学员增加体验缴费方案
     * @param $stuId
     * @param $clubId
     * @param ClubPayment $freePayment
     * @param ClubSales $sales
     * @param $rewardRecordId
     * @return ClubStudentPayment
     * @throws \Throwable
     */
    public function addPaymentsForStudent($stuId,$clubId,ClubPayment $freePayment,ClubSales $sales,$rewardRecordId)
    {
        $stuPayment = new ClubStudentPayment();
        $stuPayment->student_id = $stuId;
        $stuPayment->club_id = $clubId;
        $stuPayment->payment_id = $freePayment->id;
        $stuPayment->payment_name = $freePayment->name;
        $stuPayment->payment_tag_id = $freePayment->tag;
        $stuPayment->payment_class_type_id = $freePayment->type;
        $stuPayment->course_count = $freePayment->course_count;
        $stuPayment->pay_fee = $freePayment->price;
        $stuPayment->equipment_issend = 0;
        $stuPayment->payment_date = Carbon::now()->toDateString();
        $stuPayment->channel_type = 4;
        $stuPayment->expire_date = Carbon::now()->addCentury(1)->toDateString();
        $stuPayment->sales_id = $sales->id;
        $stuPayment->sales_dept_id = $sales->sales_dept_id;
        $stuPayment->reserve_record_id = $rewardRecordId;
        $stuPayment->saveOrFail();

        return $stuPayment;
    }

    /**
     * 给学员添加课程券
     * @param ClubStudentPayment $stuPayment
     * @param $stuId
     * @param $clubId
     * @param $freePayment
     * @throws \Throwable
     */
    public function addTicketsForStudent($stuPayment,$stuId,$clubId,$freePayment)
    {
        $tickets = new ClubCourseTickets();
        $tickets->payment_id = $stuPayment->id;
        $tickets->club_id = $clubId;
        $tickets->student_id = $stuId;
        $tickets->expired_date = Carbon::now()->addMonth($freePayment->use_to_date)->toDateString();
        $tickets->status = 2;
        $tickets->reward_type = 1;
        $tickets->saveOrFail();
    }

    /**
     * 追回赠送的体验奖励课时
     * @param ClubStudent $student
     * @param ClubRecommendReserveRecord $reserveRecords
     * @param ClubRecommendRewardRecord $rewardRecords
     * @throws Exception
     * @throws \Throwable
     */
    public function cancelTryRewardToRecommendStudent(ClubStudent $student,ClubRecommendReserveRecord $reserveRecords,ClubRecommendRewardRecord $rewardRecords)
    {
        //奖励处于已发放，执行追回逻辑
        if ($reserveRecords->recommend_status == 2 && $rewardRecords->settle_status == 2) {
            try {
                $reserveRecords->recommend_status = 1;
                $reserveRecords->saveOrFail();

                $rewardRecords->settle_status = 1;
                $rewardRecords->saveOrFail();

                $stuPaymentIds = ClubStudentPayment::notDelete()
                    ->where('reserve_record_id',$rewardRecords->id)
                    ->pluck('payment_id');

                if ($stuPaymentIds->isEmpty()) return;

                $stuTickets = ClubCourseTickets::notDelete()
                    ->whereIn('payment_id',$stuPaymentIds)
                    ->where('status',2)
                    ->get();

                $giveBackCount = collect($stuTickets)->count();

                if ($giveBackCount > 0) {
                    $student->left_course_count = $student->left_course_count - $giveBackCount;
                    $student->saveOrFail();

                    ClubCourseTickets::notDelete()
                        ->whereIn('payment_id',$stuPaymentIds)
                        ->where('status',2)
                        ->update(['is_delete' => 1]);

                    ClubStudentPayment::notDelete()
                        ->where('reserve_record_id',$rewardRecords->id)
                        ->update(['is_delete' => 1]);
                }
            } catch (Exception $e) {
                Log::setGroup('RecommendError')->error('推广奖励记录异常（更改出勤签到）-追回奖励操作失败',['msg' => $e->getMessage()]);
                throw new Exception($e->getMessage(),$e->getCode());
            }
        }
    }

    /**
     * 预约非外勤第一次签到
     * @param $signId
     * @param $status
     * @param $class
     * @param $courseId
     * @param $ticket
     * @param $courseSign
     * @param $student
     * @param $clubId
     * @param $operateUserId
     * @param ClubCourse $course
     * @return array
     */
    public function firstSignForSubscribeWithNotOutDuty($signId, $status, $class, $courseId, $ticket, $courseSign, $student, $clubId, $operateUserId, ClubCourse $course)
    {
        try {
            DB::transaction(function () use ($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$clubId,$operateUserId,$course) {
                $this->changeCourseSign($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$operateUserId,$course);
            });
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }
    }

    /**
     * 预约非外勤非再次签到（更改签到）
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $status
     * @param $operateUserId
     * @param ClubCourse $course
     * @throws Exception
     */
    public function notFirstSignForSubscribeWithNotOutDuty(ClubCourseSign $courseSign,ClubStudent $student,$clubId,$status,$operateUserId, ClubCourse $course)
    {
        try {
            DB::transaction(function () use ($courseSign,$student,$clubId,$status,$operateUserId,$course) {
                // 更新签到数据
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();

                // 缺勤算未体验
                if ($courseSign->sign_status == 1 && $status == 2) {
                    $student->ex_status = 1;
                    $student->saveOrFail();
                }
            });
        } catch (Exception $e) {
            throw new Exception($e->getCode(),$e->getMessage());
        }
    }

    /**
     * 非预约非外勤首次签到
     * @param $signId
     * @param $status
     * @param ClubClass $class
     * @param ClubCourse $course
     * @param ClubCourseTickets $ticket
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $salesId
     * @param $operateUserId
     * @throws Exception
     */
    public function firstSignWithNotOutDuty($signId, $status, ClubClass $class, ClubCourse $course, ClubCourseTickets $ticket, ClubCourseSign $courseSign, ClubStudent $student, $clubId, $salesId, $operateUserId)
    {
        try {
            // 出勤、缺勤、事假扣课程券、课时、销课
            DB::transaction(function () use ($ticket, $clubId, $student, $courseSign,$status, $signId, $class, $course,$salesId,$operateUserId) {
                if (in_array($status, [1, 2, 3])) {
                    // 更新课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 减少课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 添加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                }

                // 更新签到状态
                $courseSign->class_id = $class->id;
                $courseSign->course_id = $course->id;
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->is_used = 1;
                $courseSign->class_type_id = $class->type;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 非预约非外勤再次签到
     * @param $student
     * @param ClubCourseSign $courseSign
     * @param ClubCourse $course
     * @param $status
     * @param $signId
     * @param $class
     * @param $clubId
     * @param $stuPayIdArr
     * @param $operateUserId
     * @throws Exception
     */
    public function notFirstSignWithNotOutDuty($student, ClubCourseSign $courseSign, ClubCourse $course, $status, $signId, $class, $clubId, $stuPayIdArr, $operateUserId)
    {
        //1:出勤、2:缺勤、3:事假扣课程券、课时、销课
        //4:病假、5:Pass、6:AutoPass、7冻结时，返还课时数、课程券、销课

        try {
            DB::transaction(function () use ($courseSign, $course, $status, $signId, $student, $class, $stuPayIdArr, $clubId, $operateUserId) {
                // 更改状态为病假、Pass、AutoPass、冻结时，返还课时数、课程券、销课
                if (in_array($courseSign->sign_status,[4, 5, 6, 7]) && in_array($status, [1,2,3])) {
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $class->type);
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 更新课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 减少课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 添加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                } elseif (in_array($courseSign->sign_status,[1,2,3]) && in_array($status, [4,5,6,7])) {
                    $ticket = ClubCourseTickets::where('sign_id', $signId)->first();
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 去除销课收入
                    ClubIncomeSnapshot::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('is_delete', 0)
                        ->update(['is_delete' => 1]);

                    // 返还课程券
                    ClubCourseTickets::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('course_id', $course->id)
                        ->where('class_id', $class->id)
                        ->update([
                            'course_id' => 0,
                            'class_id' => 0,
                            'sign_id' => 0,
                            'status' => 2
                        ]);

                    // 增加课时数
                    ClubStudent::where('id', $student->id)->increment('left_course_count');
                } else {
                    //暂时没有其他的，如果有在加逻辑
                }

                // 更新签到状态
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 更改课程签到
     * @param $signId
     * @param $status
     * @param ClubClass $class
     * @param $courseId
     * @param ClubCourseTickets $ticket
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $operateUserId
     * @param $course
     * @throws \Throwable
     */
    public function changeCourseSign($signId,$status,ClubClass $class,$courseId,ClubCourseTickets $ticket,ClubCourseSign $courseSign,ClubStudent $student, $operateUserId, $course)
    {
        // 更新签到状态
        $courseSign->class_id = $class->id;
        $courseSign->course_id = $courseId;
        $courseSign->sign_status = $status;
        $courseSign->sign_date = $course->day;
        $courseSign->is_used = 1;
        $courseSign->class_type_id = $class->type;
        $courseSign->operate_user_id = $operateUserId;
        $courseSign->saveOrFail();

        // 更新课程券
        $ticket->course_id = $courseId;
        $ticket->class_id = $class->id;
        $ticket->sign_id = $signId;
        $ticket->class_type_id = $class->type;
        $ticket->status = 1;
        $ticket->saveOrFail();

        // 更新课时数
        ClubStudent::where('id', $student->id)->decrement('left_course_count');
        if ($status == 1) {
            $student->ex_status = 2;
            $student->saveOrFail();
        }
    }

    /**
     * 预约外勤第一次签到
     * @param $signId
     * @param $status
     * @param $class
     * @param $courseId
     * @param $ticket
     * @param $courseSign
     * @param $student
     * @param $clubId
     * @param $operateUserId
     * @param ClubCourse $course
     * @throws Exception
     */
    public function firstSignForSubscribeWithOutDuty($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$clubId,$operateUserId,ClubCourse $course)
    {
        try {
            DB::transaction(function () use ($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$clubId,$operateUserId,$course) {
                $this->changeCourseSign($signId,$status,$class,$courseId,$ticket,$courseSign,$student,$operateUserId,$course);
            });
        } catch (Exception $e) {
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 预约外勤再次签到
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $status
     * @param $operateUserId
     * @param $course
     * @throws Exception
     */
    public function notFirstSignForSubscribeWithOutDuty(ClubCourseSign $courseSign,ClubStudent $student,$clubId,$status,$operateUserId, $course)
    {
        try {
            DB::transaction(function () use ($courseSign,$student,$clubId,$status,$operateUserId, $course) {
                // 更新签到数据
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();

                // 缺勤算未体验
                if ($courseSign->sign_status == 1 && $status == 2) {
                    $student->ex_status == 1;
                    $student->saveOrFail();
                }
            });
        } catch (Exception $e) {
            throw new Exception($e->getCode(),$e->getMessage());
        }
    }

    /**
     * 非预约外勤首次签到
     * @param $signId
     * @param $status
     * @param ClubClass $class
     * @param ClubCourse $course
     * @param ClubCourseTickets $ticket
     * @param ClubCourseSign $courseSign
     * @param ClubStudent $student
     * @param $clubId
     * @param $salesId
     * @param $operateUserId
     * @throws Exception
     */
    public function firstSignWithOutDuty($signId,$status,ClubClass $class,ClubCourse $course,ClubCourseTickets $ticket,ClubCourseSign $courseSign,ClubStudent $student,$clubId,$salesId,$operateUserId)
    {
        try {
            // 出勤、缺勤、事假扣课程券、课时、销课
            DB::transaction(function () use ($ticket, $clubId, $student, $class, $courseSign,$status, $signId, $course,$salesId,$operateUserId) {
                if (in_array($status, [1, 2, 3])) {
                    // 减少课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 减少课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 增加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                }

                // 更新签到状态
                $courseSign->class_id = $class->id;
                $courseSign->course_id = $course->id;
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->is_used = 1;
                $courseSign->class_type_id = $class->type;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 非预约外勤再次签到
     * @param $student
     * @param ClubCourseSign $courseSign
     * @param ClubCourse $course
     * @param $status
     * @param $signId
     * @param ClubClass $class
     * @param $clubId
     * @param $operateUserId
     * @throws Exception
     */
    public function notFirstSignWithOutDuty($student, ClubCourseSign $courseSign, ClubCourse $course, $status, $signId, ClubClass $class, $clubId,$operateUserId)
    {
        //1:出勤、2:缺勤、3:事假扣课程券、课时、销课
        //4:病假、5:Pass、6:AutoPass、7冻结时，返还课时数、课程券、销课

        try {
            DB::transaction(function () use ($courseSign, $course, $status, $signId, $student, $class,$clubId,$operateUserId) {
                // 更改状态为病假、Pass、AutoPass、冻结时，返还课时数、课程券、销课
                if (in_array($courseSign->sign_status,[4, 5, 6, 7]) && in_array($status, [1,2,3])) {
                    $ticket = Classes::getStudentSignTicket($clubId, $student->id, $class->type);
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 更新课程券
                    $ticket->course_id = $course->id;
                    $ticket->class_id = $class->id;
                    $ticket->sign_id = $signId;
                    $ticket->class_type_id = $class->type;
                    $ticket->status = 1;
                    $ticket->saveOrFail();

                    // 更新课时数
                    $student->left_course_count = $student->left_course_count - 1;
                    if ($status == 1) {
                        $student->is_freeze == 0;
                    }
                    $student->saveOrFail();

                    // 添加销课收入
                    $income = new ClubIncomeSnapshot();
                    $income->ticket_id = $ticket->id;
                    $income->student_id = $student->id;
                    $income->sign_id = $signId;
                    $income->course_id = $course->id;
                    $income->class_id = $class->id;
                    $income->class_type_id = $class->type;
                    $income->venue_id = $class->venue_id;
                    $income->club_id = $clubId;
                    $income->course_date = $course->day;
                    $income->payment_id = $ticket->payment_id;
                    $income->money = $ticket->unit_price;
                    $income->sales_id = $salesId ? $salesId : 0;
                    $income->saveOrFail();
                } elseif (in_array($courseSign->sign_status,[1,2,3]) && in_array($status, [4,5,6,7])) {
                    $ticket = ClubCourseTickets::where('sign_id', $signId)->first();
                    $salesId = ClubStudentPayment::where('id', $ticket->payment_id)->value('sales_id');

                    // 去除销课收入
                    ClubIncomeSnapshot::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('is_delete', 0)
                        ->update(['is_delete' => 1]);

                    // 返还课程券
                    ClubCourseTickets::where('sign_id', $signId)
                        ->where('student_id', $student->id)
                        ->where('course_id', $course->id)
                        ->where('class_id', $class->id)
                        ->update([
                            'course_id' => 0,
                            'class_id' => 0,
                            'sign_id' => 0,
                            'status' => 2
                        ]);

                    // 返还课时数
                    ClubStudent::where('id', $student->id)->increment('left_course_count');
                } else {
                    //暂时没有其他的，如果有在加逻辑
                }

                // 更新签到状态
                $courseSign->sign_status = $status;
                $courseSign->sign_date = $course->day;
                $courseSign->operate_user_id = $operateUserId;
                $courseSign->saveOrFail();
            });
        } catch (Exception $e) {
            throw new Exception(config('error.sign.2402'),'2402');
        }
    }

    /**
     * 给推荐的学员增加课时数
     * @param $stuId
     * @param $tryRewardNum
     * @throws Exception
     */
    public function addCourseCountToStudent($stuId,$tryRewardNum)
    {
        $clubStudent = ClubStudent::notDelete()->find($stuId);
        if (empty($clubStudent)) return;

        $clubStudent->left_course_count = $clubStudent->left_course_count + $tryRewardNum;

        if ($clubStudent->status == 3) {//公海库学员则需要将状态变为非正式学员
            $defaultVenue = Student::getDefaultStuData($clubStudent->club_id,1);
            $defaultClass = Student::getDefaultStuData($clubStudent->club_id,2);
            $defaultSales = Student::getDefaultStuData($clubStudent->club_id,3);

            if (empty($defaultVenue) || empty($defaultClass) || empty($defaultSales)) {
                throw new Exception(config('error.common.1013'),'1013');
            }

            $clubStudent->venue_id = $defaultVenue->id;
            $clubStudent->sales_id = $defaultSales->id;
            $clubStudent->sales_name = $defaultSales->sales_name;
            $clubStudent->main_class_id = $defaultClass->id;
            $clubStudent->main_class_name = $defaultClass->name;

            $clubStudent->status = 2;
        }

        $clubStudent->saveOrFail();
    }

    /**
     * 上传病假单图片
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function uploadSickLeaveImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'signId' => 'required|numeric',
            'imgKey' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 签到记录不存在
        $courseSign = ClubCourseSign::find($input['signId']);
        if (empty($courseSign)) {
            return returnMessage('1412', config('error.class.1412'));
        }

        $imgArr = explode(',', $input['imgKey']);

        DB::transaction(function () use ($input, $imgArr) {
            foreach ($imgArr as $value) {
                $stuSickImg = new ClubCourseSignSickImage();
                $stuSickImg->club_id = $input['user']['club_id'];
                $stuSickImg->sign_id = $input['signId'];
                $stuSickImg->img_key = $value;
                $stuSickImg->saveOrFail();
            }
        });

        return returnMessage('200', '');
    }

    /**
     * 获取病假单图片
     * @param Request $request
     * @return array
     */
    public function getSickLeaveImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'signId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 签到记录不存在
        $courseSign = ClubCourseSign::find($input['signId']);
        if (empty($courseSign)) {
            return returnMessage('1412', config('error.class.1412'));
        }

        $stuSickImg = ClubCourseSignSickImage::where('club_id', $input['user']['club_id'])
            ->where('sign_id', $input['signId'])
            ->get();

        $list['result'] = $stuSickImg->transform(function ($items) {
            $arr['imgId'] = $items->id;
            $arr['imgUrl'] = env('IMG_DOMAIN').$items->img_key;
            $arr['imgKey'] = $items->img_key;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 删除病假单图片
     * @param Request $request
     * @return array
     */
    public function deleteSickLeaveImg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'imgId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $stuSickImg = ClubCourseSignSickImage::find($input['imgId']);
        $stuSickImg->is_delete = 1;
        $stuSickImg->saveOrFail();

        return returnMessage('200', '');
    }
}