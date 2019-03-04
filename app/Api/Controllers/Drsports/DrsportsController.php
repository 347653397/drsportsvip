<?php
namespace App\Api\Controllers\Drsports;

use App\Http\Controllers\Controller;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassImage\ClubClassImage;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseCoach\ClubCourseCoach;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudentCore\ClubStudentCore;
use App\Model\ClubSystem\ClubMessageApp;
use App\Model\ClubVenue\ClubVenueImage;
use App\Model\Common\ClubCity;
use App\Model\Common\ClubDistrict;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\Recommend\ClubRecommendRewardRecord;
use App\Services\Util\SmsService;
use function foo\func;
use Illuminate\Http\Request;
use App\Model\Club\Club;
use Carbon\Carbon;
use App\Model\ClubSystem\ClubExams;
use App\Model\ClubVenue\ClubVenue;
use App\Model\Common\ClubProvince;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Facades\Util\Common;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubClass\ClubClassTime;
use App\Facades\ClubStudent\Subscribe;
use App\Services\Payment\PaymentService;
use App\Facades\ClubStudent\Student;
use App\Model\ClubSystem\ClubExamsStudent;
use App\Services\Student\StudentService;
use App\Model\ClubErrorLog\ClubErrorLog;
use App\Facades\Util\Log;

class DrsportsController extends Controller
{

    CONST MAX_BIND_COUNT = 4;   //学员绑定上限

    /**
     * 获取测验基本信息
     * @param Request $request
     * @return array|\Illuminate\Database\Eloquent\Collection|\Illuminate\Database\Eloquent\Model|null|static|static[]
     */
    public function getExamBasic(Request $request)
    {

        $clubExam = ClubExams::with(['club','examItems'])->find($request->input('examId'));

        if (empty($clubExam)) {
            return returnMessage('1004', config('error.common.1004'));
        }

        $returnData = [
            'clubName' => $clubExam->club->name,
            'examName' => $clubExam->exam_name,
            'examDate' => $clubExam->exams_date,
            'examItems' => $clubExam->examItems->pluck('item_name')
        ];

        return returnMessage('200', '',$returnData);
    }

    /**
     * 俱乐部测验审核通过
     * @param Request $request
     * @return array
     */
    public function aggreeClubExam(Request $request)
    {
        $clubExam = ClubExams::notDelete()->find($request->input('examId'));

        Log::setGroup('Subscribe')->error('测验测试',['examId' => $request->input('examId')]);

        if (empty($clubExam)) {
            return returnMessage('1004', config('error.common.1004'));
        }

        $clubExam->status = 1;
        $clubExam->auditing_at = Carbon::now();

        try {
            $clubExam->saveOrFail();
        } catch (\Exception $e) {
            return returnMessage('1005', config('error.common.1005'));
        }

        return returnMessage('200');
    }

    /**
     * 俱乐部测验审核拒绝
     * @param Request $request
     * @return array
     */
    public function refuseClubExam(Request $request)
    {
        $clubExam = ClubExams::notDelete()->find($request->input('examId'));

        if (empty($clubExam)) {
            return returnMessage('1004', config('error.common.1004'));
        }

        if ($clubExam->status == 1) {
            return returnMessage('2001', config('error.sys.2001'));
        }

        $clubExam->status = -1;
        $clubExam->auditing_at = Carbon::now();

        try {
            $clubExam->saveOrFail();
        } catch (\Exception $e) {
            return returnMessage('1005', config('error.common.1005'));
        }

        return returnMessage('200');
    }

    /**
     * 获取所有俱乐部列表
     * @return array
     */
    public function getAllClubList()
    {
        $club = Club::valid()->notDelete()->select('id','name')->get();

        $arr = [];
        collect($club)->each(function ($item) use (&$arr) {
            array_push($arr,array(
                'id' => $item->id,
                'name' => $item->name
            ));
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 根据俱乐部ID获取俱乐部名称
     * @param Request $request
     * @return array
     */
    public function getClubName(Request $request)
    {
        $club = Common::getClubById($request->input('clubId'));

        if (empty($club)) {
            return returnMessage('1004', config('error.common.1004'));
        }

        return returnMessage('200','',['clubName' => $club->name]);
    }

    /**
     * 获取所有俱乐部所在区域
     * @return array
     */
    public function getAllClubArea()
    {
        $club = ClubVenue::valid()
            ->notDelete()
            ->showInApp()
            ->select('city_id','city','district_id','district')
            ->whereNotNull('city_id')
            ->whereNotNull('district_id')
            ->get();

        if (empty($club)) {
            return returnMessage('200', '',[]);
        }

        $areaData = [];

        collect($club)->each(function ($item) use (&$areaData) {
            if (collect($areaData)->has($item->city_id.'-'.$item->city)) {
                $citys = collect($areaData[$item->city_id.'-'.$item->city])->pluck('countryCode');
                if (!$citys->contains($item->district_id)) {
                    $areaData[$item->city_id.'-'.$item->city][] = [
                        'countryCode' => $item->district_id,
                        'countryName' => $item->district
                    ];
                }
            } else {
                $areaData[$item->city_id.'-'.$item->city][] = [
                    'countryCode' => $item->district_id,
                    'countryName' => $item->district
                ];
            }
        });

        $returnData = [];
        collect($areaData)->each(function ($item,$key) use (&$returnData) {
            $city = explode('-',$key);
            $returnData[] = [
                'cityCode' => $city[0],
                'cityName' => $city[1],
                'countryData' => $item
            ];
        });

        return returnMessage('200','',$returnData);
    }

    /**
     * 场馆详情
     * @param Request $request
     * @return array
     */
    public function venueDetail(Request $request)
    {
        $venue = ClubVenue::where('status',1)->with([
            'club',
            'classes' => function ($query) {
                return $query->where('status',1)->where('show_in_app',1);
            },
            'images' => function ($query) {
                return $query->where('is_show',1);
            }
        ])->find($request->input('veId'));

        if (empty($venue)) {
            return returnMessage('1301', config('error.venue.1301'));
        }

        $classData = [];
        collect($venue->classes)->each(function ($class) use (&$classData) {
            $classData[] = [
                'classId' => $class->id,
                'className' => $class->name,
                'classAgeStage' => $class->getClassStudentMinAndMaxAge($class->id)['age_stage'] . '岁',
                'courseTime' => $class->getClassTimeString($class['id']) ?: ''
            ];
        });

        $returnData = [
            'clubId' => $venue->club->id,
            'clubName' => $venue->club->name,
            'veUnitPrice' => $venue->price_in_app,
            'venues' => [
                'veId' => $venue->id,
                'veName' => $venue->name,
                'veLat' => Common::numberFormatFloat($venue->latitude),
                'veLng' => Common::numberFormatFloat($venue->longitude),
                'address' => $venue->address,
                'veMobile' => $venue->tel,
                'veDesc' => $venue->description.$venue->traffic_info,
                'veImg' => $venue->images->isEmpty() ? config('public.DEFAULT_VENUE_IMG') : Common::handleImg($venue->images[0]->file_path)
            ],
            'classData' => $classData,
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 获取场馆列表
     * @param Request $request
     * @return array
     */
    public function venueLists(Request $request)
    {
        $cityId = $request->input('cityCode');
        $countryId = $request->input('countryCode');
        $clubId = $request->input('clubId');
        $sportId = $request->input('sportId');
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $isPromote = $request->input('isPromote');
        $courseTypeId = $request->input('courseTypeId');
        $currentPage = $request->input('currentPage');
        $pagePerNum = $request->input('pagePerNum');

        $offset = ($currentPage - 1) * $pagePerNum;

        $venues = ClubVenue::select('*')
            ->when($cityId > 0,function ($query) use ($cityId) {
                return $query->where('city_id',$cityId);
            })
            ->when($countryId > 0 && $cityId != $countryId,function ($query) use ($countryId) {
                return $query->where('district_id',$countryId);
            })
            ->when($clubId > 0,function ($query) use ($clubId) {
                return $query->where('club_id',$clubId);
            })
            ->when($sportId > 0,function ($query) use ($sportId) {
                $venueIds = $this->getVenueIdsBySportsTypeId($sportId);
                return $query->whereIn('id',$venueIds);
            })
            ->when($courseTypeId > 0,function ($query) use ($courseTypeId) {
                $venueIds = $this->getVenueIdsByCourseTypeId($courseTypeId);
                return $query->whereIn('id',$venueIds);
            })
            ->when($lat && $lng,function ($query) use ($lat,$lng) {
                return $query->addSelect(DB::raw('st_distance (point (longitude, latitude),point('.$lng.','.$lat.') ) / 0.0111 as distance'));
            })
            ->with([
                'club:id,name',
                'classes' => function ($query) {
                    return $query->where('status',1)->where('show_in_app',1);
                },
                'images' => function ($query) {
                    return $query->notDelete()->where('is_show',1);
                }
            ])
            //->where('be_for_sale',$isPromote)
            ->valid()->showInApp()
            ->offset($offset)
            ->limit($pagePerNum)
            ->get();

        $venuesData = [];
        collect($venues)->each(function ($item) use (&$venuesData) {
            $venuesData[] = [
                'veId' => $item->id,
                'veImg' => $item->images->isEmpty() ? config('public.DEFAULT_VENUE_IMG') : Common::handleImg($item->images[0]->file_path),
                'veName' => $item->name,
                'clubId' => !empty($item->club) ? $item->club->id : 0,
                'clubName' => !empty($item->club) ? $item->club->name : '',
                'classNum' => $item->classes->count(),
                'classStuNum' => $item->classes->isNotEmpty() ? $item->getVenueStudents($item->classes->pluck('id')) : 0,
                'veUnitPrice' => $item->price_in_app,
                'distance' => isset($item->distance) ? number_format($item->distance,1).'km' : '',
            ];
        });

        return returnMessage('200','',$venuesData);
    }

    /**
     * 获取附近场馆
     * @param Request $request
     * @return array
     */
    public function getNearCourses(Request $request)
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $limitNum = $request->input('limitNum');

        $clubCourse = ClubCourse::select('*')->leftJoin('club_venue',function ($query) {
            $query->on('club_course.venue_id','=','club_venue.id');
        })
            ->where('club_course.day','>=',Carbon::now()->format('Y-m-d'))
            ->where('club_course.is_delete',0)
            ->where('club_course.show_in_app',1)
            ->where('club_course.status',1)
            ->orderBy('club_course.day','ASC')
            ->when($lat && $lng,function ($query) use ($lat,$lng) {
                return $query->addSelect(DB::raw('st_distance (point (longitude, latitude),point('.$lng.','.$lat.') ) / 0.0111 as distance'))
                    ->orderBy('distance', 'ASC');
            })
            ->orderBy('club_course.created_at','DESC')
            ->limit($limitNum)
            ->get();

        $classesData = [];
        collect($clubCourse)->each(function ($item) use (&$classesData,$lat,$lng) {
            $class = $item->getFirstClass($item->class_id);

            $classesData[] = [
                'classId' => $item->class_id,
                'imgUrl' => Common::handleImg($class['classImg']),
                'venues' => [
                    'veId' => $item->venue_id,
                    'veName' => $item->name,
                    'address' => $item->address,
                ],
                'courseDay' => $item->day,
                'courseName' => $class['className'],
                'courseTime' => $class ? $class['timeString'] : '',
                'courseWeek' => $class ? $class['week'] : '',
                'distance' => $lat && $lng ? $item->distance : '',
                'veUnitPrice' => "$item->price_in_app"
            ];
        });

        return returnMessage('200','',$classesData);
    }

    /**
     * 根据运动类型的ID获取该运动类型的所有场馆ID
     * @param $sportId
     * @return mixed
     */
    public function getVenueIdsBySportsTypeId($sportId)
    {
        $clubIds = Club::valid()->sportsType($sportId)->pluck('id');

        if ($clubIds->isEmpty()) return [];

        return ClubVenue::whereIn('club_id',$clubIds)->pluck('id');
    }

    /**
     * 根据课程类型ID获取课程类型所有俱乐部ID
     * @param $courseTypeId
     * @return mixed
     */
    public function getVenueIdsByCourseTypeId($courseTypeId)
    {
        $venueIds = ClubClass::leftJoin('club_club',function ($query) {
            return $query->on('club_class.club_id','=','club_club.id');
        })
            ->where('club_club.status',1)
            ->where('club_class.status',1)
            ->where('club_class.show_in_app',1)
            ->where('club_class.type',$courseTypeId)
            ->pluck('club_class.venue_id')->unique();

        return $venueIds;
    }

    /**
     * 班级详情
     * @param Request $request
     * @return array
     */
    public function classDetail(Request $request)
    {
        $classId = $request->input('classId');

        $class = ClubClass::valid()->showInApp()
            ->with(['club','venue','images','teachers'])
            ->find($classId);

        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $paymentService = new PaymentService();
        try {
            $paymenPlan = $paymentService->getClassPayment($classId);
        } catch (Exception $e) {
            return returnMessage($e->getCode(), $e->getMessage());
        }

        $paymentData = [];
        collect($paymenPlan)->each(function ($item) use (&$paymentData) {
            $paymentData[] = [
                'planId' => $item['planId'],
                'planName' => $item['planName'],
                'originPrice' => $item['originPrice'],   //原价
                'preferentialPrice' => $item['price'],  //现价
            ];
        });

        $classAgeStage = $class->getClassStudentMinAndMaxAge($classId);
        $teachers = $class->teachers->pluck('teacher_name')->unique();
        $teachers = $teachers->implode(',');
        $coachs = Common::getOneCoachNameByClassId($classId);

        $returnData = [
            'classId' => $class->id,
            'clubId' => $class->club_id,
            'clubName' => !empty($class->club) ? $class->club->name : '',
            'classType' => $class->type,
            'classImg' => $class->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($class->images[0]->file_path),
            'className' => $class->name,
            'courseTime' => $class->getClassTimeString($classId),
            'classAgeStage' => $classAgeStage['min'] . '岁' . '-' . $classAgeStage['max'] . '岁',
            'coachName' => $coachs ? $coachs : '暂无教练',
            'classAdviserName' => $teachers,
            'venues' => [
                'veName' => !empty($class->venue) ? $class->venue->name : '',
                'veLat' => !empty($class->venue) ? Common::numberFormatFloat($class->venue->latitude) : '',
                'veLng' => !empty($class->venue) ? Common::numberFormatFloat($class->venue->longitude) : '',
                'address' => !empty($class->venue) ? $class->venue->address : '',
            ],
            'paymentPlan' => $paymentData,
            "isFreeReserve" => true
        ];

        return returnMessage('200', '',$returnData);
    }

    /**
     * 预约详情
     * @param Request $request
     * @return array
     */
    public function reserveDetail(Request $request)
    {
        $reserveId = $request->input('reserveId');

        $reserve = ClubStudentSubscribe::with(['class','course','course.course_coach','class.venue','class.teachers'])
            ->find($reserveId);

        if (empty($reserve)) {
            return returnMessage('1902', config('error.subscribe.1902'));
        }

        $classAgeStage = Common::getClassStudentMinAndMaxAge($reserve->class_id);
        $teachers = $reserve->class->teachers->pluck('teacher_name')->unique();
        $teachers = $teachers->implode(',');
        $coachs = $reserve->course->course_coach->pluck('coach_name')->unique();
        $coachs = $coachs->implode(',');
        $classImg = Common::getFirstClassImg($reserve->class_id);
        $week = Common::getWeekName($reserve->course->week);
        $courseTime = Carbon::parse($reserve->course->start_time)->format('H:s').'-'.Carbon::parse($reserve->course->end_time)->format('H:s');

        $reserveStatus = 1;
        if ($reserve->subscribe_status == 3) {
            $reserveStatus = 2;
        } elseif (Carbon::now()->gt(Carbon::parse($reserve->course->day.' '.$reserve->course->end_time))) {
            $reserveStatus = 3;
        }

        $returnData = [
            'classId' => $reserve->class_id,
            'classImg' => Common::handleImg($classImg),
            'className' => !empty($reserve->class) ? $reserve->class->name : '',
            'courseTime' => $reserve->course->day.' '.$week.' '.$courseTime,
            'classAgeStage' => $classAgeStage['min'] . '岁' . '-' . $classAgeStage['max'] . '岁',
            'coachName' => $coachs ? $coachs : '暂无教练',
            'classAdviserName' => $teachers,
            'venues' => [
                'veName' => !empty($reserve->class->venue) ? $reserve->class->venue->name : '',
                'veLat' => !empty($reserve->class->venue) ? Common::numberFormatFloat($reserve->class->venue->latitude) : '',
                'veLng' => !empty($reserve->class->venue) ? Common::numberFormatFloat($reserve->class->venue->longitude) : '',
                'address' => !empty($reserve->class->venue) ? $reserve->class->venue->address : '',
            ],
            'reserveStatus' => $reserveStatus
        ];

        return returnMessage('200', '',$returnData);

    }

    /**
     * 增加班级的转发数
     * @param Request $request
     * @return array
     */
    public function incrClassTransNum(Request $request)
    {
        $classId = $request->input('classId');

        $class = ClubClass::valid()->showInApp()->find($classId);

        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        try {
            $class->increment('trans_num', 1);
        } catch (Exception $e) {
            return returnMessage('1005', config('error.common.1005'));
        }

        return returnMessage('200');
    }

    /**
     * 获取所有地址区域
     * @return array
     */
    public function getAllRigion()
    {
        $regions = ClubProvince::with(['city','city.district'])->get();

        $returnData = [];
        collect($regions)->each(function ($state) use (&$returnData) {
            $cityData = [];
            collect($state->city)->each(function ($city) use (&$cityData) {
                $countryData = [];
                collect($city->district)->each(function ($country) use (&$countryData) {
                    $countryData[] = [
                        'countryCode' => $country->code,
                        'countryName' => $country->name,
                    ];
                });

                $cityData[] = [
                    'cityCode' => $city->code,
                    'cityName' => $city->name,
                    'countrys' => $countryData
                ];
            });

            $returnData[] = [
                'stateCode' => $state->code,
                'stateName' => $state->name,
                'citys' => $cityData,
            ];
        });

        return returnMessage('200','',$returnData);
    }

    /**
     * 获取所有运动类型
     * @return array
     */
    public function getAllSportsType()
    {
        $clubTypes = Club::valid()->pluck('type')->unique();

        $arr = [];
        collect($clubTypes)->each(function ($item) use (&$arr) {
            if (!empty($item)) {
                array_push($arr,array(
                    'sportsId' => $item,
                    'sportsName' => Common::getSportsName($item)
                ));
            }
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 获取所有课程类型
     * @return array
     */
    public function getAllCourseType()
    {
        $arr = [
            ['courseId' => 1, 'courseName' => '常规班'],
            ['courseId' => 2, 'courseName' => '走训班'],
            ['courseId' => 3, 'courseName' => '封闭营']
        ];

        return returnMessage('200','',$arr);

        /*$clubTypes = ClubClass::leftJoin('club_club',function ($query) {
                return $query->on('club_class.club_id','=','club_club.id');
            })
            ->where('club_club.status',1)
            ->where('club_class.status',1)
            ->where('club_class.show_in_app',1)
            ->groupBy('club_class.type')
            ->pluck('club_class.type');

        $arr = [];
        collect($clubTypes)->each(function ($item) use (&$arr) {
            array_push($arr,array(
                'courseId' => $item,
                'courseName' => Common::getClassTypeName($item)
            ));
        });*/
    }

    /**
     * app用户预约列表
     * @param Request $request
     * @return array
     */
    public function myReservationList(Request $request)
    {
        $currentPage = $request->input('currentPage');
        $pagePerNum = $request->input('pagePerNum');
        $appUserMobile = $request->input('appUserMobile');

        $offset = ($currentPage - 1) * $pagePerNum;

        $studentIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentIds->isEmpty()) return returnMessage('200','',[]);

        $reserveList = ClubStudentSubscribe::with([
                'class',
                'course',
                'class.images' => function ($query) {
                    return $query->notDelete()->show();
                },
                'class.venue'
            ])
            ->whereIn('student_id',$studentIds)
            ->offset($offset)
            ->limit($pagePerNum)
            ->get();

        $arr = [];
        collect($reserveList)->each(function ($item) use (&$arr) {
            $arr[] = [
                'reserveId' => $item->id,
                'classId' => !empty($item->class) ? $item->class->id : 0,
                'className' => !empty($item->class) ? $item->class->name : '',
                'classImg' => $item->class->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($item->class->images[0]->file_path),
                'address' => !empty($item->class) && !empty($item->class->venue) ? $item->class->venue->address : '',
                'lessonTime' => !empty($item->course) ? Carbon::parse($item->course->day. ' '.$item->course->start_time)->format('m.d H:i') : '',
                'dutyStatus' => $item->transformDutyStatusCodeForApp($item->subscribe_status),
                'courseStatus' => !empty($item->course) ? $item->getCourseStatusForApp($item->course->status,$item->course->day,$item->course->start_time,$item->course->end_time) : ''
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 我的课程统计
     * @param Request $request
     * @return array
     */
    public function myCourseStatics(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $studentIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentIds->isEmpty()) return returnMessage('200','',[]);

        $studentsPayments = ClubStudent::with(['club','payments','signs','tickets'])
            ->whereIn('id',$studentIds)
            ->get();

        $arr = [];
        collect($studentsPayments)->each(function ($item) use (&$arr) {
            $purchasedCourseNum = $item->payments->sum('course_count');

            $onDutySigns = collect($item->signs)->filter(function ($val) {
                return $val->sign_status == 1;
            });

            $restCourses = collect($item->tickets)->filter(function ($val) {
                return $val->status == 2;
            });

            array_push($arr,array(
                'clubName' => !empty($item->club) ? $item->club->name : '',
                'stuName' => $item->name,
                'purchasedCourseNum' => $purchasedCourseNum,
                'onDutyCourseNum' => $onDutySigns->count(),
                'restCourseNum' => $restCourses->count()
            ));
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 我的课程统计
     * @param Request $request
     * @return array
     */
    public function myCourseStaticsForV1(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $studentIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentIds->isEmpty()) return returnMessage('200','',[]);

        $studentsPayments = ClubStudent::with(['club','payments.payment','signs','tickets'])
            ->whereIn('id',$studentIds)
            ->get();

        $arr = [];
        collect($studentsPayments)->each(function ($item) use (&$arr) {
            $totalTryCourseNum = 0; //总体验课时数
            $totalUsedTryCourseNum = 0; //已使用的体验课时数
            $totalNormalCourseNum = 0;  //总正式课时数
            $totalUsedNormalCourseNum = 0;  //已使用的正式课时数
            $totalRewardCourseNum = 0;  //总赠送课时数
            $totalUsedRewardCourseNum = 0;  //已使用赠送课时数
            $totalLeaveCount = 0;   //总请假额
            $totalUsedLeaveCount = 0;   //已使用事假
            $onDutyCourseNum = 0;    //累计总出勤数
            $outDutyCourseNum = 0;  //累计缺勤数
            $illForCourseNum = 0;   //累计病假数

            collect($item->payments)->each(function ($payment) use (&$totalTryCourseNum,&$totalNormalCourseNum) {
                if ($payment->payment_tag_id == 1) {
                    $totalTryCourseNum += $payment->course_count;
                }

                if ($payment->payment_tag_id == 2) {
                    $totalNormalCourseNum += $payment->course_count;
                }
            });

            collect($item->signs)->each(function ($sign) use (&$totalUsedTryCourseNum,&$totalUsedLeaveCount,&$onDutyCourseNum,&$outDutyCourseNum,&$illForCourseNum) {
                if ($sign->sign_status == 1) {
                    $onDutyCourseNum++;

                    if ($sign->is_experience == 1) {
                        $totalUsedTryCourseNum += 1;
                    }
                } elseif ($sign->sign_status == 2) {
                    $outDutyCourseNum++;
                } elseif ($sign->sign_status == 3) {
                    $totalUsedLeaveCount++;
                } elseif ($sign->sign_status == 4) {
                    $illForCourseNum++;
                }
            });

            $leftTryCourseNum = $totalTryCourseNum - $totalUsedTryCourseNum;

            collect($item->tickets)->each(function ($ticket) use (&$totalUsedNormalCourseNum,&$totalRewardCourseNum,&$totalUsedRewardCourseNum) {
                if ($ticket->is_experience == 0 && $ticket->reward_type == 0 && $ticket->status == 1) {
                    $totalUsedNormalCourseNum += 1;
                }

                if (in_array($ticket->reward_type,[1,2,3])) {
                    $totalRewardCourseNum ++;
                    if ($ticket->status == 1) {
                        $totalUsedRewardCourseNum++;
                    }
                }
            });

            $leftNormalCourseNum = $totalNormalCourseNum - $totalUsedNormalCourseNum;
            $leftRewardCourseNum = $totalRewardCourseNum - $totalUsedRewardCourseNum;

            if ($item->payments->isNotEmpty()) {
                $payments = ClubPayment::whereIn('id',collect($item->payments)->pluck('payment_id'))->select('private_leave_count')->get();
                //dd($payments);
                collect($payments)->each(function ($payPlan) use (&$totalLeaveCount) {
                    $totalLeaveCount += $payPlan->private_leave_count;
                });
            }

            $leftLeaveCount = $totalLeaveCount - $totalUsedLeaveCount;    //剩余事假额

            $arr[] = [
                'clubName' => !empty($item->club) ? $item->club->name : '',
                'stuName' => $item->name,
                'totalTryCourseNum' => $totalTryCourseNum,
                'leftTryCourseNum' => $leftTryCourseNum,
                'totalNormalCourseNum' => $totalNormalCourseNum,
                'leftNormalCourseNum' => $leftNormalCourseNum,
                'totalRewardCourseNum' => $totalRewardCourseNum,
                'leftRewardCourseNum' => $leftRewardCourseNum,
                'totalLeaveCount' => $totalLeaveCount,
                'leftLeaveCount' => $leftLeaveCount,
                'onDutyCourseNum' => $onDutyCourseNum,
                'outDutyCourseNum' => $outDutyCourseNum,
                'leaveForCourseNum' => $totalUsedLeaveCount,
                'illForCourseNum' => $illForCourseNum,
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 我的mvp列表
     * @param Request $request
     * @return array
     */
    public function myMvpList(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $studentIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentIds->isEmpty()) return returnMessage('200','',[]);

        $mvpList = ClubCourseSign::with(['class','course','class.venue','class.images'])
            ->whereIn('student_id',$studentIds)
            ->where('ismvp',1)
            ->get();

        $clubIds = $mvpList->pluck('club_id')->unique();

        $arr = [];
        collect($clubIds)->each(function ($clubId) use ($mvpList,&$arr) {
            $mvps = [];

            collect($mvpList)->each(function ($mvp) use (&$mvps,$clubId) {
                if ($mvp->club_id == $clubId) {
                    $mvps[] = [
                        'classId' => !empty($mvp->class) ? $mvp->class->id : '',
                        'className' => !empty($mvp->class) ? $mvp->class->name : '',
                        'address' => !empty($mvp->class) && !empty($mvp->class->venue) ? $mvp->class->venue->address : '',
                        'lessonTime' => !empty($mvp->course) ? Carbon::parse($mvp->course->day. ' '.$mvp->course->start_time)->format('m.d H:i') : '',
                        'classImg' => empty($mvp->class) || collect($mvp->class->images)->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($mvp->class->images[0]->file_path),
                    ];
                }
            });

            $club = Common::getClubById($clubId);
            $arr[] = [
                'clubId' => $clubId,
                'clubName' => !empty($club) ? $club->name : '',
                'mvpNum' => count($mvps),
                'mvpList' => $mvps
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 选择课程时间
     * @param Request $request
     * @return array
     */
    public function selectCourseTime(Request $request)
    {
        $classId = $request->input('classId');
        $paymentId = $request->input('planId');

        $classTime = ClubClassTime::where('class_id',$classId)->get();

        if ($classTime->isEmpty()) return returnMessage('1409',config('error.class.1409'));

        $payment = ClubPayment::find($paymentId);
        if (empty($payment)) return returnMessage('2101',config('error.Payment.2101'));

        $arr = [];
        collect($classTime)->each(function ($item) use (&$arr,$payment) {
            $week = $item->day;
            if ($week == 7) $week = 0;

            for ($i = 0; $i < 8; $i++) {
                $num = Carbon::now()->next($week)->diffInDays(Carbon::now()) + 1;
                $arr[] = [
                    'courseStartDate' => Carbon::now()->addDays($num + $i * 7)->timestamp,
                    'courseEndDate' => Carbon::now()->addDays($num + $i * 7)->addMonths($payment->use_to_date)->timestamp,
                ];
            }
        });

        $_arr = collect($arr)->sortBy('courseStartDate');
        $arr = $_arr->values()->all();

        $arr = array_slice($arr, 0, 8); //只取8个时间
        $returnData = [];
        collect($arr)->each(function ($item) use (&$returnData) {
            $returnData[] = [
                'courseStartDate' => Carbon::createFromTimestamp($item['courseStartDate'])->format('Y-m-d'),
                'courseEndDate' => Carbon::createFromTimestamp($item['courseEndDate'])->format('Y-m-d'),
            ];
        });

        return returnMessage('200','',$returnData);
    }

    /**
     * 预约详情
     * @param Request $request
     * @return array
     */
    public function reservationDetail(Request $request)
    {
        $classId = $request->input('classId');

        $class = ClubClass::with(['time','venue','images'])
            ->valid()->showInApp()->find($classId);

        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $courseDays = ClubCourse::notDelete()->courseOn()
            ->where('class_id',$classId)
            ->where('day','>=',Carbon::now()->format('Y-m-d'))
            ->orderBy('day')
            ->limit(8)
            ->select('day','start_time')
            ->get();

        $days = collect($courseDays)->filter(function ($item) {
            if ($item->day > Carbon::now()->format('Y-m-d')
                ||
                ($item->day == Carbon::now()->format('Y-m-d') && $item->start_time >= Carbon::now()->addHours(2)->format('H:i:s'))) {
                return true;
            }
        });

        if ($days->isEmpty()) {
            return returnMessage('1420', config('error.class.1420'));
        }

        $arr = [
            'classId' => $classId,
            'classImg' => $class->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($class->images[0]->file_path),
            'className' => $class->name,
            'courseTime' => $class->getClassTimeString($classId),
            'address' => $class->venue->address,
            //'reserveTime' => $courseDays
            'reserveTime' => collect($days)->pluck('day')
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 确认预约
     * @param Request $request
     * @return array
     */
    public function reserveConfirm(Request $request)
    {
        $classId = $request->input('classId');
        $reserveDate = $request->input('reserveDate');
        $stuName = $request->input('stuName');
        $stuAge = $request->input('stuAge');
        $stuSex = $request->input('stuSex');
        $appUserMobile = $request->input('appUserMobile');

        $reserveDate = str_replace('.','-',$reserveDate);

        $class = ClubClass::with(['time','venue:id,name','club:id'])
            ->valid()->showInApp()->find($classId);

        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $venueId = $class->venue->id;
        $clubId = $class->club->id;

        $course = ClubCourse::where('class_id',$classId)->where('day',$reserveDate)->where('status',1)->first();

        if (empty($course)) {
            return returnMessage('1409', config('error.class.1409'));
        }

        Log::setGroup('Subscribe')->error('app预约-start',[$request->all()]);

        try {
            $student = Subscribe::studentReserve($classId,$venueId,$course->id,$clubId,$stuName,$stuAge,$stuSex,$appUserMobile);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        $studentId = $student['id'];

        $clubStudent = ClubStudent::with(['club:id,name','sales:id,sales_name,mobile'])->find($studentId);

        $arr = [
            'postData' => $request->all(),
            'studentId' => $studentId,
            'studentInfo' => $clubStudent->toArray()
        ];
        Log::setGroup('Subscribe')->error('app预约',$arr);

        $reserveTime = $request->input('reserveDate') . '日 ' . Carbon::parse($course->start_time)->format('H:i') . '-' . Carbon::parse($course->end_time)->format('H:i');

        $paramData = [
            $clubStudent->name,
            $clubStudent->club ? $clubStudent->club->name : '暂无',
            $reserveTime,
            $class->venue->name,
            $clubStudent->sales ? $clubStudent->sales->sales_name : '暂无',
            $clubStudent->sales ? $clubStudent->sales->mobile : '暂无',
        ];

        $postData = [
            'userMobile' => $appUserMobile,
            'smsTitle' => 'reserveCourseSms',
            'smsSourceType' => 'APP',
            'paramData' => json_encode($paramData)
        ];

        $url = env('HTTPS_PREFIX').env('APP_INNER_DOMAIN').'sms/clubPushSms';
        $res = Common::curlPost($url,1,$postData);
        $res = json_decode($res,true);

        if ($res['code'] != '200') {
            $clubErrorLog = new ClubErrorLog();
            $clubErrorLog->error_channel = 'sms';
            $arr = [
                'mobile' => $appUserMobile,
                'reserveTime' => $reserveTime,
                'className' => $class->name,
                'courseId' => $course->id,
                'stuId' => $studentId,
            ];
            $clubErrorLog->error_content = '预约短信发送失败：数据'.json_encode($arr);

            $clubErrorLog->save();
        }

        $arr = [
            'clubId' => $clubStudent->clubId,
            'info' => '您已成功预约' . $class->venue->name . $class->name . $request->input('reserveDate') . '日 ' . Carbon::parse($course->start_time)->format('H:i') . '~' . Carbon::parse($course->end_time)->format('H:i') . ' 的课程'
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 取消预约
     * @param Request $request
     * @return array
     */
    public function cancelReserve(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');
        $reserveId = $request->input('reserveId');

        try {
            Subscribe::cancelReserve($reserveId,$appUserMobile);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','');
    }

    /**
     * 预约学员信息确认
     * @param Request $request
     * @return array
     */
    public function confirmStudentInfo(Request $request)
    {
        $classId = $request->input('classId');
        $stuName = $request->input('stuName');
        $enterClassTime = $request->input('startTime');
        $stuIdCardNum = $request->input('stuIdCardNum');
        $appUserMobile = $request->input('appUserMobile');
        $paymentId = $request->input('paymentId');

        $class = ClubClass::with(['venue','club'])
            ->valid()->showInApp()->find($classId);

        if (empty($class)) {
            return returnMessage('1406', config('error.class.1409'));
        }

        $venueId = $class->venue->id;
        $clubId = $class->club->id;

        $course = ClubCourse::where('class_id',$classId)
            ->notDelete()
            ->courseOn()
            ->get();

        $courseId = 0;

        foreach ($course as $key => $val) {
            if (Carbon::parse($val->day)->dayOfWeekIso == Carbon::parse($enterClassTime)->dayOfWeekIso) {
                $courseId = $val->id;
                break;
            }
        }

        if (empty($courseId)) {
            return returnMessage('2002', config('error.course.2002'));
        }

        try {
            $student = Subscribe::confirmStudentInfo($classId,$venueId,$clubId,$courseId,$paymentId,$stuName,$stuIdCardNum,$appUserMobile);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','',$student);
    }

    /**
     * 购买学员信息确认
     * @param Request $request
     * @return array
     */
    public function confirmStudentInfoForBuy(Request $request)
    {
        $classId = $request->input('classId');
        $stuName = $request->input('stuName');
        $stuIdCardNum = $request->input('stuIdCardNum');
        $appUserMobile = $request->input('appUserMobile');
        $paymentId = $request->input('paymentId');

        $class = ClubClass::with(['venue','club'])
            ->valid()->showInApp()->find($classId);

        if (empty($class)) {
            return returnMessage('1406', config('error.class.1409'));
        }

        $venueId = $class->venue->id;
        $clubId = $class->club->id;

        try {
            $student = Subscribe::confirmStudentInfoForBuy($classId,$venueId,$clubId,$paymentId,$stuName,$stuIdCardNum,$appUserMobile);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','',$student);
    }

    /**
     * 获取班级基本信息和教练评价
     * @param Request $request
     * @return array
     */
    public function getClassBasicAndCoachComments(Request $request)
    {
        $classId = $request->input('classId');

        $classBasic = $this->getClassBasic($classId);

        if (empty($classBasic)) return returnMessage('1406', config('error.class.1406'));

        $coachComments = $this->getClassCoachComments($classId);

        $returnData = $classBasic;

        $returnData['coachComments'] = $coachComments;

        return returnMessage('200', '',$returnData);
    }

    /**
     * 获取班级基本信息
     * @param $classId
     * @return array
     */
    public function getClassBasic($classId)
    {
        $class = ClubClass::valid()
            ->with(['venue','club','images','teachers','coachs'])
            ->find($classId);

        if (empty($class)) return [];

        $classAgeStage = $class->getClassStudentMinAndMaxAge($classId);
        $teachers = $class->teachers->pluck('teacher_name')->unique();
        $teachers = $teachers->implode(',');
        $coachs = $class->coachs->pluck('teacher_name')->unique();
        $coachs = $coachs->implode(',');

        $paymentService = new PaymentService();

        try {
            $paymenPlan = $paymentService->getClassPayment($classId);
        } catch (Exception $e) {
            return [];
        }

        $paymentData = [];
        collect($paymenPlan)->each(function ($item) use (&$paymentData) {
            $paymentData[] = [
                'planId' => $item['planId'],
                'planName' => $item['planName'],
                'unitPrice' => Common::numberFormatFloat($item['price']/$item['courseCount'],2),
                'courseCount' => $item['courseCount'],
                'originPrice' => $item['originPrice'],   //原价
                'price' => $item['price'],  //现价
            ];
        });

        $returnData = [
            'classId' => $class->id,
            'clubId' => $class->club_id,
            'clubName' => $class->club->name,
            'classType' => $class->type,
            'classImg' => $class->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($class->images[0]->file_path),
            'className' => $class->name,
            'courseTime' => $class->getClassTimeString($classId),
            'classAgeStage' => $classAgeStage['min'] . '岁' . '-' . $classAgeStage['max'] . '岁',
            'coachName' => $coachs ? $coachs : '暂无教练',
            'classAdviserName' => $teachers,
            'venues' => [
                'veName' => $class->venue->name,
                'veLat' => Common::numberFormatFloat($class->venue->latitude),
                'veLng' => Common::numberFormatFloat($class->venue->longitude),
                'address' => $class->venue->address,
            ],
            'tag' => $class->pay_tag_name ? [$class->pay_tag_name] : [],
            'paymentPlan' => $paymentData,
            'everyTimeLong' => $class->getEveryCourseTimeLong($classId)
        ];

        return $returnData;
    }

    /**
     * 班级基本信息(课程订单)
     * @param Request $request
     * @return array
     */
    public function getClassBasicForOrder(Request $request)
    {
        $classId = $request->input('classId');
        $class = ClubClass::with(['club','images'])
            ->find($classId);

        if (empty($class)) return returnMessage('1406', config('error.class.1406'));

        $arr = [
            'classId' => $class->id,
            'className' => $class->name,
            'clubName' => $class->club->name,
            'classImg' => $class->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($class->images[0]->file_path),
            'tag' => $class->pay_tag_name ? [$class->pay_tag_name]: [],
        ];

        return returnMessage('200', '',$arr);
    }

    /**
     * 获取课程教练评价
     * @param $classId
     * @return array
     */
    public function getClassCoachComments($classId)
    {
        $course = ClubCourse::where('class_id',$classId)
            ->select('coach_id','coach_name','coach_comment')
            ->orderBy('coach_comment_at','desc')
            ->get();

        if ($course->isEmpty()) return [];

        $arr = [];
        collect($course)->each(function ($item) use (&$arr) {
            $arr[] = [
                'user' => [
                    'id' => $item->coach_id,
                    'nickName' => $item->coach_name,
                ],
                'created_at' => Common::fomatTimeStampToString($item->coach_comment_at),
                'content' => $item->coach_comment
            ];
        });

        return $arr;
    }

    /**
     * 根据学员序列号绑定学员
     * @param Request $request
     * @return array
     */
    public function bindStudentBySeriesNum(Request $request)
    {
        $stuSeriesNum = $request->input('stuSeriesNum');
        $appUserMobile = $request->input('appUserMobile');

        $student = ClubStudent::where('serial_no',strtolower($stuSeriesNum))->first();

        if (empty($student)) {
            return returnMessage('1601',config('error.Student.1610'));
        }

        $studentBind = ClubStudentBindApp::notDelete()
            ->where('student_id',$student->id)
            ->where('app_account',$appUserMobile)
            ->first();

        if ($studentBind) {
            return returnMessage('1673',config('error.Student.1673'));
        }

        $studentBindCounts = ClubStudentBindApp::notDelete()
            ->where('app_account',$appUserMobile)
            ->count();

        if ($studentBindCounts >= self::MAX_BIND_COUNT) {
            return returnMessage('1677',config('error.Student.1677'));
        }

        try {
            DB::transaction(function () use ($student,$appUserMobile) {
                $studentBind = new ClubStudentBindApp();
                $studentBind->student_id = $student->id;
                $studentBind->student_sales_id = $student->sales_id;
                $studentBind->app_account = $appUserMobile;
                $studentBind->saveOrFail();

                $student->bind_app = 1;
                $student->app_account = $appUserMobile;
                $student->saveOrFail();
            });
        } catch (Exception $e) {
            return returnMessage('1674',config('error.Student.1674'));
        }

        $arr = [
            'stuId' => $student->id,
            'stuName' => $student->name
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 获取绑定学员类列表
     * @param Request $request
     * @return array
     */
    public function getBindStudents(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $studentBindIds = ClubStudentBindApp::notDelete()->where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentBindIds->isEmpty()) return returnMessage('200','',[]);

        $students = ClubStudent::whereIn('id',$studentBindIds)->get();

        $arr = [];
        collect($students)->each(function ($item) use (&$arr) {
            $arr[] = [
                'stuId' => $item->id,
                'stuName' => $item->name
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 获取用户绑定学员所在的俱乐部
     * @param Request $request
     * @return array
     */
    public function getBindStudentClubIds(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $studentBindIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentBindIds->isEmpty()) return returnMessage('200','',[]);

        $studentClubs = ClubStudent::notDelete()->whereIn('id',$studentBindIds)
            ->groupBy('club_id')
            ->pluck('club_id');

        if ($studentClubs->isEmpty()) return returnMessage('200','',[]);

        return returnMessage('200','',$studentClubs->toArray());
    }

    /**
     * 根据用户手机号和俱乐部ID获取对应的绑定学员
     * @param Request $request
     * @return array
     */
    public function getBindStudentByAppUserMobileAndClubId(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');
        $clubId = $request->input('clubId');

        $studentBindIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentBindIds->isEmpty()) return returnMessage('200','',[]);

        $students = ClubStudent::notDelete()
            ->whereIn('id',$studentBindIds)
            ->where('club_id',$clubId)
            ->get();

        $arr = [];
        collect($students)->each(function ($item) use (&$arr) {
            $arr[] = [
                'stuId' => $item->id,
                'stuName' => $item->name
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * app用户绑定学员的课程顾问
     * @param Request $request
     * @return array
     */
    public function myCourseConsultant(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $studentBindIds = ClubStudentBindApp::where('app_account',$appUserMobile)->pluck('student_id');

        if ($studentBindIds->isEmpty()) return returnMessage('200','',[]);

        $students = ClubStudent::with(['club','sales'])
            ->whereIn('id',$studentBindIds)->get();

        if ($students->isEmpty()) return returnMessage('200','',[]);

        $arr = [];
        collect($students)->each(function ($item) use (&$arr) {
            $arr[] = [
                'clubName' => $item->club->name,
                'conSulName' => !empty($item->sales) ? $item->sales->sales_name : '',
                'conSulMobile' => !empty($item->sales) ? $item->sales->mobile : ''
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 班级基本信息
     * @param Request $request
     * @return array
     */
    public function getClassBasicInfo(Request $request)
    {
        $classId = $request->input('classId');

        $classBasic = $this->getClassBasic($classId);

        if (empty($classBasic)) returnMessage('1406', config('error.class.1406'));

        return returnMessage('200','',$classBasic);
    }

    /**
     * 获取app用户绑定学员课表
     * @param Request $request
     * @return array
     */
    public function getMyCourseTables(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');
        $stuId = $request->input('stuId');
        $dateTime = $request->input('dateTime');

        $students = ClubStudent::where('id',$stuId)->exists();

        if ($students === false) return returnMessage('1610',config('error.Student.1610'));

        $studentBind = ClubStudentBindApp::where('app_account',$appUserMobile)
            ->where('student_id',$stuId)
            ->exists();

        if ($studentBind === false) return returnMessage('1675',config('error.Student.1675'));

        $classStudents = ClubClassStudent::with([
                'class' => function ($query) {
                    return $query->where('status',1)->where('is_delete',0);
                }
            ])->where('student_id',$stuId)->select('class_id')->get();

        if ($classStudents->isEmpty()) return returnMessage('200','',[]);

        $classIds = $classStudents->filter(function ($item) {
            return !empty($item->class);
        });

        $stuEnterTime = ClubClassStudent::notDelete()->where('student_id',$stuId)->value('enter_class_time');

        $courseId = ClubStudentSubscribe::where('student_id',$stuId)->value('course_id');

        if (!empty($courseId)) {
            $courseTime = ClubCourse::where('id',$courseId)->value('day');

            if (!empty($courseTime) && Carbon::parse($stuEnterTime)->lt(Carbon::parse($courseTime))) {
                $stuEnterTime = $courseTime;
            }
        }

        $classIds = collect($classIds)->pluck('class_id');

        if ($classIds->isEmpty()) return returnMessage('200','',[]);

        $course = ClubCourse::with([
                'venue',
                'class',
                'course_sign' => function ($query) use ($stuId) {
                    return $query->where('student_id',$stuId);
                }
            ])
            ->when($dateTime,function ($query) use ($dateTime) {
                $startOfMonth = Carbon::parse($dateTime)->startOfMonth();
                $endOfMonth = Carbon::parse($dateTime)->endOfMonth();
                return $query->where('day','>=',$startOfMonth)->where('day','<=',$endOfMonth);
            })
            ->when($stuEnterTime,function ($query) use ($stuEnterTime) {
                return $query->where('day','>=',Carbon::parse($stuEnterTime)->format('Y-m-d'));
            })
            ->whereIn('class_id',$classIds)
            ->orderBy('day','asc')
            ->get();

        $arr = [];

        collect($course)->each(function ($item) use (&$arr,$stuEnterTime) {
            if ($item->course_sign->isEmpty() || !in_array($item->course_sign[0]->sign_status,[6,7])) {//过滤掉6:pass,7:autopass
                if ($item->status == 0) {//停课
                    $dutyStatus = 5;
                } elseif (Carbon::now()->gt(Carbon::parse($item->day))) {//课程已上完
                    if ($item->course_sign->isNotEmpty() && $item->course_sign[0]->sign_status > 0) {
                        $dutyStatus = Common::getDutyStatusForApp($item->course_sign[0]->sign_status);
                    } else {
                        $dutyStatus = 4;//缺考勤记录
                    }
                } elseif (Carbon::now()->lte(Carbon::parse($item->day.' '.$item->start_time))) {//上课
                    $dutyStatus = 0;
                } else {
                    $dutyStatus = Common::getDutyStatusForApp($item->course_sign[0]->sign_status);
                }

                $arr[] = [
                    'courseDate' => $item->day,
                    'veName' => $item->venue->name,
                    'className' => $item->class->name,
                    'courseTime' => Carbon::parse($item->day.' '.$item->start_time)->format('m月d日'),
                    'dutyStatus' => $dutyStatus,
                ];
            }

        });

        return returnMessage('200','',$arr);
    }

    /**
     * 班级图片(banner)
     * @param Request $request
     * @return array
     */
    public function classBanner(Request $request)
    {
        $classId = $request->input('classId');

        $classImages = ClubClassImage::where('class_id',$classId)
            ->notDelete()
            ->show()
            ->orderBy('created_at', 'desc')
            ->get();

        $arr = [];
        collect($classImages)->each(function ($items) use (&$arr) {
            $arr[] = Common::handleImg($items->file_path);
        });

        if (empty($arr)) {
            $arr = [config('public.DEFAULT_CLASS_IMG')];
        }

        return returnMessage('200','',$arr);
    }

    /**
     * 场馆图片（banner）
     * @param Request $request
     * @return array
     */
    public function venuesBanner(Request $request)
    {

        $veId = $request->input('veId');

        $venueImages = ClubVenueImage::where('venue_id',$veId)
            ->notDelete()
            ->show()
            ->orderBy('created_at', 'desc')
            ->get();

        $arr = [];
        collect($venueImages)->each(function ($items) use (&$arr) {
            $arr[] = Common::handleImg($items->file_path);
        });

        if (empty($arr)) {
            $arr = [config('public.DEFAULT_VENUE_IMG')];
        }

        return returnMessage('200','',$arr);
    }

    /**
     * 查询场馆存不存在
     * @param Request $request
     * @return array
     */
    public function findVenueExistsForApp(Request $request)
    {
        $veId = $request->input('veId');
        $venue = ClubVenue::notDelete()->valid()->showInApp()
            ->with([
                'club' => function ($query) {
                    return $query->notDelete()->valid();
                }
            ])
            ->find($veId);

        if (empty($venue)) {
            return returnMessage('1301',config('error.venue.1301'));
        }

        if (empty($venue->club)) {
            return returnMessage('1204',config('error.club.1204'));
        }

        return returnMessage('200');
    }

    /**
     * 查询班级存不存在
     * @param Request $request
     * @return array
     */
    public function findClassExistsForApp(Request $request)
    {
        $classId = $request->input('classId');
        $class = ClubClass::valid()->showInApp()
            ->with([
                'club' => function ($query) {
                    return $query->notDelete()->valid();
                },
                'venue' => function ($query) {
                    return $query->notDelete()->valid()->showInApp();
                }
            ])
            ->find($classId);

        if (empty($class)) {
            return returnMessage('1406',config('error.class.1406'));
        }

        if (empty($class->club)) {
            return returnMessage('1204',config('error.club.1204'));
        }

        if (empty($class->venue)) {
            return returnMessage('1301',config('error.venue.1301'));
        }

        return returnMessage('200');
    }

    /**
     * 根据学员序列号查找俱乐部
     * @param Request $request
     * @return array
     */
    public function findClubByStuSeriesNum(Request $request)
    {
        $stuSeriesNum = $request->input('stuSeriesNum');

        $clubStudent = ClubStudent::with(['club'])->where('serial_no',$stuSeriesNum)->first();

        $arr = [];
        if (!empty($clubStudent)) $arr = ['clubName' => $clubStudent->club->name];

        return returnMessage('200','',$arr);
    }

    /**
     * 根据班级ID获取相应的数据
     * @param Request $request
     * @return array
     */
    public function getClassListByIds(Request $request)
    {
        $classIds = $request->input('classIds');
        $classIds = explode(',',$classIds);
        $classIds = collect($classIds)->unique();

        $classes = ClubClass::valid()
            ->with(['images'])
            ->whereIn('id',$classIds)
            ->get();

        if (empty($classes)) returnMessage('200','',[]);

        $arr = [];

        $paymentService = new PaymentService();

        collect($classes)->each(function ($item) use (&$arr,$paymentService) {
            try {
                $paymentPlan = $paymentService->getClassPayment($item->id);
            } catch (Exception $e) {
                $paymentPlan = [];
            }

            $arr[$item->id] = [
                'classId' => $item->id,
                'classImg' => $item->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($item->images[0]->file_path),
                'className' => $item->name,
                'courseTime' => $item->getClassTimeString($item->id),
                'paymentPlan' => $paymentPlan
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 获取学生基本信息
     * @param Request $request
     * @return array
     */
    public function getStudentBasic(Request $request)
    {
        $stuId = $request->input('stuId');

        $clubStudent = ClubStudent::with(['core'])->find($stuId);

        if (empty($clubStudent)) return returnMessage('1610',config('error.student.1610'));

        $arr = [
            'stuId' => $clubStudent->id,
            'zh_name' => isset($clubStudent->core) ? $clubStudent->core->chinese_name : '',
            'card_no' => isset($clubStudent->core) ? $clubStudent->core->card_no : ''
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 修改学员身份状态
     * @param Request $request
     * @return array
     */
    public function changeStuStatus(Request $request)
    {
        $stuId = $request->input('stuId');
        $planId = $request->input('planId');
        $orderSn = $request->input('orderSn');
        $classId = $request->input('classId');
        $contractSn = $request->input('contractSn');

        $studentService = new StudentService();

        try {
            $returnData = $studentService->changeStuStatus($stuId,$planId,$orderSn,$classId,$contractSn);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','学员身份修改成功',$returnData);
    }

    /**
     * 获取es搜索需要的班级数据
     * @return array
     */
    public function getAllClassListForES()
    {
        $classes = ClubClass::valid()->showInApp()
            ->with(['venue','venue.images','images'])
            ->get();

        if (empty($classes)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $arr = [];

        collect($classes)->each(function ($item) use (&$arr) {
            /*try {
                $paymentPlan = $paymentService->getClassPayment($item->id);
            } catch (Exception $e) {
                $paymentPlan = [];
            }*/

            $arr[] = [
                'classId' => $item->id,
                'clubId' => $item->club_id,
                'clubName' => '',
                //'clubImg' => $item->club->images->isEmpty() ? '' : Common::handleImg($item->club->images[0]->file_path),
                'clubImg' => !empty($item->venue) && !empty($item->venue->images->isNotEmpty()) ? Common::handleImg($item->venue->images[0]->file_path) : config('public.DEFAULT_VENUE_IMG'),
                'classType' => $item->type,
                'classImg' => $item->images->isEmpty() ? config('public.DEFAULT_CLASS_IMG') : Common::handleImg($item->images[0]->file_path),
                'className' => $item->name,
                'venues' => [
                    'veId' => !empty($item->venue) ? $item->venue->id : null,
                    'veName' => !empty($item->venue) ? $item->venue->name : '',
                    'veLat' => !empty($item->venue) ? Common::numberFormatFloat($item->venue->latitude) : '',
                    'veLng' => !empty($item->venue) ? Common::numberFormatFloat($item->venue->longitude) : '',
                    'address' => !empty($item->venue) ? $item->venue->address : '',
                    'veUnitPrice' => !empty($item->venue) ? strval($item->venue->price_in_app) : '',
                    'veProviceId' => !empty($item->venue) ? $item->venue->province_id : null,
                    'veCityId' => !empty($item->venue) ? $item->venue->city_id : null,
                    'veDistrictId' => !empty($item->venue) ? $item->venue->district_id : null,
                    'veImg' => !empty($item->venue) && !empty($item->venue->images->isNotEmpty()) ? Common::handleImg($item->venue->images[0]->file_path) : config('public.DEFAULT_VENUE_IMG')
                ],
                //'paymentPlan' => $paymentPlan
            ];
        });

        return returnMessage('200', '',$arr);
    }

    /**
     * 根据场馆ID获取场馆下的班级和学员数
     * @param Request $request
     * @return array
     */
    public function getVenuesClassNumAndStuNum(Request $request)
    {
        $veIds = $request->input('veIds');

        $veIds = explode(',',$veIds);

        $venues = ClubVenue::with([
                'classes' => function ($query) {
                    return $query->valid()->showInApp();
                },
                'students' => function ($query) {
                    return $query->notDelete();
                }
            ])
            ->whereIn('id',$veIds)
            ->get();

        $arr = [];
        collect($venues)->each(function ($item) use (&$arr) {
            $arr[$item->id] = [
                'classNum' => $item->classes->isNotEmpty() ? $item->classes->count() : 0,
                'stuNum' => $item->students->isNotEmpty() ? $item->students->count() : 0
            ];
        });

        return returnMessage('200', '',$arr);
    }

    /**
     * 获取学员身份证相关信息
     * @param Request $request
     * @return array
     */
    public function getStudentIdCardInfo(Request $request)
    {
        $stuId = $request->input('stuId');

        $student = ClubStudent::notDelete()->with(['core'])->find($stuId);

        if (empty($student)) {//学员不存在
            return returnMessage('1610',config('error.Student.1610'));
        }

        $arr = [];
        if (empty($student->core)) return returnMessage('200','',$arr);

        $arr = [
            'stuId' => $stuId,
            'stuName' => $student->core->chinese_name,
            'stuIdCardNum' =>$student->core->card_no
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 将学员的信息发送给俱乐部完善
     * @param Request $request
     * @return array
     */
    public function fullFillStudentCore(Request $request)
    {
        $stuId = $request->input('stuId');
        $stuName = $request->input('stuName');
        $stuIdCardNum = $request->input('stuIdCardNum');
        $appUserMobile = $request->input('appUserMobile');

        $student = ClubStudent::notDelete()->with(['core'])->find($stuId);

        if (empty($student)) {//学员不存在
            return returnMessage('1610',config('error.Student.1610'));
        }

        $studentBind = ClubStudentBindApp::where('app_account',$appUserMobile)
            ->where('student_id',$stuId)
            ->first();

        if (empty($studentBind)) {
            try {
                Subscribe::studentBindApp($appUserMobile, $stuId);
            } catch (Exception $e) {
                return returnMessage('1011',config('error.common.1011'));
            }
        }

        if (empty($student->core)) {
            try {
                DB::transaction(function () use ($stuName,$stuIdCardNum,$stuId) {
                    $studentCore = new ClubStudentCore();
                    $studentCore->chinese_name = $stuName;
                    $studentCore->card_type = 1;
                    $studentCore->card_no = $stuIdCardNum;
                    $studentCore->saveOrFail();

                    Student::updateStudentCoreId($stuId,$studentCore->id);
                });

            } catch (Exception $e) {
                return returnMessage('1011',config('error.common.1011'));
            }
        } else {
            $student->core->chinese_name = $stuName;
            $student->core->card_no = $stuIdCardNum;

            try {
                $student->core->saveOrFail();
            } catch (Exception $e) {
                return returnMessage('1011',config('error.common.1011'));
            }
        }

        return returnMessage('200','',[]);
    }

    /**
     * 通过缴费记录ID获取缴费方案
     * @param Request $request
     * @return array
     */
    public function getPaymentByPayRecordId(Request $request)
    {
        $payRecordId = $request->input('payRecordId');

        $paymentService = new PaymentService();

        try {
            $payment = $paymentService->getPaymentByStudent($payRecordId);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','',$payment);
    }

    /**
     * 通过多个缴费记录ID获取缴费方案
     * @param Request $request
     * @return array
     */
    public function getPaymentsByPayRecordIds(Request $request)
    {
        $payRecordsIds = $request->all();

        $paymentService = new PaymentService();

        try {
            $payments = $paymentService->getPaymentByPaymentIds($payRecordsIds);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','',$payments);
    }

    /**
     * 合同签署完成通知
     * @param Request $request
     * @return array
     */
    public function contractCompleteNotice(Request $request)
    {
        $payRecordId = $request->input('payRecordId');
        $contractSn = $request->input('contractSn');

        $paymentService = new PaymentService();

        try {
            $paymentService->contractComplete($payRecordId,$contractSn);
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        return returnMessage('200','',[]);
    }

    /**
     * 我的测评列表
     * @param Request $request
     * @return array
     */
    public function getMyExamLists(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $bindStudents = ClubStudentBindApp::notDelete()
            ->with(['student_club:id,club_id'])
            ->where('app_account',$appUserMobile)
            ->get();

        $bindStudentIds = collect($bindStudents)->pluck('student_id');

        if ($bindStudentIds->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $clubIds = [];
        collect($bindStudents)->each(function ($item) use (&$clubIds) {
            $clubIds[] = $item->student_club->club_id;
        });

        $clubIds = collect($clubIds)->unique();

        $clubs = Club::whereIn('id',$clubIds)->pluck('name','id');

        $myExams = ClubExamsStudent::notDelete()
            ->with(['exam','student','exam_items','exam_items.exam_item','exam_items.exam_item_level'])
            ->whereIn('student_id',$bindStudentIds)
            ->whereHas('exam',function ($query) {
                return $query->where('club_exams.status',1);
            })
            ->get();

        $arr = [];

        collect($clubs)->each(function ($club,$clubId) use (&$arr,$myExams) {
            collect($myExams)->each(function ($item) use (&$arr,$club,$clubId) {
                $age = Common::getAgeByBirthday($item->student->birthday);
                if (empty($age)) $age = $item->student->age;

                $examBill = [];
                collect($item->exam_items)->each(function ($exam_item) use (&$examBill) {
                    $examBill[] = [
                        "itemName" => $exam_item->exam_item->item_name,
                        "itemLevel" => $exam_item->exam_item_level->level_name
                    ];
                });

                $exams = [
                    "examEnName" => $item->exam->exam_name,
                    "examName" => $item->exam->exam_name,
                    "userName" => $item->student->name,
                    "age" => $age,
                    "generalLevel" => $item->exam_general_level,
                    "examTime" => Carbon::parse($item->created_at)->format('Y.m.d'),
                    "examBill" => $examBill,
                    "remark" => $item->remark
                ];

                if ($item->student->club_id == $clubId) {
                    $arr[$clubId]['clubName'] = $club;
                    $arr[$clubId]['exams'][] = $exams;
                } else {
                    $arr[$clubId] = [
                        'clubName' => $club,
                        'exams' => [$exams]
                    ];
                }
            });
        });

        if (!empty($arr)) {
            $arr = array_values($arr);
        }

        return returnMessage('200','',$arr);
    }

    /**
     * 根据缴费方案ID获取缴费方案
     * @param Request $request
     * @return array
     */
    public function getPaymentByPlanId(Request $request)
    {
        $planId = $request->input('planId');

        $payment = ClubPayment::valid()->showInApp()->find($planId);

        if (empty($payment)) {
            return returnMessage('2101',config('error.Payment.2101'));
        }

        $arr = [
            'planId' => $payment->id,
            'planName' => $payment->name,
            'paymentTagId' => $payment->tag,
            'originPrice' => $payment->original_price,
            'price' => $payment->price,
            'courseCount' => $payment->course_count,
            'privateLeaveCount' => $payment->private_leave_count,
            'unitPrice' => Common::numberFormatFloat($payment->price/$payment->course_count,2)
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 根据缴费方案IDs获取缴费方案
     * @param Request $request
     * @return array
     */
    public function getPaymentByPlanIds(Request $request)
    {
        $planIds = $request->all();

        $payments = ClubPayment::valid()->whereIn('id',$planIds)->get();

        $arr = [];
        collect($payments)->each(function ($item) use (&$arr) {
            $arr[$item->id] = [
                'planId' => $item->id,
                'planName' => $item->name,
                'originPrice' => $item->original_price,
                'price' => $item->price,
                'courseCount' => $item->course_count,
                'privateLeaveCount' => $item->private_leave_count,
                'unitPrice' => Common::numberFormatFloat($item->price/$item->course_count,2)
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 获取绑定正式学员
     * @param Request $request
     * @return array
     */
    public function getBindOfficalStudents(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $bindStudents = $this->bindStudentsForApp($appUserMobile,1);

        return returnMessage('200','',$bindStudents);

    }

    /**
     * 获取绑定非正式学员
     * @param Request $request
     * @return array
     */
    public function getBindNotOfficalStudents(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');

        $bindStudents = $this->bindStudentsForApp($appUserMobile,2);

        return returnMessage('200','',$bindStudents);
    }

    /**
     * 根据类型获取对应的绑定学员
     * @param $appUserMobile
     * @param int $type
     * @return array
     */
    public function bindStudentsForApp($appUserMobile,$type = 1)
    {

        $studentBind = ClubStudentBindApp::where('app_account',$appUserMobile)
            ->with([
                'student_club' => function ($query) {
                    return $query->where('is_delete',0)->where('is_freeze',0);
                }
            ])
            ->get();

        if (empty($studentBind)) return [];

        $arr = collect($studentBind)->filter(function ($item) use ($type) {
            if ($type == 1) {
                return !empty($item->student_club) && $item->student_club->status == 1;
            } else {
                return !empty($item->student_club) && $item->student_club->status == 2;
            }
        });

        if ($arr->isEmpty()) return [];

        return $arr->toArray();
    }

    /**
     * 获取绑定过学员的用户数
     * @param Request $request
     * @return array
     */
    public function getHasBindStudentsUsersCount(Request $request)
    {
        $appUserMobiles = $request->all();

        $bindStudentUserCount = ClubStudentBindApp::notDelete()->whereIn('app_account',$appUserMobiles)
            ->pluck('app_account');

        $count = collect($bindStudentUserCount)->unique()->count();

        return returnMessage('200','',['count' => $count]);
    }

    /**
     * 获取俱乐部学员统计数据
     * @param Request $request
     * @return array
     */
    public function getClubBindStudentsStatics(Request $request)
    {
        $date = $request->input('date');
        $clubId = $request->input('clubId');

        $clubs = Club::when($clubId > 0,function ($query) use ($clubId){
                return $query->where('id',$clubId);
            })
            ->select('id','name')->get();

        if ($clubs->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $bindStudents = ClubStudentBindApp::notDelete()
            ->when($date,function ($query) use ($date) {
                return $query->where('created_at','>=',Carbon::parse($date)->startOfMonth())
                    ->where('created_at','<=',Carbon::parse($date)->endOfMonth());
            })
            ->with(['student_club:id,club_id,name'])
            ->groupBy('student_id')
            ->select('student_id')
            ->get();

        $arr = [];
        collect($clubs)->each(function ($club) use (&$arr,$bindStudents,&$num) {
            $arr[$club->id] = [
                'clubName' => $club->name,
                'bindUserCount' => 0
            ];
        });

        collect($bindStudents)->each(function ($student) use (&$arr,&$num) {
            if (!empty($student->student_club) && collect($arr)->has($student->student_club->club_id)) {
                $arr[$student->student_club->club_id]['bindUserCount'] += 1;
            }
        });

        return returnMessage('200','',array_values($arr));
    }

    /**
     * 获取app用户绑定学员信息
     * @param Request $request
     * @return array
     */
    public function getUserBindStudents(Request $request)
    {
        $appUserMobiles = $request->all();

        $bindStudents = ClubStudentBindApp::notDelete()
            ->with(['student_club','student_club.club'])
            ->whereIn('app_account',$appUserMobiles)
            ->get();


        $arr = [];
        collect($appUserMobiles)->each(function ($mobile) use (&$arr,$bindStudents) {
            collect($bindStudents)->each(function ($student) use (&$arr,$mobile) {

                if ($student->app_account == $mobile) {
                    $arr[$mobile][] = [
                        'name' => !empty($student->student_club) ? $student->student_club->name : '',
                        'studentId' => $student->student_id,
                        'club' => !empty($student->student_club) && !empty($student->student_club->club) ? $student->student_club->club->name : '',
                        'createdAt' => !empty($student->student_club) ? Carbon::parse($student->student_club->created_at)->toDateTimeString() : '',
                    ];
                }
            });
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 获取app用户绑定的一个学员信息
     * @param Request $request
     * @return array
     */
    public function getUsersBindOneStudent(Request $request)
    {
        $appUserMobiles = $request->all();

        $bindStudents = ClubStudentBindApp::notDelete()
            ->with(['student_club'])
            ->whereIn('app_account',$appUserMobiles)
            ->get();

        $arr = [];
        collect($appUserMobiles)->each(function ($mobile) use (&$arr,$bindStudents) {
            collect($bindStudents)->each(function ($student) use (&$arr,$mobile) {
                if ($student->app_account == $mobile && empty($arr[$mobile])) {
                    $arr[$mobile] = [
                        'stuName' => !empty($student->student_club) ? $student->student_club->name : '',
                        'stuId' => $student->student_id,
                        'stuAge' => !empty($student->student_club) ? Common::getAgeByBirthday($student->student_club->birthday) : '',
                        'guardRole' => !empty($student->student_club) ? Common::getGuardRoleName($student->student_club->guarder) : ''
                    ];
                }
            });
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 根据俱乐部ID获取俱乐部信息
     * @param Request $request
     * @return array
     */
    public function getClubsByClubIds(Request $request)
    {
        $clubIds = $request->all();

        $clubs = Club::whereIn('id',$clubIds)
            ->pluck('name','id');

        $arr = [];
        if ($clubs->isNotEmpty()) {
            $arr = $clubs->toArray();
        }

        return returnMessage('200','',$arr);
    }

    /**
     * 根据俱乐部获取场馆
     * @param Request $request
     * @return array
     */
    public function venuesByClubIds(Request $request)
    {
        $clubIds = $request->all();

        $venues = ClubVenue::notDelete()->valid()
            ->whereIn('club_id',$clubIds)
            ->select('name','id')
            ->get();

        $arr = [];
        collect($venues)->each(function ($item) use (&$arr) {
            $arr[] = [
                'id' => $item->id,
                'name' => $item->name
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 获取场馆班级列表
     * @param Request $request
     * @return array
     */
    public function classesByVenueIds(Request $request)
    {
        $venueIds = $request->all();

        $classes = ClubClass::whereIn('venue_id',$venueIds)
            ->valid()
            ->select('id','name')
            ->get();

        $arr = [];
        collect($classes)->each(function ($item) use (&$arr) {
            $arr[] = [
                'id' => $item->id,
                'name' => $item->name
            ];
        });

        return returnMessage('200','',$arr);
    }

    /**
     * 根据相关筛选条件获取学员信息
     * @param Request $request
     * @return array
     */
    public function findStudentsByClassPrams(Request $request)
    {
        $classIds = json_decode($request->input('classIds'),true);
        $studentStatus = $request->input('studentStatus');
        $leftCourseCount = $request->input('leftCourseCount');
        $freezeStatus = $request->input('freezeStatus');

        $classes = ClubClass::valid()
            ->whereIn('id',$classIds)
            ->select('id','name')
            ->get();

        if ($classes->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $classStudents = ClubClassStudent::notDelete()
            ->whereIn('class_id',$classIds)->select('class_id','student_id')->get();

        if ($classStudents->isEmpty()) {
            return returnMessage('200','',[]);
        }

        //将没有绑定过app用户的stuId给过滤掉
        $bindStudentIds = ClubStudentBindApp::notDelete()
            ->whereIn('student_id',collect($classStudents)->pluck('student_id'))
            ->pluck('student_id')
            ->unique();

        if ($bindStudentIds->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $students = ClubStudent::when($studentStatus !== null,function ($query) use ($studentStatus) {
            $query->where('status',$studentStatus);
        })
            ->when($freezeStatus !== null,function ($query) use ($freezeStatus) {
                $query->where('is_freeze',$freezeStatus);
            })
            ->when($leftCourseCount !== null,function ($query) use ($leftCourseCount) {
                if ($leftCourseCount == 0) {//剩余课时数为0次
                    $query->where('left_course_count',0);
                } elseif ($leftCourseCount == 1) {//剩余课时数为1-3次
                    $query->whereIn('left_course_count',[1,2,3]);
                } else {//剩余课时数为4次以上
                    $query->where('left_course_count','>=',4);
                }
            })
            ->notDelete()
            ->whereIn('id',$bindStudentIds)
            ->pluck('name','id');

        if ($students->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $arr = [];
        collect($classes)->each(function ($item) use (&$arr,$students) {
            $arr[$item->id] = [
                'classId' => $item->id,
                'className' => $item->name,
                'students' => []
            ];
        });

        collect($classStudents)->each(function ($item) use (&$arr,$students) {
            if (collect($arr)->has($item->class_id) && collect($students)->has($item->student_id)) {
                $arr[$item->class_id]['students'][] = [
                    'id' => $item->student_id,
                    'name' => $students[$item->student_id]
                ];
            }
        });

        if (empty($arr)) {
            return returnMessage('200','',[]);
        }

        return returnMessage('200','',array_values($arr));
    }

    /**
     * 根据学员的类型获取学员绑定的用户手机号
     * @param Request $request
     * @return array
     */
    public function getBindUserMobileForStudentsByStuType(Request $request)
    {
        //styType    0:全部，1:正式学员,2:非正式学员
        $stuType = $request->input('stuType');

        $studentIds = ClubStudent::notDelete()
            ->notFreeze()
            ->when($stuType > 0,function ($query) use ($stuType) {
                return $query->where('status',$stuType);
            })
            ->pluck('id');

        if ($studentIds->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $bindUsers = ClubStudentBindApp::notDelete()
            ->whereIn('student_id',$studentIds)
            ->pluck('app_account')
            ->unique();

        if ($bindUsers->isEmpty()) {
            return returnMessage('200','',[]);
        }

        return returnMessage('200','',$bindUsers->toArray());
    }

    /**
     * 根据学员ID获取对应绑定的手机号
     * @param Request $request
     * @return array
     */
    public function getBindUserMobileForStudentsByStuIds(Request $request)
    {
        $stuIds = $request->all();

        $studentIds = ClubStudent::notDelete()
            ->notFreeze()
            ->whereIn('id',$stuIds)
            ->pluck('id');

        if ($studentIds->isEmpty()) {
            return returnMessage('200','',[]);
        }

        $bindUsers = ClubStudentBindApp::notDelete()
            ->whereIn('student_id',$studentIds)
            ->pluck('app_account')
            ->unique();

        if ($bindUsers->isEmpty()) {
            return returnMessage('200','',[]);
        }

        return returnMessage('200','',$bindUsers->toArray());
    }

    /**
     * 根据省市区code获取地址名称
     * @param Request $request
     * @return array
     */
    public function getAddressNameByCodes(Request $request)
    {
        $proviceCode = $request->input('proCode');
        $cityCode = $request->input('cityCode');
        $countryCode = $request->input('countryCode');

        $proviceName = ClubProvince::where('code',$proviceCode)->value('name');
        $cityName = ClubCity::where('code',$cityCode)->value('name');
        $countryName = ClubDistrict::where('code',$countryCode)->value('name');

        $arr = [
            'proviceName' => $proviceName ? $proviceName : '',
            'cityName' => $cityName ? $cityName : '',
            'countryName' => $countryName ? $countryName : '',
        ];

        return returnMessage('200','',$arr);
    }

    /**
     * 更改俱乐部公告状态
     * @param Request $request
     * @return array
     */
    public function changeClubNoticeStatus(Request $request)
    {
        $clubNoticeId = $request->input('clubNoticeId');
        $status = $request->input('status');

        $clubMessApp = ClubMessageApp::notDelete()
            ->find($clubNoticeId);

        if (empty($clubMessApp)) {
            return returnMessage('2701',config('error.clubMessage.2701'));
        }

        $clubMessApp->status = $status == 1 ? 1 : -1;
        $clubMessApp->check_datetime = Carbon::now()->toDateTimeString();

        try {
            $clubMessApp->saveOrFail();
        } catch (Exception $e) {
            return returnMessage('2702',config('error.clubMessage.2702'));
        }

        return returnMessage('200');
    }

    /**
     * 通过学员ID获取销售ID
     * @param Request $request
     * @return array
     */
    public function getSalesIdByStudentId(Request $request)
    {
        $stuId = $request->input('stuId');

        $clubStudent = ClubStudent::notDelete()->find($stuId);

        if (empty($clubStudent)) {
            return returnMessage('1610',config('error.Student.1610'));
        }

        if (empty($clubStudent->sales_id)) {
            $salesId = ClubSales::notDelete()->where('club_id',$clubStudent->club_id)
                ->where('sales_name','默认销售')
                ->value('id');

            if (empty($salesId)) {
                return returnMessage('1801',config('error.sales.1801'));
            }

            return returnMessage('200','',['salesId' => $salesId]);
        }

        return returnMessage('200','',['salesId' => $clubStudent->sales_id]);
    }

    /**
     * 奖励明细
     * @param Request $request
     * @return array
     */
    public function rewardDetails(Request $request)
    {
        $appUserMobile = $request->input('appUserMobile');
        //$appUserMobile = '18602119566';

        $reserveRecords = ClubRecommendReserveRecord::with(['rewards','recommend_payment'])->where('user_mobile',$appUserMobile)
            ->orderByDesc('created_at')
            ->get();

        $totalCourseCout = 0;   //总课时数
        $totalReserve = 0;  //已预约总数
        $totalExperience = 0;   //总体验数
        $totalPay = 0;  //总付费数
        $totalExpire = 0;   //已过退费总数

        $arr = [];
        collect($reserveRecords)->each(function ($item) use (&$arr,&$totalCourseCout,&$totalExperience,&$totalReserve,&$totalPay,&$totalExpire) {
            if ($item->rewards->isNotEmpty()) {
                collect($item->rewards)->each(function ($reward) use (&$totalCourseCout,&$totalExperience,&$totalReserve) {
                    if ($reward->settle_status == 2) {
                        $totalCourseCout += $reward->reward_course_num;
                    }

                    if ($reward->event_type == 1) {
                        $totalReserve++;
                        if ($reward->settle_status == 2) {
                            $totalExperience++;
                        }
                    }
                });
            }

            if ($item->recommend_status == 3) {
                $totalPay++;
            }

            if (!empty($item->recommend_payment) && $item->recommend_payment->reward_status == 2) {
                $totalExpire++;
            }

            if (!empty($item->recommend_payment) && $item->recommend_payment->reward_status == 2) {
                $status = 4;
            } else {
                $status = $item->recommend_status;
            }

            $arr[] = [
                'id' => $item->new_stu_id,
                'tel' => $item->new_mobile,
                'status' => $status
            ];
        });

        $returnData = [
            'totalCourseCout' => $totalCourseCout,
            'totalReserve' => $totalReserve,
            'totalExperience' => $totalExperience,
            'totalPay' => $totalPay,
            'totalExpire' => $totalExpire,
            'reward' => $arr
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 获取未绑定商品的缴费方案
     * @param Request $request
     * @return array
     */
    public function getUnBindClubPayments(Request $request)
    {
        $planIds = explode(',',$request->input('planIds'));
        $key = $request->input('key');

        $clubIds = [];
        if (!empty($key)) {
            $clubIds = Club::notDelete()->valid()
                ->where('name','LIKE',$key.'%')
                ->pluck('id');

            if ($clubIds->isNotEmpty()) $clubIds = $clubIds->toArray();
        }

        $clubPayments = ClubPayment::valid()
            ->with(['club:id,name'])
            ->where('tag',2)
            ->when(!empty($planIds),function ($query) use ($planIds) {
                return $query->whereNotIn('id',$planIds);
            })
            ->when(!empty($clubIds),function ($query) use ($clubIds) {
                return $query->whereIn('club_id',$clubIds);
            })
            ->whereHas('club',function ($query) {
                return $query->where('club_club.status',1);
            })
            ->get();

        $arr = [];

        collect($clubPayments)->each(function ($item) use (&$arr){
            $arr[] = [
                'planId' => $item->id,
                'planName' => $item->name,
                'clubId' => $item->club_id,
                'clubName' => $item->club ? $item->club->name : '',
                'typeName' => Common::getPaymentTypeName($item->type),
                'tagName' => Common::getPaymentTagName($item->tag)
            ];
        });

        return returnMessage('200','',$arr);
    }


}
