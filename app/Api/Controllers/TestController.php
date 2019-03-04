<?php
/**
 * Created by PhpStorm.
 * User: zhanjin
 * Date: 2018/6/21
 * Time: 下午4:14
 */

namespace App\Api\Controllers;

use App\Facades\Util\Common;
use App\Http\Controllers\Controller;

use App\Model\ClubUser\ClubUser;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Services\Common\CommonService;
use App\Services\Student\StudentService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Claims\JwtId;
use Tymon\JWTAuth\Facades\JWTAuth;


use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Facades\Util\Sms;
use App\Model\ClubSales\ClubSales;
use Illuminate\Support\Facades\Redis;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubVenue\ClubVenue;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubPayment\ClubPayment;
use App\Model\Recommend\ClubCourseReward;
use Exception;
use App\Facades\Util\Log;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\Recommend\ClubRecommendRewardRecord;
use App\Model\ClubStudent\ClubStudent;
use App\Facades\ClubStudent\Student;

class TestController extends Controller
{
    private $studentService;

    CONST CONDITION_COUNT_FOR_REWARD = 1;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    public function test(Request $request)
    {
        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-start');

        //reward_status 默认0，1：未发放，2：已发放，3：已失效
        $stuPayments = ClubStudentPayment::where('reward_status','>',0)
            ->where('reward_status',1)
            ->get();

        if ($stuPayments->isEmpty()) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-暂时没有奖励的推荐的用户');
            return;
        }

        $courseTickets = ClubCourseTickets::notDelete()
            ->whereIn('payment_id',collect($stuPayments)->pluck('id'))
            ->where('status',1)
            ->select('payment_id', DB::raw('COUNT(*) as used_count'))
            ->groupBy('payment_id')
            ->havingRaw('used_count >= '.self::CONDITION_COUNT_FOR_REWARD)
            ->get();

        if ($courseTickets->isEmpty()) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-没有满足达到指定签到次数需要发放奖励的记录');
            return;
        }

        $ticketsPaymentIds = collect($courseTickets)->pluck('payment_id');
        $studentPayments = ClubStudentPayment::with(['refund'])->whereIn('id',$ticketsPaymentIds)
            ->get();

        if ($studentPayments->isEmpty()) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-数据有误',['paymentIds' => $ticketsPaymentIds->toArray()]);
            return;
        }

        try {
            DB::transaction(function () use ($studentPayments) {
                collect($studentPayments)->each(function ($item) {
                    //如果有退款的，缴费记录reward_status需要置为3：已失效
                    if (!empty($item->refund)) {
                        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-更新有退款的缴费记录失败',['stuPaymentId' => $item->id]);
                        $item->reward_status = 3;
                        $item->saveOrFail();
                    }

                    try {
                        $this->addBuyRewardToRecommendStudent($item->recommend_id);
                        $item->reward_status = 2;
                        $item->saveOrFail();
                    } catch (Exception $e) {
                        $arr = [
                            'code' => $e->getCode(),
                            'msg' => $e->getMessage()
                        ];
                        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-添加奖励数据有误',[$arr]);
                    }

                });
            });
        } catch (Exception $e) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-赠送买单奖励数据操作错误',['paymentIds' => $ticketsPaymentIds->toArray()]);
            return;
        }

        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-end',['paymentIds' => $ticketsPaymentIds->toArray()]);

        echo 'success';
    }

    /**
     * 给推荐用户添加买单奖励记录
     * @param $recommendId
     * @throws Exception
     * @throws \Throwable
     */
    public function addBuyRewardToRecommendStudent($recommendId)
    {
        $reserveRecord = ClubRecommendReserveRecord::find($recommendId);

        if (empty($reserveRecord)) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-推荐奖励记录不存在',['recommend_id' => $recommendId]);
            throw new Exception(config('error.recommend.2307'),'2307');
        }

        if ($reserveRecord->recommend_status == 3) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-推荐奖励记录状态有误',['recommend_id' => $recommendId,'status' => $reserveRecord->recommend_status]);
            throw new Exception(config('error.recommend.2308'),'2308');
        }

        $rewardRecord = ClubRecommendRewardRecord::where('recommend_id',$recommendId)
            ->where('event_type',2)
            ->first();

        if (empty($rewardRecord)) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-买单奖励记录不存在',['recommend_id' => $recommendId]);
            throw new Exception(config('error.recommend.2307'),'2307');
        }

        if ($rewardRecord->settle_status == 2) {//已结算，不用处理
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-买单奖励已发放',['recommend_id' => $recommendId]);
            return;
        }

        $buyCourseRewardNum = $rewardRecord->reward_course_num; //买单奖励课时数
        if ($buyCourseRewardNum > 0) {
            //获取活动缴费方案
            $activityPayment = ClubPayment::where('club_id',$rewardRecord->club_id)
                ->where('tag',3)
                ->where('is_default', 1)
                ->first();

            if (empty($activityPayment)) {
                Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-该俱乐部下没有活动缴费方案',['clubId' => $rewardRecord->club_id]);
                return;
            }

            $sales = ClubSales::find($reserveRecord->sale_id);

            if (empty($sales)) {
                Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-学员无销售',['recommendId' => $reserveRecord->id]);
                return;
            }

            $student = ClubStudent::notDelete()->find($rewardRecord->stu_id);

            if (empty($student)) {
                Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-奖励学员不存在',['recommend_stu_id' => $rewardRecord->stu_id]);
                return;
            }

            for ($i=0;$i<$buyCourseRewardNum;$i++) {
                $stuPayment = $this->addStudentPayment($reserveRecord,$rewardRecord,$activityPayment,$sales);
                $this->addStudentsTickets($stuPayment,$activityPayment);
            }

            $this->addCourseCountToStudent($student,$buyCourseRewardNum);
        }

        $reserveRecord->recommend_status = 3;   //更改推荐记录状态

        $rewardRecord->settle_status = 2;   //更改奖励记录状态

        $reserveRecord->saveOrFail();
        $rewardRecord->saveOrFail();

    }

    /**
     * 添加缴费方案（活动缴费）
     * @param ClubRecommendReserveRecord $reserveRecord
     * @param ClubRecommendRewardRecord $rewardRecord
     * @param ClubPayment $activityPayment
     * @param ClubSales $sales
     * @return ClubStudentPayment
     * @throws \Throwable
     */
    public function addStudentPayment(ClubRecommendReserveRecord $reserveRecord,ClubRecommendRewardRecord$rewardRecord,ClubPayment $activityPayment,ClubSales $sales)
    {
        $stuPayment = new ClubStudentPayment();
        $stuPayment->student_id = $rewardRecord->stu_id;
        $stuPayment->club_id = $rewardRecord->club_id;
        $stuPayment->payment_id = $activityPayment->id;
        $stuPayment->payment_name = $activityPayment->name;
        $stuPayment->payment_tag_id = $activityPayment->tag;
        $stuPayment->payment_class_type_id = $activityPayment->type;
        $stuPayment->course_count = $activityPayment->course_count;
        $stuPayment->pay_fee = $activityPayment->price;
        $stuPayment->equipment_issend = 0;
        $stuPayment->payment_date = Carbon::now()->toDateString();
        $stuPayment->channel_type = 4;
        $stuPayment->expire_date = Carbon::now()->addMonth($activityPayment->use_to_date)->toDateString();
        $stuPayment->is_pay_again = 0;
        $stuPayment->sales_id = $reserveRecord->sale_id;
        $stuPayment->sales_dept_id = $sales->sales_dept_id;
        $stuPayment->reserve_record_id = $rewardRecord->id;

        $stuPayment->saveOrFail();

        return $stuPayment;
    }

    /**
     * 添加课程券(活动缴费方案券)
     * @param $stuPayment
     * @param $activityPayment
     * @throws \Throwable
     */
    public function addStudentsTickets($stuPayment,$activityPayment)
    {
        $tickets = new ClubCourseTickets();
        $tickets->payment_id = $stuPayment->id;
        $tickets->club_id = $stuPayment->club_id;
        $tickets->student_id = $stuPayment->student_id;
        $tickets->expired_date = Carbon::now()->addMonth($activityPayment->use_to_date)->toDateString();
        $tickets->status = 2;
        $tickets->unit_price = 0;
        $tickets->reward_type = 2;

        $tickets->saveOrFail();
    }

    /**
     * 给推荐的学员增加课时数
     * @param ClubStudent $clubStudent
     * @param $buyCourseRewardNum
     * @throws Exception
     * @throws \Throwable
     */
    public function addCourseCountToStudent(ClubStudent $clubStudent,$buyCourseRewardNum)
    {
        $clubStudent->left_course_count = $clubStudent->left_course_count + $buyCourseRewardNum;

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

    public function testTest()
    {
        $data = DB::table('club_course')->where('status',1)
            ->where('day','2018-07-23')
            ->select('id','start_time')
            ->get();

        $courseIds = [];
        $data->each(function ($item) use (&$courseIds) {
            $courseStartDate = Carbon::createFromFormat('Y-m-d H:i:s',Carbon::now()->format('Y-m-d').' '.$item->start_time);

            if (Carbon::now()->gte($courseStartDate->subHours(2))) {
                $courseIds[] = $item->id;
            }

        });

        //dd($courseIds);

        //根据要上课的courseId找出需要发送短信的
        $courseStudents = ClubCourseSign::with(['club','Student','course','course.venue','class','class.teachers'])
            ->whereIn('course_id',$courseIds)
            //->whereNull('sign_status')
            ->get();

        //dd($courseStudents->toArray());

        $data = [];
        $courseStudents->each(function ($item) use (&$data) {
            //if (!empty($item->Student->guarder_mobile)) {
            $stuStr = $item->student->name;
            $clubStr = $item->club->name;
            $courseTimeStr = $item->course->day.' ';
            $courseTimeStr .= Carbon::createFromFormat('Y-m-d H:i:s',$item->course->day. ' '.$item->course->start_time)
                    ->format('H:i').'-';
            $courseTimeStr .= Carbon::createFromFormat('Y-m-d H:i:s',$item->course->day. ' '.$item->course->end_time)
                ->format('H:i');
            $venueStr = $item->course->venue->name;

            $teacherId = !empty($item->class->teachers) ? $item->class->teachers[0]->teacher_id : 0;
            $teacher = $this->getTeacherContract($teacherId);

            $contactStr = !empty($teacher) ? $teacher['mobile'].' '.$teacher['name'] : '';
            $data2 = [
                $stuStr,
                $clubStr,
                $courseTimeStr,
                $venueStr,
                $contactStr
            ];

            $data = $data2;

            //Sms::sendSms($item->Student->guarder_mobile,$data,'1111');
            //Sms::sendSms('15055349677',$data2,'270690');
            //}
        });

        dd($data);
    }

    /**
     * 获取班主任联系方式(班主任通常也是销售)
     * @param $teacherId
     * @return array|string
     */
    public function getTeacherContract($teacherId)
    {
        if ($teacherId <= 0) return [];

        $teacher = ClubSales::find($teacherId);

        if (empty($teacher)) return [];

        return [
            'mobile' => $teacher->mobile,
            'name' => $teacher->sales_name
        ];
    }

    public function test2(){
        return 1;
    }

    /*************测试分界线**************/


    public function login(Request $request){
        $input = $request->all();
        $username = $input['username'];
        $password = $input['password'];
        $code = $input['code'];

        $user = ClubUser::where('username', $username)->first();
        $token = '';
        if($user->password == $password){

            $token = JWTAuth::fromUser($user);

        }

        $user->token = $token;

        return $user;
    }

    public function getUserInfo(Request $request){

        $user = JWTAuth::parseToken()->authenticate();
        $isAdmin = $user->role->is_admin;

        return $user;

    }

    public function logout(Request $request){
        $token = JWTAuth::getToken();
        JWTAuth::invalidate($token);

        return 'ok';
    }

    // Tracy test
    public function studentReserve()
    {
        $classId = 31;
        $venueId = 22;
        $courseId = 41;
        $classId = 154;
        $contractSn = '5b7c658868227';
       return  Common::compoundOnePoster('李四',1,1,1);
    }

    public function test1()
    {
        echo 111;
    }

    /**
     * 初始化二维码推广缴费方案的方法（别删）
     */
    public function initQrcodePayments()
    {
        $clubIds = Club::valid()->notDelete()->pluck('id');

        if ($clubIds->isNotEmpty()) {
            try {
                DB::transaction(function () use ($clubIds) {
                    collect($clubIds)->each(function ($clubId) use (&$arr) {
                        //添加二维码缴费方案
                        $payment = new ClubPayment();
                        $payment->club_id = $clubId;
                        $payment->name = "二维码推广赠送课时";
                        $payment->payment_tag = '二维码';
                        $payment->type = 1;
                        $payment->tag = 3;
                        $payment->price = 0;
                        $payment->original_price = 0;
                        $payment->min_price = 0;
                        $payment->course_count = 1;
                        $payment->use_to_student_type = 1;
                        $payment->private_leave_count = 0;
                        $payment->show_in_app = 0;
                        $payment->limit_to_buy = 0;
                        $payment->is_free = 1;
                        $payment->is_default = 1;
                        $payment->status = 1;
                        $payment->saveOrFail();

                        //添加奖励课时设置（默认）
                        $courseReward = new ClubCourseReward();
                        $courseReward->club_id = $clubId;
                        $courseReward->num_for_try = 1;
                        $courseReward->num_for_buy = 3;
                        $courseReward->saveOrFail();
                    });
                });
            } catch (Exception $e) {
                dd($e->getMessage());
            }
        }

        dd('success');
    }
}
