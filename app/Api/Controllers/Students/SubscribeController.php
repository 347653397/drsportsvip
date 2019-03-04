<?php

namespace App\Api\Controllers\Students;

use App\Facades\ClubStudent\Student;
use App\Facades\Util\Common;
use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubUser\ClubUser;
use App\Model\Recommend\ClubCourseReward;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\Recommend\ClubRecommendRewardRecord;
use App\Model\Recommend\ClubRecommendUser;
use App\Services\Common\CommonService;

use App\Services\Student\StudentService;
use Carbon\Carbon;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Model\ClubErrorLog\ClubErrorLog;
use Exception;
use App\Facades\Util\Log;

class SubscribeController extends Controller
{
    /**
     * userToken 前缀
     */
    CONST USER_TOKEN_SALT = 'user_identity!@#';

    /**
     * appToken 前缀
     */
    CONST APP_TOKEN_SALT = 'single_login!@#';

    /**
     * @var CommonService
     */
    private $commonService;

    /**
     * @var StudentService
     */
    private $studentService;

    private $base_url;
    /**
     * subscribeController constructor.
     * @param CommonService $commonService
     * @param StudentService $studentService
     */
    public function __construct(CommonService $commonService, StudentService $studentService)
    {
        $this->commonService = $commonService;
        $this->studentService = $studentService;
        $this->base_url = env('H5_INNER_URL');
    }

    /**
     * 二维码海报
     * @param Request $request
     * @return array
     * @throws \Exception
     */
    public function studentCodePoster(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'mobile' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $mobile = $input['mobile'];

        $student = ClubStudentBindApp::with(['student'])
            ->where('app_account', $mobile)
            ->get();

        $list = [];

        collect($student)->each(function($item) use (&$list,$mobile) {
            $list[] = [
                'studentId' => $item->student_id,
                'studentName' => $item->student ? $item->student->name : '',
                'appUserMobile' => $mobile,
                'isPoster' => $item->student && !empty($item->student->qrcode_url) ? 1 : 0,
                'clubId' => $item->student ? $item->student->club_id : null,
                'salesId' => $item->student ? $item->student->sales_id : null
            ];
        });

        $keyArr = $this->commonService->compoundPoster($list);
        //return returnMessage('200', '', $keyArr);

        // 更新学员二维码
        try {
            DB::transaction(function () use ($keyArr) {
                foreach ($keyArr as $key => $value) {
                    $clubStudent = ClubStudent::notDelete()->find($value['studentId']);
                    if (!empty($clubStudent)) {
                        $clubStudent->qrcode_url = $value['imgKey'];
                        $clubStudent->saveOrFail();
                    }
                }
            });
        } catch (Exception $e) {
            return returnMessage($e->getCode(), $e->getMessage());
        }

        $student = ClubStudentBindApp::with(['student','student.club'])
            ->where('app_account', $mobile)
            ->get();

        $list = [];

        collect($student)->each(function($item) use (&$list,$mobile) {

            $list[] = [
                'studentId' => $item->student_id,
                'appUserMobile' => $mobile,
                'studentName' => $item->student ? $item->student->name : '',
                'clubId' => $item->student ? $item->student->club_id : null,
                'salesId' => $item->student ? $item->student->sales_id : null,
                'clubName' => $item->student && $item->student->club ? $item->student->club->name : '',
                'poster' => $item->student ? env('IMG_DOMAIN').$item->student->qrcode_url : ''
            ];
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 通过分享预约
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function subscribeByQrCode(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
            'mobile' => 'required|string',
            'salesId' => 'nullable|numeric',
            'name' => 'required|string',
            'sex' => 'required|numeric',
            'age' => 'required|numeric',
            'fromStuId' => 'nullable|numeric',
            'appUserMobile' => 'nullable|string'
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        Log::setGroup('SubscribeError')->error('预约学员参数', ['input' => $input]);

        $student = ClubStudent::where('name', $input['name'])
            ->where('guarder_mobile', $input['mobile'])
            ->where('club_id', $input['clubId'])
            ->first();

        // 默认场馆
        $defaultVenue = Student::getDefaultStuData($input['clubId'], 1);
        Log::setGroup('SubscribeError')->error('默认场馆', ['defaultVenue' => $defaultVenue]);

        //return returnMessage('009', '', $defaultVenue);

        if (empty($defaultVenue)) {
            return returnMessage('1680', config('error.Student.1680'));
        }

        // 默认班级
        $defaultClass = Student::getDefaultStuData($input['clubId'], 2);
        Log::setGroup('SubscribeError')->error('默认班级', ['defaultClass' => $defaultClass]);
        if (empty($defaultClass)) {
            return returnMessage('1681', config('error.Student.1681'));
        }

        // 推荐销售或默认销售
        if (!isset($input['salesId']) || empty($input['salesId'])) {
            $reverseSales = Student::getDefaultStuData($input['clubId'], 3);
        } else {
            $reverseSales = ClubSales::find($input['salesId']);
        }
        Log::setGroup('SubscribeError')->error('默认销售', ['reverseSales' => $reverseSales]);
        if (empty($reverseSales)) {
            return returnMessage('1683', config('error.Student.1683'));
        }

        // 体验缴费方案
        $freePayment = ClubPayment::where('club_id', $input['clubId'])
            //->where('is_free', 1)
            ->where('tag',1)
            ->where('is_default', 1)
            ->first();

        Log::setGroup('SubscribeError')->error('默认体验缴费方案', ['freePayment' => $freePayment]);
        if (empty($freePayment)) {
            return returnMessage('2105', config('error.Student.2105'));
        }

        $fromStuId = isset($input['fromStuId']) ? $input['fromStuId'] : 0;
        $appUserMobile = isset($input['appUserMobile']) ? $input['appUserMobile'] : '';

        // todo 新学员预约
        if (empty($student)) {
            $studentId = DB::transaction(function () use ($input, $freePayment, $defaultVenue, $defaultClass, $reverseSales,$fromStuId,$appUserMobile) {
                // 添加学员
                $student = $this->createClubStudent($input['clubId'], $input['name'], $reverseSales->id, $reverseSales->sales_name, $input['mobile'], $input['sex'], $input['age'], $defaultClass->id, $defaultClass->name, $defaultVenue->id);
                $studentId = $student->id;

                // 绑定账号
                $isBindApp = ClubStudentBindApp::where('student_id', $studentId)
                    ->where('app_account', $input['mobile'])
                    ->exists();

                if ($isBindApp === false) {
                    $this->studentBindApp($input['mobile'], $studentId, $input['salesId']);
                }

                // 添加体验缴费
                $stuPayment = new ClubStudentPayment();
                $stuPayment->student_id = $studentId;
                $stuPayment->club_id = $input['clubId'];
                $stuPayment->payment_id = $freePayment->id;
                $stuPayment->payment_name = $freePayment->name;
                $stuPayment->payment_tag_id = $freePayment->tag;
                $stuPayment->payment_class_type_id = $freePayment->type;
                $stuPayment->course_count = $freePayment->course_count;
                $stuPayment->pay_fee = $freePayment->price;
                $stuPayment->equipment_issend = 0;
                $stuPayment->is_free = 1;
                $stuPayment->payment_date = Carbon::now()->format('Y-m-d');
                $stuPayment->channel_type = 4;
                $stuPayment->expire_date = date('Y-m-d', time() + $freePayment->use_to_date * 30 * 86400);
                $stuPayment->is_pay_again = 0;
                $stuPayment->sales_id = $input['salesId'];
                $stuPayment->sales_dept_id = $defaultClass->sales_dept_id;
                $stuPayment->save();
                $studentPayId = $stuPayment->id;

                // 添加课程券
                $tickets = new ClubCourseTickets();
                $tickets->payment_id = $studentPayId;
                $tickets->tag_id = $freePayment->tag;
                $tickets->club_id = $input['clubId'];
                $tickets->student_id = $studentId;
                $tickets->expired_date = date('Y-m-d', time()+10*86400);
                $tickets->status = 2;
                $tickets->class_type_id = $freePayment->type;
                $tickets->unit_price = $freePayment->price;
                $tickets->save();

                //添加推广记录
                if ($fromStuId > 0 && !empty($appUserMobile)) {
                    $recommendStuName = ClubStudent::where('id',$fromStuId)->value('name');

                    $paramData = [
                        'fromStuId' => $fromStuId,
                        'fromStuName' => $recommendStuName ?? '',
                        'appUserMobile' => $appUserMobile,
                        'clubId' => $input['clubId'],
                        'stuId' => $studentId,
                        'stuName' => $input['name'],
                        'age' => $input['age'],
                        'salesId' => $input['salesId'],
                        'newMobile' => $input['mobile']
                    ];

                    try {
                        $this->addRecommendReward($paramData);
                        $stuPayment->source_type = 2;
                        $student->from_stu_id = $fromStuId;
                        $student->saveOrFail();
                        //$stuPayment->recommend_id = $recommendInfo['reserveRecordId'];
                        $stuPayment->saveOrFail();
                    } catch (Exception $e) {
                        throw new Exception($e->getMessage(),$e->getCode());
                    }
                }

                return $studentId;
            });

            $data['studentId'] = $studentId;
            $data['studentName'] = $input['name'];
            $data['salesMobile'] = $reverseSales->mobile;
            $data['salesName'] = $reverseSales->sales_name;
            $data['isOldStudent'] = 0;

            $fromStudentName = '';
            if ($fromStuId > 0) {
                $fromStudentName = ClubStudent::notDelete()->where('id',$fromStuId)->value('name');
            }

            $data['fromStudentName'] = $fromStuId > 0 && $fromStudentName ? $fromStudentName : '';

            Log::setGroup('SubscribeError')->error('二维预约码添加学员返回，新学员', ['data' => $data]);

            return returnMessage('200', '', $data);
        }

        // 正式学员不可预约
        if ($student->status == 1) {
            return returnMessage('1678', config('error.Student.1678'));
        }

        // 预约体验过不可预约
        if ($student->ex_status == 2) {
            return returnMessage('1637', config('error.Student.1637'));
        }

        // todo 非正式学员预约
        try {
            DB::transaction(function () use ($input, $student, $freePayment, $defaultVenue, $defaultClass, $reverseSales, $fromStuId, $appUserMobile) {
                // 是否已预约过
                $stuPayment = ClubStudentPayment::where('club_id', $input['clubId'])
                    ->where('student_id', $student->id)
                    ->where('is_delete', 0)
                    ->where('is_free', 1)
                    ->first();

                if (empty($stuPayment)) {
                    // 绑定账号
                    $isBindApp = ClubStudentBindApp::where('student_id', $student->id)
                        ->where('app_account', $input['mobile'])
                        ->exists();
                    if ($isBindApp === false) {
                        $this->studentBindApp($input['mobile'], $student->id, $input['salesId']);
                    }

                    // 更新学员体验缴费
                    $stuPayment = new ClubStudentPayment();
                    $stuPayment->student_id = $student->id;
                    $stuPayment->club_id = $input['clubId'];
                    $stuPayment->payment_id = $freePayment->id;
                    $stuPayment->payment_name = $freePayment->name;
                    $stuPayment->payment_tag_id = $freePayment->tag;
                    $stuPayment->payment_class_type_id = $freePayment->type;
                    $stuPayment->course_count = $freePayment->course_count;
                    $stuPayment->pay_fee = $freePayment->price;
                    $stuPayment->equipment_issend = 0;
                    $stuPayment->is_free = 1;
                    $stuPayment->payment_date = Carbon::now()->format('Y-m-d');
                    $stuPayment->channel_type = 4;
                    $stuPayment->expire_date = date('Y-m-d', time() + $freePayment->use_to_date * 30 * 86400);
                    $stuPayment->is_pay_again = 0;
                    $stuPayment->sales_id = $input['salesId'];
                    $stuPayment->sales_dept_id = $defaultClass->sales_dept_id;
                    $stuPayment->saveOrFail();
                    $studentPayId = $stuPayment->id;

                    // 添加课程券
                    $tickets = new ClubCourseTickets();
                    $tickets->payment_id = $studentPayId;
                    $tickets->tag_id = $freePayment->tag;
                    $tickets->club_id = $input['clubId'];
                    $tickets->student_id = $student->id;
                    $tickets->expired_date = date('Y-m-d', time()+10*86400);
                    $tickets->status = 2;
                    $tickets->saveOrFail();

                    //添加推广记录
                    if ($fromStuId > 0 && !empty($appUserMobile)) {
                        $recommendStuName = ClubStudent::where('id',$fromStuId)->value('name');

                        $paramData = [
                            'fromStuId' => $fromStuId,
                            'fromStuName' => $recommendStuName ?? '',
                            'appUserMobile' => $appUserMobile,
                            'clubId' => $input['clubId'],
                            'stuId' => $student->id,
                            'stuName' => $input['name'],
                            'age' => $input['age'],
                            'salesId' => $input['salesId'],
                            'newMobile' => $input['mobile']
                        ];

                        try {
                            $this->addRecommendReward($paramData);
                            $stuPayment->source_type = 2;
                            $student->from_stu_id = $fromStuId;
                            $student->saveOrFail();
                            //$stuPayment->recommend_id = $recommendInfo['reserveRecordId'];
                            $stuPayment->saveOrFail();
                        } catch (Exception $e) {
                            throw new Exception($e->getMessage(),$e->getCode());
                        }
                    }
                }
            });
        } catch (Exception $e) {
            return returnMessage($e->getCode(),$e->getMessage());
        }

        $data['studentId'] = $student->id;
        $data['studentName'] = $input['name'];
        $data['salesMobile'] = $reverseSales->mobile;
        $data['salesName'] = $reverseSales->sales_name;
        $data['isOldStudent'] = 1;

        $fromStudentName = '';
        if ($fromStuId > 0) {
            $fromStudentName = ClubStudent::notDelete()->where('id',$fromStuId)->value('name');
        }

        $data['fromStudentName'] = $fromStuId > 0 && $fromStudentName ? $fromStudentName : '';

        Log::setGroup('SubscribeError')->error('二维预约码添加学员返回，老学员', ['data' => $data]);

        return returnMessage('200', '', $data);
    }

    /**
     * 二维码创建学员信息
     * @param $clubId
     * @param $name
     * @param $salesId
     * @param $salesName
     * @param $mobile
     * @param $sex
     * @param $age
     * @param $classId
     * @param $className
     * @param $venueId
     * @return ClubStudent
     */
    public function createClubStudent($clubId, $name, $salesId, $salesName, $mobile, $sex, $age, $classId, $className, $venueId)
    {
        // 创建学员
        $student = new ClubStudent();
        $student->club_id = $clubId;
        $student->venue_id = $venueId;
        $student->sales_id = $salesId;
        $student->sales_name = $salesName;
        $student->name = $name;
        $student->sex = $sex;
        $student->channel_id = 4;
        $student->guarder = 2;
        $student->guarder_mobile = $mobile;
        $student->age = $age;
        $student->birthday = Common::getBirthdayByAge($age);
        $student->status = 2;
        $student->main_class_id = $classId;
        $student->main_class_name = $className;
        $student->left_course_count = 1;
        $student->serial_no = Common::buildStudentSerialNo();
        $student->save();

        // 创建班级
        $studentClass = new ClubClassStudent();
        $studentClass->club_id = $clubId;
        $studentClass->venue_id = $venueId;
        $studentClass->sales_id = $salesId;
        $studentClass->class_id = $classId;
        $studentClass->student_id = $student->id;
        $studentClass->student_name = $name;
        $studentClass->student_status = 2;
        $studentClass->enter_class_time = date('Y-m-d', time());
        $studentClass->save();

        return $student;
    }

    // 学员绑定账号
    public function studentBindApp($mobile, $studentId, $salesId)
    {
        $bind = new ClubStudentBindApp();
        $bind->student_id = $studentId;
        $bind->student_sales_id = $salesId;
        $bind->app_account = $mobile;
        $bind->save();
    }


    /**
     * h5是否是老学员
     * @param Request $request
     * @return array
     */
    public function getStudentInfo(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'mobile' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 老学员
        $student = ClubStudent::where('guarder_mobile', $input['mobile'])
            ->where('club_id', 1)
            ->first();
        if (empty($student)) {
            $list['isOldStudent'] = 0;
            return returnMessage('200', '', $list);
        }

        // 新学员
        $list['isOldStudent'] = 1;
        $list['studentId'] = $student->id;
        $list['name'] = $student->name;
        $list['sex'] = $student->sex;
        $list['age'] = $student->age;
        $list['city'] = $student->city;
        $list['ballNum'] = $student->ball_num;
        return returnMessage('200', '', $list);
    }

    /**
     * 新学员录入
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function addStudentInfo(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'mobile' => 'required|string',
            'name' => 'required|string',
            'sex' => 'required|numeric',
            'age' => 'required|numeric',
            'city' => 'required|string',
            'ballNum' => 'required|numeric',
            'salesId' => 'nullable|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 老学员直接返回
        $student = ClubStudent::where('guarder_mobile', $input['mobile'])
            ->where('club_id', 1)
            ->first();
        if (!empty($student)) {
            $student->name = $input['name'];
            $student->sex = $input['sex'];
            $student->age = $input['age'];
            $student->city = $input['city'];
            $student->ball_num = $input['ballNum'];
            $student->saveOrFail();

            $list['studentId'] = $student->id;
            $list['salesId'] = $student->sales_id;
            return returnMessage('200', '', $list);
        }

        // 默认场馆
        $reverseVenue = Student::getDefaultStuData(1, 1);
        if (empty($reverseVenue)) {
            throw new Exception(config('error.Student.1680'),'1680');
        }

        // 默认班级
        $reverseClass = Student::getDefaultStuData(1, 2);
        if (empty($reverseClass)) {
            throw new Exception(config('error.Student.1681'),'1681');
        }

        // 推荐销售或默认销售
        if (!isset($input['salesId']) || empty($input['salesId'])) {
            $reverseSales = Student::getDefaultStuData(1, 3);
        }
        else {
            $reverseSales = ClubSales::find($input['salesId']);
        }
        if (empty($reverseSales)) {
            throw new Exception(config('error.Student.1683'),'1683');
        }

        $studentId = 0;
        DB::transaction(function () use ($input, &$studentId, $reverseVenue, $reverseClass, $reverseSales) {
            // 添加学员
            $student = new ClubStudent();
            $student->club_id = 1;
            $student->venue_id = $reverseVenue->id;
            $student->sales_id = $reverseSales->id;
            $student->sales_name = $reverseSales->sales_name;
            $student->name = $input['name'];
            $student->sex = $input['sex'];
            $student->channel_id = 4;
            $student->guarder = 2;
            $student->guarder_mobile = $input['mobile'];
            $student->age = $input['age'];
            $student->birthday = Common::getBirthdayByAge($input['age']);
            $student->status = 2;
            $student->main_class_id = $reverseClass->id;
            $student->main_class_name = $reverseClass->name;
            $student->left_course_count = 1;
            $student->city = $input['city'];
            $student->ball_num = $input['ballNum'];
            $student->serial_no = Common::buildStudentSerialNo();
            $student->saveOrFail();
            $studentId = $student->id;

            // 创建班级
            $studentClass = new ClubClassStudent();
            $studentClass->club_id = 1;
            $studentClass->venue_id = $reverseVenue->id;
            $studentClass->sales_id = $reverseSales->id;
            $studentClass->class_id = $reverseClass->id;
            $studentClass->student_id = $studentId;
            $studentClass->student_name = $input['name'];
            $studentClass->student_status = 2;
            $studentClass->is_main_class = 1;
            $studentClass->enter_class_time = Carbon::now()->format('Y-m-d');
            $studentClass->saveOrFail();

            // 绑定账号
            $bind = new ClubStudentBindApp();
            $bind->student_id = $studentId;
            $bind->student_sales_id = $reverseSales->id;
            $bind->app_account = $input['mobile'];
            $bind->saveOrFail();
        });

        $list['studentId'] = $studentId;
        $list['salesId'] = isset($student['sales_id']) ? $student['sales_id'] : 0;
        return returnMessage('200', '', $list);
    }

    /**
     * 预约二维码海报
     * @param Request $request
     * @return array
     */
    public function getQrCodeUrl(Request $request){
        $data = $request->all();
        $validate = Validator::make($data,[
            'studentId' => 'required|numeric',
            'salesId' => 'nullable|numeric',
            'clubId' => 'nullable|numeric'//目前只针对clubId 为1的
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $createTime = ClubStudent::where('id',$data['studentId'])->first();
        $data['salesId'] = isset($data['salesId']) ? $data['salesId'] : '';
        $data['clubId'] = isset($data['clubId']) ? $data['clubId'] : 1;
        $day = Carbon::parse($createTime->created_at)->diffInDays();


        $url = $this->base_url."event/iverson/form?clubId=".$data['clubId']."&salesId=".$data['salesId']."&fromUserId=".$data['studentId'];
        $result = [
            'url' => $url,
            'day' => $day
        ];
        return returnMessage('200','success',$result);
    }

    /**
     * h5预约成功
     * @param Request $request
     * @return array
     * @throws Exception
     */
    public function subscribeSuccess(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 预约学员信息
        $student = ClubStudent::with(['sales'])->find($input['studentId']);

        // 是否已预约过
        if ($student->ex_status == 2) {
            return returnMessage('200','', ['isSubscribe' => 0]);
        }

        // 体验缴费方案
        $freePayment = ClubPayment::where('club_id', 1)
            ->where('is_free', 1)
            ->where('is_default', 1)
            ->first();
        if (empty($freePayment)) {
            throw new Exception(config('error.Payment.2105'),'2105');
        }

        DB::transaction(function () use ($input, $student, $freePayment) {
            // 是否已预约过
            $stuPayment = ClubStudentPayment::where('club_id', 1)
                ->where('student_id', $input['studentId'])
                ->where('is_delete', 0)
                ->where('is_free', 1)
                ->first();
            if (!empty($stuPayment)) {
                // 删除已预约过的数据
                ClubStudentPayment::where('id', $stuPayment->id)
                    ->where('student_id', $input['studentId'])
                    ->where('club_id', 1)
                    ->update(['is_delete' => 1]);

                ClubCourseTickets::where('club_id', 1)
                    ->where('student_id', $input['studentId'])
                    ->where('payment_id', $stuPayment->id)
                    ->update(['is_delete' => 1]);

                ClubCourseSign::where('club_id', 1)
                    ->where('student_id', $input['studentId'])
                    ->where('is_experience', 1)
                    ->update(['is_delete' => 1]);
            }

            // 添加体验缴费
            $stuPayment = new ClubStudentPayment();
            $stuPayment->student_id = $student->id;
            $stuPayment->club_id = $input['clubId'];
            $stuPayment->payment_id = $freePayment->id;
            $stuPayment->payment_name = $freePayment->name;
            $stuPayment->payment_tag_id = $freePayment->tag;
            $stuPayment->payment_class_type_id = $freePayment->type;
            $stuPayment->course_count = $freePayment->course_count;
            $stuPayment->pay_fee = $freePayment->price;
            $stuPayment->equipment_issend = 0;
            $stuPayment->is_free = 1;
            $stuPayment->payment_date = Carbon::now()->format('Y-m-d');
            $stuPayment->channel_type = 4;
            $stuPayment->expire_date = date('Y-m-d', time() + $freePayment->use_to_date * 30 * 86400);
            $stuPayment->is_pay_again = 0;
            $stuPayment->is_experience = 1;
            $stuPayment->sales_id = $student->sales_id;
            $stuPayment->sales_dept_id = ClubUser::where('id', ClubSales::where('id', $student->sales_id)->value('user_id'))->value('dept_id');
            $stuPayment->saveOrFail();
            $studentPayId = $stuPayment->id;

            // 添加课程券
            $tickets = new ClubCourseTickets();
            $tickets->payment_id = $studentPayId;
            $tickets->tag_id = $freePayment->tag;
            $tickets->club_id = 1;
            $tickets->student_id = $student->id;
            $tickets->expired_date = date('Y-m-d', time()+10*86400);
            $tickets->status = 2;
            $tickets->saveOrFail();
        });

        Log::setGroup('SmsError')->error('艾弗森活动-短信发送start');
        if (!empty($student->sales)) {
            $paramData = [
                $student->name,
                $student->age,
                Common::getSexName($student->sex),
                $student->guarder_mobile
            ];

            $postData = [
                'userMobile' => $student->sales->mobile,
                'smsTitle' => 'iversonActivity',
                'smsSourceType' => 'CLUB',
                'paramData' => json_encode($paramData)
            ];

            Log::setGroup('SmsError')->error('艾弗森活动短信-发送数据',['postData' => $postData,'paramData' => $paramData]);

            $url = env('HTTPS_PREFIX').env('APP_INNER_DOMAIN').'sms/clubPushSms';
            $res = Common::curlPost($url,1,$postData);
            $res = json_decode($res,true);

            if ($res['code'] != '200') {
                Log::setGroup('SmsError')->error('艾弗森活动短信发送失败',['postData' => $postData,'paramData' => $paramData]);
            }

            Log::setGroup('SmsError')->error('艾弗森活动-短信发送end');
        } else {
            $arr = [
                'stuId' => $student->id,
                'stuName' => $student->name,
                'smsTitle' => 'iversonActivity',
                'smsSourceType' => 'CLUB'
            ];

            Log::setGroup('SmsError')->error('艾弗森活动-暂无销售，数据：',[$arr]);
        }

        return returnMessage('200','', ['isSubscribe' => 1]);
    }

    /**
     * 增加推荐奖励相关信息
     * @param $postData
     * @return array|mixed
     * @throws Exception
     * @throws \Throwable
     */
    public function addRecommendReward($postData)
    {
        $reserveRecordExists = ClubRecommendReserveRecord::where('user_mobile',$postData['appUserMobile'])
            ->where('stu_id',$postData['fromStuId'])
            ->where('new_stu_id',$postData['stuId'])
            ->exists();

        if ($reserveRecordExists == true) {
            Log::setGroup('RecommendError')->error('推广奖励-预约学员已经存在',['postData' => $postData]);
            return;
        }

        $recommendUser = ClubRecommendUser::where('student_id',$postData['fromStuId'])
            ->exists();

        try {
            if ($recommendUser === false) {
                $this->addRecommendUser($postData);     //添加推广用户
            }

            $recordInfo = $this->addRecommendReserveRecord($postData);      //添加推广预约记录
            $postData['reserveRecordId'] = $recordInfo['reserveRecordId'];
            $courseRewardInfo = $this->getClubCourseRewardNumForTry($postData['clubId']);   //获取体验奖励课时数
            $postData['tryRewardNum'] = $courseRewardInfo['tryRewardNum'];

            $this->addRecommendRewardRecordForTry($postData);   //添加体验推广奖励记录
        } catch (Exception $e) {
            Log::setGroup('RecommendError')->error('推广奖励相关信息添加失败',['postData' => $postData,'msg' => $e->getMessage()]);
            throw new Exception($e->getMessage(),$e->getCode());
        }

        return [
            'reserveRecordId' => $postData['reserveRecordId']
        ];
    }

    /**
     * 添加推荐用户记录
     * @param $postData
     * @throws \Throwable
     */
    public function addRecommendUser($postData)
    {
        $clubRecommendUser = new ClubRecommendUser();
        $clubRecommendUser->club_id = $postData['clubId'];
        $clubRecommendUser->student_id = $postData['fromStuId'];
        $clubRecommendUser->student_name = $postData['fromStuName'];
        $clubRecommendUser->user_mobile = $postData['appUserMobile'];

        try {
            $clubRecommendUser->saveOrFail();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 添加推荐预约学员记录
     * @param $postData
     * @return array
     * @throws \Throwable
     */
    public function addRecommendReserveRecord($postData)
    {
        $clubReserveRecord = new ClubRecommendReserveRecord();
        $clubReserveRecord->club_id = $postData['clubId'];
        $clubReserveRecord->new_stu_id = $postData['stuId'];
        $clubReserveRecord->new_stu_name = $postData['stuName'];
        $clubReserveRecord->new_stu_age = $postData['age'];
        $clubReserveRecord->new_mobile = $postData['newMobile'];
        $clubReserveRecord->stu_id = $postData['fromStuId'];
        $clubReserveRecord->user_mobile = $postData['appUserMobile'];
        $clubReserveRecord->sale_id = $postData['salesId'];
        $clubReserveRecord->recommend_status = 1;

        try {
            $clubReserveRecord->saveOrFail();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(),$e->getCode());
        }

        return [
            'reserveRecordId' => $clubReserveRecord->id
        ];
    }

    /**
     * 增加推广用户体验奖励记录
     * @param $postData
     * @throws Exception
     * @throws \Throwable
     */
    public function addRecommendRewardRecordForTry($postData)
    {
        $clubRecommendRewardRecord = new ClubRecommendRewardRecord();
        $clubRecommendRewardRecord->club_id = $postData['clubId'];
        $clubRecommendRewardRecord->recommend_id = $postData['reserveRecordId'];
        $clubRecommendRewardRecord->user_mobile = $postData['appUserMobile'];
        $clubRecommendRewardRecord->stu_id = $postData['fromStuId'];
        $clubRecommendRewardRecord->stu_name = $postData['fromStuName'];
        $clubRecommendRewardRecord->new_mobile = $postData['newMobile'];
        $clubRecommendRewardRecord->new_stu_id = $postData['stuId'];
        $clubRecommendRewardRecord->new_stu_name = $postData['stuName'];
        $clubRecommendRewardRecord->event_type = 1;
        $clubRecommendRewardRecord->reward_course_num = $postData['tryRewardNum'];
        $clubRecommendRewardRecord->settle_status = 1;

        try {
            $clubRecommendRewardRecord->saveOrFail();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(),$e->getCode());
        }
    }

    /**
     * 获取俱乐部体验奖励课时数
     * @param $clubId
     * @return array
     */
    public function getClubCourseRewardNumForTry($clubId)
    {
        $courseReward = ClubCourseReward::where('club_id',$clubId)->first();

        if (empty($courseReward)) {
            Log::setGroup('RecommendError')->error('俱乐部没有奖励课时设置',['clubId' => $clubId]);
            return ['tryRewardNum' => 0];
        }

        return ['tryRewardNum' => $courseReward->num_for_try];
    }
}