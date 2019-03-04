<?php

namespace App\Api\Controllers\Course;

use App\Model\ClubClass\ClubClass;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourseCoach\ClubCourseCoach;
use App\Model\ClubCourseOperation\ClubCourseOperation;
use App\Model\ClubClass\ClubClassTime;
use App\Model\ClubCourse\ClubCourseSign;
use App\Services\Common\CommonService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;


class CourseController extends Controller
{
    private $user;

    public function __construct()
    {
        try {
            //获取该用户
            $this->user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $exception) {

        }
    }

    //(1)获取该账号对应的所有场馆
    public function allVenue()
    {
        $data = \DB::table('club_venue')
            ->where(['status' => 1, 'club_id' => $this->user->club_id])
            ->get(['id', 'name'])
            ->toArray();

        if ($data) array_unshift($data, ['id' => 0, 'name' => '全部场馆']);

        return returnMessage('200', '获取成功', $data);
    }

    //(2)获取 该账号 某个场馆下的所有班级 status 1=生效  is_delete 0未删除
    public function getClassByVenue(Request $request)
    {
        $venue_id = $request->post('venueId');
        $data = \DB::table('club_class')
            ->where(['status' => 1, 'is_delete' => 0, 'club_id' => $this->user->club_id])
            ->where(function ($query) use ($venue_id) {
                if ($venue_id) {
                    $query->where('venue_id', $venue_id);
                }
            })
            ->get(['id', 'name'])
            ->toArray();

        return returnMessage('200', '获取成功', $data);
    }

    //(3)课程列表
    public function courseList(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'nullable|array',
            'date' => 'required|array', //第一个值开始日期  第二个值结束日期
            'week' => 'nullable|array', //数组
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        if ($data['date'][1] < $data['date'][0]) {
            return returnMessage('2001', '日期开始时间应不小于结束时间');
        }

        $course = \DB::table('club_course as a')
            ->leftJoin('club_course_operation as d', 'd.course_id', '=', 'a.id')
            ->leftJoin('club_class as c', 'c.id', '=', 'a.class_id')
            ->leftJoin('club_venue as b', 'b.id', '=', 'a.venue_id')
            ->select(['a.id', 'a.day', 'a.start_time', 'a.end_time', 'c.name as className',
                'b.name as venueName', 'a.coach_name', 'a.week', 'd.remark', 'd.operation_sales_name', 'd.operation_time'])
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("a.venue_id", $val);
                                break;
                            case "classId":
                                $query->whereIn("a.class_id", $val);
                                break;
                            case "date":
                                $query->whereBetween('a.day', $val);
                                break;
                            case "week":
                                $query->whereIn($key, $val);
                                break;
                        }
                    }
                }
            })
            ->where(['a.status' => 1, 'a.is_delete' => 0, 'a.club_id' => $this->user->club_id])
            ->orderBy('a.day', 'desc')
            ->orderBy('a.start_time','desc')
            ->orderBy('a.id','desc')
            ->get()
            ->toArray();

        $data = [];
        array_walk($course, function ($model) use (&$data) {
            $item = [
                'id' => $model->id,
                'time' => $model->start_time . '-' . $model->end_time,
                'className' => $model->className,
                'venueName' => $model->venueName,
                'coachName' => $model->coach_name,
                'coachDatails' => ClubCourseCoach::where(['course_id' => $model->id, 'status' => 1, 'is_delete' => 0])
                    ->orderBy('created_at', 'desc')
                    //->take(4)
                    ->get(['coach_id', 'coach_name', \DB::raw("left(created_at, 10) as date")])
                    ->toArray(),
                'remark' => $model->remark,
                'remarkDetails' => [
                    'remark' => $model->remark,
                    'name' => $model->operation_sales_name,
                    'date' => $model->operation_time
                ],
                'week' => $model->week
            ];

            $data[$model->day][] = $item;
        });

        $list = [];
        array_walk($data, function ($k, $v) use (&$list) {
            $list[] = [
                'date' => $v,
                'week' => $k[0]['week'],
                'total' => count($k),
                'list' => $k
            ];
        });

        return returnMessage('200', '获取成功', ['total' => count($course), 'list' => $list]);
    }

    //(4)编辑备注
    public function editRemark(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'courseId' => 'required|numeric',
            'remark' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }
        $ClubStudent = ClubCourse::find($data['courseId']);
        if (!$ClubStudent) {
            return returnMessage('2001', '该记录不存在');
        }

        try {
            $result = ClubCourseOperation::updateOrCreate(
                [
                    'course_id' => $data['courseId']
                ],
                [
                    'operation_sales_id' => $this->user->id ?? 0,
                    'operation_sales_name' => $this->user->username ?? '未知',
                    'remark' => $data['remark'],
                    'operation_time' => date('Y-m-d H:i:s'),
                    'created_at' => date('Y-m-d H:i:s')
                ]
            );
            if (!$result) throw new \Exception('操作失败');

            return returnMessage('200', '操作成功');
        } catch (\Exception $exception) {
            return returnMessage('2001', $exception->getMessage());
        }
    }

    //(5)根据用户所在的俱乐部获取所有的教练
    public function getCoachByUserClub()
    {
        $club_id = $this->user->club_id ?? '';
        if (!$this->user) {
            return returnMessage('2001', '用户没有登录');
        } elseif (!$club_id) {
            return returnMessage('2001', '用户所在的俱乐部为空,该账号无法进行该操作');
        }

        $data = \DB::table('club_coach')
            ->where('club_id', $club_id)
            ->where(['status' => 1, 'is_delete' => 0])
            ->get(['id', 'name']);

        return returnMessage('200', '获取成功', $data);
    }


    //(6)添加或修改教练
    public function handleCoach(Request $request)
    {
        $club_id = $this->user->club_id ?? '';

        $data = $request->all();
        $validate = \Validator::make($data, [
            'courseId' => 'required|numeric',
            'coachId' => 'required|numeric',
            'oldCoachId' => 'nullable|numeric',
            'coachName' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }
        $ClubStudent = ClubCourse::find($data['courseId']);
        if (!$ClubStudent) {
            return returnMessage('2001', '该记录不存在');
        }

        $month = intval(substr($ClubStudent->day, 5, 2));

        $manage_cost = \DB::table('club_coach_manage_cost')
            ->where('club_id', $club_id)
            ->where([
                'year' => substr($ClubStudent->day, 0, 4),
                'month' => $month
            ])
            ->value('cost');

        //更新时 限制已经存在的记录
        $coach = ClubCourseCoach::where([
            'course_id' => $data['courseId'],
            'coach_id' => $data['coachId'],
            'status' => 1,
            'is_delete' => 0
        ])->first();
        if ($coach && isset($data['oldCoachId'])) {
            return returnMessage('2001', '该课程对应的该教练已经存在,请换一个');
        }

        try {
            //$data['oldCoachId'] 存在值时是更新
            $coach_id = $data['oldCoachId'] ?? $data['coachId'];
            $courseCoach = ClubCourseCoach::where('coach_id', $coach_id)->where('course_id', $data['courseId'])->first();
            if (!$courseCoach) {
                $courseCoach = new ClubCourseCoach();
            }
            $courseCoach->coach_id = $data['coachId'];
            $courseCoach->course_id = $data['courseId'];
            $courseCoach->class_id = $ClubStudent->class_id;
            $courseCoach->club_id = $ClubStudent->club_id;
            $courseCoach->coach_name = $data['coachName'];
            $courseCoach->manage_cost = $manage_cost;
            $courseCoach->save();
            /*
            $result = ClubCourseCoach::updateOrCreate(
                [
                    'course_id' => $data['courseId'],
                    'coach_id' => $coach_id
                ],
                [
                    'coach_id' => $data['coachId'],
                    'class_id' => $ClubStudent->class_id,
                    'club_id' => $ClubStudent->club_id,
                    'coach_name' => $data['coachName'],
                    'manage_cost' => $manage_cost,
                    'created_at' => date('Y-m-d H:i:s')
                ]
            );
            if (!$result) throw new \Exception('操作失败');
            */

            //ClubCourse 主表添加教练
            $course = ClubCourse::find($data['courseId']);
            $course->coach_id = $data['coachId'];
            $course->coach_name = $data['coachName'];
            $course->save();

            return returnMessage('200', '操作成功');
        } catch (\Exception $exception) {
            return returnMessage('2001', $exception->getMessage());
        }
    }

    //(7)根据班级及日期获取 可课程总数  已创建总数  已停课总数  新增场馆数/班级数  班级对应场馆
    public function getCourseTotal(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'classId' => 'required|array',    //所有班级id
            'month' => 'required|string',     //月份
            'venueId' => 'required|numeric'   //场馆id
        ]);

        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $venueNum = \DB::table('club_venue')
            ->where(['status' => 1, 'club_id' => $this->user->club_id ?? ''])
            ->count();
        $venueNum = !$data['venueId'] ? $venueNum : 1;

        $result = $this->getPatchCourseTotal($data);
        $result['canCreateTotal'] = count($result['createArr']) + $result['createdTotal'];
        unset($result['createArr'], $result['updateArr']);

        return returnMessage('200', '操作成功',
            array_merge(['venueTotal' => $venueNum, 'classTotal' => count($data['classId'])], $result));

    }

    //(8)批量创建整月课程
    public function patchCreateCourse(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'classId' => 'required|array',   //所有班级id
            'month' => 'required|string'     //字符串
        ]);

        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        if ($data['month'] < date('Y-m')) {
            return returnMessage('2001', '选择月份不可小于当前月份');
        }

        $values = $this->getPatchCourseTotal($data)['createArr'];

        $result = ClubCourse::insert($values);
        if ($result) {
            return returnMessage('200', '批量创建课程成功');
        } else {
            return returnMessage('2002', '批量创建课程失败');
        }
    }

    //(9)批量停课
    public function patchStopCourse(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'classId' => 'required|array',
            'month' => 'required|string' //月份
        ]);

        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $ids = $this->getPatchCourseTotal($data)['updateArr'];
        $res = ClubCourse::whereIn('id', $ids)->update(['status' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

        if ($res) {
            return returnMessage('200', '操作成功');
        } else {
            return returnMessage('200', '操作失败');
        }
    }

    //根据班级及日期获取 可课程总数  已创建总数  已停课总数
    private function getPatchCourseTotal(array $data)
    {
        //根据月份获取日期列表
        $BeginDate = date('Y-m-01', strtotime($data['month']));
        $dayList = get_d_list($BeginDate,
            date('Y-m-d', strtotime("$BeginDate +1 month -1 day")));

        $stopTotal = $createdTotal = 0;
        $createArr = $updateArr = [];

        foreach ($dayList as $val1) {
            foreach (array_filter($data['classId']) as $val2) {  //兼容0
                $ClubClass = ClubClass::find($val2);
                if (!$ClubClass) {
                    return returnMessage('2001', '俱乐部班级记录不存在');
                }

                $venue_id = $ClubClass->venue_id;

                if (ClubCourse::where([  //已停课总数
                    'venue_id' => $venue_id,
                    'class_id' => $val2,
                    'day' => $val1,
                    'status' => 0
                ])->first()
                ) {
                    $stopTotal += 1;
                }

                if (ClubCourse::where([  //已创建总数
                    'venue_id' => $venue_id,
                    'class_id' => $val2,
                    'day' => $val1
                ])->first()
                ) {
                    $createdTotal += 1;
                } else {
                    //根据日期获取星期
                    $week = date('w', strtotime($val1));
                    $week = ($week == 0) ? 7 : $week;  //周日为0 要转化
                    $club_class_time = ClubClassTime::where(['day' => $week, 'class_id' => $val2])->first();
                    if ($club_class_time && $club_class_time->start_time && $club_class_time->end_time) {
                        //实际能创建可课程数 精确到秒
                        $dateDay = $val1.' '.$club_class_time->start_time;
                        if (strtotime($dateDay) > time()) {
                            $createArr[] = [
                                'club_id' => $ClubClass->club_id,
                                'venue_id' => $venue_id,
                                'class_id' => $val2,
                                'class_type_id' => $ClubClass->type,
                                'day' => $val1,
                                'week' => $week,
                                'start_time' => $club_class_time->start_time,
                                'end_time' => $club_class_time->end_time,
                                'created_at' => date('Y-m-d H:i:s')
                            ];
                        }
                    }
                }

                if ($res = ClubCourse::where([  //可批量停课值id
                    'venue_id' => $venue_id,
                    'class_id' => $val2,
                    'day' => $val1,
                    'status' => 1
                ])->first()
                ) {
                    $updateArr[] = $res->id;
                }
            }
        }

        return [
            'createdTotal' => $createdTotal,
            'stopTotal' => $stopTotal,
            'createArr' => $createArr,
            'updateArr' => $updateArr
        ];
    }

    //(10)课程概况列表汇总
    public function summaryCourse(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'nullable|array',
            'date' => 'required|array', //第一个值开始日期  第二个值结束日期
            'week' => 'nullable|array', //数组
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $course = \DB::table('club_course as a')
            ->leftJoin('club_course_operation as d', 'd.course_id', '=', 'a.id')
            ->leftJoin('club_class as c', 'c.id', '=', 'a.class_id')
            ->leftJoin('club_venue as b', 'b.id', '=', 'a.venue_id')
            ->select(['a.id', 'a.day', 'a.start_time', 'a.end_time', 'c.name as className',
                'b.name as venueName', 'a.coach_name', 'a.week', 'd.remark', 'a.status', 'a.class_id'])
            ->where(function ($query) use ($data) {

                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("a.venue_id", $val);
                                break;
                            case "classId":
                                $query->whereIn("a.class_id", $val);
                                break;
                            case "date":
                                $query->whereBetween('day', $val);
                                break;
                            case "week":
                                $query->whereIn($key, $val);
                                break;
                        }
                    }
                }
            })
            ->where(['a.club_id' => $this->user->club_id, 'a.is_delete' => 0])
            ->orderBy('a.day','desc')
            ->orderBy('a.start_time','desc')
            ->orderBy('a.id','desc')
            ->get()
            ->toArray();

        $data = [];
        $CommonService = new CommonService();
        foreach ($course as $key=>$model) {
            //$SignModel = ClubCourseSign::where('course_id', $model->id);

            $count = ClubCourseSign::where(['course_id' => $model->id])->count();

            $count1 = ClubCourseSign::where(['course_id' => $model->id,'sign_status' => 1])->count();
            $count3 = ClubCourseSign::where(['course_id' => $model->id,'sign_status' => 3])->count();
            $count4 = ClubCourseSign::where(['course_id' => $model->id,'sign_status' => 4])->count();
            $count2 = ClubCourseSign::where(['course_id' => $model->id,'sign_status' => 2])->count();
            $count6 = ClubCourseSign::where(['course_id' => $model->id,'sign_status' => 6])->count();

            $countY = ClubCourseSign::where(['course_id' => $model->id,'sign_status' => 1])->count();

            $countT = ClubCourseSign::where(['course_id' => $model->id,'is_subscribe' => 1, 'sign_status' => 1])->count();

            $item = [
                'id' => $model->id,
                'time' => $model->start_time . '-' . $model->end_time,
                'className' => $model->className,
                'venueName' => $model->venueName,
                'coachName' => $model->coach_name,
                'coachDatails' => ClubCourseCoach::where(['course_id' => $model->id, 'status' => 1, 'is_delete' => 0])
                    ->orderBy('created_at', 'desc')
                    ->take(4)
                    ->get(['id as clubCourseCoachId', 'coach_name', \DB::raw("left(created_at, 10) as date")])
                    ->toArray(),
                'remark' => $model->remark,
                'status' => $model->status,                         //状态 0=停课;1=上课
                'week' => $model->week,                             //星期
                'classNum' => $count . '人',           //上课人数
                'lastAttendance' => ClubCourseSign::where('course_id', $model->id - 1)->count() . '人',     //上次出勤
                'rangeAge' => $CommonService->getClassStudentMinAndMaxAge($model->class_id)['age_stage'], //年龄
                //预约与体验
                'bookingExperience' =>
                    $countY . '人/' .
                    $countT . '人',
                //出勤/事假/病假/缺勤
                'sigeAttendance' =>
                    $count1 . '人/'.
                    $count3 . '人' .
                    $count4 . '人' .
                    $count2 . '人' .
                    $count6 . '人' ,
            ];

            $data[$model->day][] = $item;
        }

        $list = [];
        $coachTotal = $subscribeTotal = $experienceTotal = $attendanceTotal = 0;
        foreach ($data as $v => $k) {

            $course_id = array_column($k, 'id');


            $coach = \DB::table('club_course_coach as a')
                ->leftJoin('club_course as b', 'b.id', '=', 'a.course_id')
                ->whereIn('a.course_id', $course_id)
                ->count();
            $coachTotal += $coach;

            $club_course_sign = \DB::table('club_course_sign as a')
                ->leftJoin('club_course as b', 'b.id', '=', 'a.course_id')
                ->whereIn('a.course_id', $course_id);

            $subscribe = $club_course_sign->where(['is_subscribe' => 1])->count();
            $subscribeTotal += $subscribe;

            $experience = $club_course_sign->where(['is_subscribe' => 1, 'sign_status' => 1])->count();
            $experienceTotal += $experience;


            // 如果 $k 有多个元素，则循环取出id

            if (count($k) > 1){
                foreach ($k as $subKey => $subValue){
                    $listKey[] = $subValue['id'];
                }
            }else{
                $listKey = $course_id;
            }

            $attendance = ClubCourseSign::whereIn('course_id', $listKey)->whereNotNull('sign_status')->where('sign_status','<>',0)->where(['course_day' => $v])->count();

            $attendanceTotal += $attendance;

            $list[] = [
                'date' => $v,
                'week' => $k[0]['week'],
                'course' => count($k),
                'coach' => $coach,
                'subscribe' => $subscribe,
                'experience' => $experience,
                'attendance' => $attendance,
                'list' => $k
            ];
        }


        return returnMessage('200', '获取成功', [
                'total' => count($course),
                'coachTotal' => $coachTotal,
                'subscribeTotal' => $subscribeTotal,
                'experienceTotal' => $experienceTotal,
                'attendanceTotal' => $attendanceTotal,
                'list' => $list]
        );

    }


    //(11)获取所有渠道来源
    public function allChannel()
    {
        $data = \DB::table('club_channel')
            ->where(['is_delete' => 0])
            ->get(['id', 'channel_name']);

        return returnMessage('200', '获取成功', $data);
    }

    //(12)获取本俱乐部的所有销售员
    public function allSales()
    {
        $data = \DB::table('club_sales')
            ->where(['status' => 1, 'is_delete' => 0, 'club_id' => $this->user->club_id])
            ->get(['id', 'sales_name']);

        return returnMessage('200', '获取成功', $data);
    }


    //(13)课程总统计
    public function courseTotal(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'date' => 'nullable|array',//第一个值开始日期  第二个值结束日期
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $currentPage = $data['currentPage'] ?? 1; //页码
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $data = \DB::table('club_course as a')
            ->leftJoin('club_class as c', 'c.id', '=', 'a.class_id')
            //->innerJoin('club_student as e', 'c.id', '=', 'e.main_class_id')
            ->select(['a.id', 'c.name as className','a.week','a.start_time as startTime','a.end_time as endTime','c.id as mainClassId', 'a.day'])
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("a.venue_id", $val);
                                break;
                            case "classId":
                                $query->where("a.class_id", $val);
                                break;
                            case "date":
                                $query->whereBetween("a.day", $val);
                                break;
                            case "week":
                                $query->whereIn($key, $val);
                                break;
                        }
                    }
                }
            })
            ->where(['a.is_delete' => 0, 'a.club_id' => $this->user->club_id])
            ->orderBy('a.day', 'desc')
            ->orderBy('a.id', 'desc')

            ->paginate($data['pageSize'] ?? 10);

        $data = $data->toArray();

        if (isset($data['data'])){

            foreach ($data['data'] as $key => &$value){

                $minAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->min('age');

                $maxAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->max('age');

                $value->className = $value->className . ' ' . config("date.$value->week") . ' ' . $value->startTime . '~' . $value->endTime . ' ' . $minAge . '~' . $maxAge . '岁';

                //unset($value->week);
                unset($value->name);
                unset($value->startTime);
                unset($value->endTime);
                unset($value->mainClassId);
            }
            unset($value);

        }

        return returnMessage('200', '获取成功', $data);
    }

    //(14)教练总统计
    public function coachTotal(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'date' => 'nullable|array', //第一个值开始日期  第二个值结束日期  上课时间
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $currentPage = $data['currentPage'] ?? 1; //页码
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $data = \DB::table('club_course_coach as a')
            ->leftJoin('club_course as b', 'b.id', '=', 'a.course_id')
            ->leftJoin('club_class as c', 'c.id', '=', 'a.class_id')
            ->leftJoin('club_student as e', 'c.id', '=', 'e.main_class_id')
            ->leftJoin('club_venue as d', 'd.id', '=', 'b.venue_id')
            ->select(['a.coach_id as coachId', 'a.coach_name as coachName','c.name as className','b.week','b.start_time as startTime','b.end_time as endTime','e.main_class_id as mainClassId', 'd.name as venueName', 'c.name', 'b.day', 'b.id'])
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("d.id", $val);
                                break;
                            case "classId":
                                $query->where("c.id", $val);
                                break;
                            case "date":
                                $query->whereBetween("b.day", $val);
                                break;
                        }
                    }
                }
            })
            ->where(['a.club_id' => $this->user->club_id, 'a.is_delete' => 0, 'b.is_delete' => 0])
            ->orderBy('b.day', 'desc')
            ->orderBy('b.id', 'desc')
            ->paginate($data['pageSize'] ?? 10);

        $data = $data->toArray();

        if (isset($data['data'])){

            foreach ($data['data'] as $key => &$value){

                $minAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->min('age');

                $maxAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->max('age');

                $value->name = $value->className . ' ' . config("date.$value->week") . ' ' . $value->startTime . '~' . $value->endTime . ' ' . $minAge . '~' . $maxAge . '岁';

                unset($value->week);
                //unset($value->className);
                unset($value->startTime);
                unset($value->endTime);
                unset($value->mainClassId);
                unset($venueName);
            }
            unset($value);

        }

        return returnMessage('200', '获取成功', $data);
    }

    //(15)预约总统计
    public function subscribeTotal(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'date' => 'nullable|array', //第一个值开始日期  第二个值结束日期
            'channelId' => 'nullable|numeric',
            'salesId' => 'nullable|numeric',
            'subscribeStatus' => 'nullable|numeric|in:0,1,2,3', //出勤状态
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $currentPage = $data['currentPage'] ?? 1; //页码
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $data = \DB::table('club_student_subscribe as a')
/*            ->leftJoin('club_student as b', 'b.id', '=', 'a.student_id')*/
            ->leftJoin('club_sales as c', 'c.id', '=', 'a.sales_id')
            ->leftJoin('club_channel as d', 'd.id', '=', 'a.channel_id')
            ->leftJoin('club_class as e', 'e.id', '=', 'a.class_id')
            ->leftJoin('club_course as f', 'f.id', '=', 'a.course_id')
            ->leftJoin('club_venue as g', 'g.id', '=', 'f.venue_id')
            ->select(['a.id','a.student_id as studentId', 'e.name as className','f.week','f.start_time as startTime','f.end_time as endTime','d.channel_name as channelName', 'c.sales_name as salesName',
                'e.name', 'f.day', 'a.subscribe_status as subscribeStatus', 'a.created_at as createdAt'])
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("g.id", $val);
                                break;
                            case "classId":
                                $query->where("e.id", $val);
                                break;
                            case "date":
                                $query->whereBetween("f.day", $val);
                                break;
                            case "channelId":
                                $query->where("a.channel_id", $val);
                                break;
                            case "salesId":
                                $query->where("a.sales_id", $val);
                                break;
                            case "subscribeStatus":
                                $query->where("a.subscribeStatus", $val);
                                break;
                        }
                    }
                }
            })
            ->where(['a.club_id' => $this->user->club_id, 'a.is_delete' => 0, 'f.is_delete' => 0])
            ->orderBy('c.day', 'desc')
            ->orderBy('f.id', 'desc')
            ->paginate($data['pageSize'] ?? 10);

        $data = $data->toArray();

        foreach ($data['data'] as $key => &$value){

            $temp = \DB::table("club_student as a")
                ->leftJoin("club_student_payment as b",'a.id','=','b.student_id')
                ->select('a.id','a.name','a.age','a.main_class_id as mainClassId','b.payment_tag_id as payStatus')
                ->where('a.id',$value->studentId)
                ->first();

            if ($temp){

                $value->studentName = $temp->name;
                $value->age = $temp->age;
                $value->mainClassId = $temp->mainClassId;
                if ($temp->payStatus == 2) {
                    $value->payStatus = "已付款";
                }else{
                    $value->payStatus = "未付款";
                }

            }

        }
        unset($value);

        if (isset($data['data'])){
            foreach ($data['data'] as $key => &$value){

                $minAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->min('age');

                $maxAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->max('age');

                $value->name = $value->className . ' ' . config("date.$value->week") . ' ' . $value->startTime . '~' . $value->endTime . ' ' . $minAge . '~' . $maxAge . '岁';

                unset($value->week);
                unset($value->className);
                unset($value->startTime);
                unset($value->endTime);
                unset($value->mainClassId);
            }
            unset($value);
        }



        return returnMessage('200', '获取成功', $data);
    }

    //(16)体验总统计
    public function experienceTotal(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'date' => 'nullable|array', //第一个值开始日期  第二个值结束日期
            'channelId' => 'nullable|numeric',
            'salesId' => 'nullable|numeric',
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }

        $currentPage = $data['currentPage'] ?? 1; //页码
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $data = \DB::table('club_student_subscribe as a')
            ->leftJoin('club_student as b', 'b.id', '=', 'a.student_id')
            ->leftJoin('club_sales as c', 'c.id', '=', 'a.sales_id')
            ->leftJoin('club_channel as d', 'd.id', '=', 'a.channel_id')
            ->leftJoin('club_class as e', 'e.id', '=', 'a.class_id')
            ->leftJoin('club_course as f', 'f.id', '=', 'a.course_id')
            ->leftJoin('club_venue as g', 'g.id', '=', 'f.venue_id')
            ->leftJoin('club_course_operation as h', 'h.course_id', '=', 'f.id')
            ->select(['b.id', 'b.name', 'e.name as className','f.week','f.start_time as startTime','f.end_time as endTime','b.main_class_id as mainClassId','c.sales_name as salesName', 'd.channel_name as channelName', 'e.name',
                'h.remark', 'f.day', 'a.subscribe_status as subscribeStatus',
                'b.left_course_count as leftCount'])
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("g.id", $val);
                                break;
                            case "classId":
                                $query->where("a.class_id", $val);
                                break;
                            case "date":
                                $query->whereBetween("f.day", $val);
                                break;
                            case "channelId":
                                $query->where("a.channel_id", $val);
                                break;
                            case "salesId":
                                $query->where("a.sales_id", $val);
                                break;
                        }
                    }
                }
            })

            ->where(['a.subscribe_status' => 1, 'a.club_id' => $this->user->club_id,
                'a.is_delete' => 0, 'f.is_delete' => 0])
            ->orderBy('f.day', 'desc')
            ->orderBy('f.id', 'desc')

            ->paginate($data['pageSize'] ?? 10);


        $data = $data->toArray();

        if (isset($data['data'])){

            foreach ($data['data'] as $key => &$value){

                $minAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->min('age');

                $maxAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->max('age');

                $value->name = $value->className . ' ' . config("date.$value->week") . ' ' . $value->startTime . '~' . $value->endTime . ' ' . $minAge . '~' . $maxAge . '岁';

                unset($value->week);
                unset($value->className);
                unset($value->startTime);
                unset($value->endTime);
                unset($value->mainClassId);
            }
            unset($value);

        }

        return returnMessage('200', '获取成功', $data);
    }

    //(17)出勤总统计
    public function attendanceTotal(Request $request)
    {
        $data = $request->all();

        $validate = \Validator::make($data, [
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'date' => 'nullable|array', //第一个值开始日期  第二个值结束日期
            'channelId' => 'nullable|numeric',  //学员来源
            'salesId' => 'nullable|numeric',    //学员销售
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('2001', $validate->errors()->first());
        }


        $currentPage = $data['currentPage'] ?? 1; //页码
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });


        $data = \DB::table('club_course_sign as a')
            ->leftJoin('club_student as b', 'b.id', '=', 'a.student_id')
            ->leftJoin('club_course as c', 'c.id', '=', 'a.course_id')
            ->leftJoin('club_venue as ca', 'ca.id', '=', 'c.venue_id')
            ->leftJoin('club_course_operation as cb', 'cb.course_id', '=', 'c.id')
            ->leftJoin('club_class as d', 'd.id', '=', 'a.class_id')
            ->select(['b.id', 'a.student_id as studentId', 'd.name as className','c.week','c.start_time as startTime','c.end_time as endTime','b.main_class_id as mainClassId','b.sales_name as salesName', 'b.channel_name as channelName', 'd.name',
                'cb.remark', 'c.day', 'a.sign_status as signStatus', 'b.left_course_count as leftCount'])
            ->where(function ($query) use ($data) {
                foreach ($data as $key => $val) {
                    if ($val) {
                        switch ($key) {
                            case "venueId":
                                $query->where("ca.id", $val);
                                break;
                            case "classId":
                                $query->where("d.id", $val);
                                break;
                            case "date":
                                $query->whereBetween("c.day", $val);
                                break;
                            case "channelId":
                                $query->where("b.channel_id", $val);
                                break;
                            case "salesId":
                                $query->where("b.sales_id", $val);
                                break;
                        }
                    }
                }
            })
            ->where(['a.club_id' => $this->user->club_id, 'a.is_delete' => 0, 'c.is_delete' => 0,'b.status' => 1,'b.is_freeze' => 0])
            ->orderBy('c.day', 'desc')
            ->orderBy('b.id', 'desc')
            ->paginate($data['pageSize'] ?? 10);

        $data = $data->toArray();

        foreach ($data['data'] as $key => &$value){

            $temp = \DB::table("club_student as a")
                ->leftJoin("club_student_payment as b",'a.club_id','=','b.club_id')
                ->select('a.id','a.name','a.age','a.main_class_id as mainClassId','b.payment_tag_id as payStatus')
                ->where('a.id',$value->studentId)
                ->first();

            if ($temp){

                $value->studentName = $temp->name;
                $value->age = $temp->age;
                $value->mainClassId = $temp->mainClassId;
                if ($temp->payStatus == 2) {
                    $value->payStatus = "已付款";
                }else{
                    $value->payStatus = "未付款";
                }

            }

        }
        unset($value);

        foreach ($data['data'] as $key => &$value){

            $minAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->min('age');

            $maxAge = \DB::table("club_student")->where('main_class_id',$value->mainClassId)->max('age');

            $value->name = $value->className . ' ' . config("date.$value->week") . ' ' . $value->startTime . '~' . $value->endTime . ' ' . $minAge . '~' . $maxAge . '岁';

            unset($value->week);
            unset($value->className);
            unset($value->startTime);
            unset($value->endTime);
            unset($value->mainClassId);
        }
        unset($value);

        return returnMessage('200', '获取成功', $data);
    }

}
