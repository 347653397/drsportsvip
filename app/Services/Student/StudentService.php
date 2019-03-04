<?php

namespace App\Services\Student;

use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubIncomeSnapshot\ClubIncomeSnapshot;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubVenue\ClubVenue;
use App\Model\Recommend\ClubRecommendRewardRecord;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use App\Facades\Util\Log;
use App\Model\Recommend\ClubRecommendReserveRecord;

class StudentService
{
    // 生成学员序列号
    public function buildStudentSerialNo()
    {
        $serialNo = rand_char().dec62(msectime());
        $student = ClubStudent::where('serial_no', $serialNo)->exists();

        if ($student === true) {
            $serialNo = $this->buildStudentSerialNo();
        }

        return $serialNo;
    }

    /**
     * 更新学员的core_id
     * @param $stuId
     * @param $studentCoreId
     * @throws Exception
     */
    public function updateStudentCoreId($stuId,$studentCoreId)
    {
        if ($stuId <= 0 || $studentCoreId <= 0) {
            throw new Exception(config('error.common.1001'),'1001');
        }

        $student = ClubStudent::notDelete()->find($stuId);

        // 学员不存在
        if (empty($student)) {
            throw new Exception(config('error.Student.1610'),'1610');
        }

        $student->core_id = $studentCoreId;

        try {
            $student->saveOrFail();
        } catch (Exception $e) {
            throw new Exception(config('error.common.1011'),'1011');
        }
    }

    /**
     * 支付成功
     * @param $stuId
     * @param $planId
     * @param $orderId
     * @param $classId
     * @param $contractSn
     * @return array
     * @throws Exception
     */
    public function changeStuStatus($stuId, $planId, $orderId, $classId, $contractSn)
    {
        $student = ClubStudent::notDelete()->with(['sales'])->find($stuId);

        // 学员不存在
        if (empty($student)) {
            throw new Exception(config('error.Student.1610'),'1610');
        }

        $payment = ClubPayment::valid()->showInApp()->find($planId);

        if (empty($payment)) {
            throw new Exception(config('error.Payment.2101'),'2101');
        }

        $clubClass = ClubClass::valid()->showInApp()->find($classId);

        if (empty($clubClass)) {
            throw new Exception(config('error.class.1406'),'1406');
        }

        if (empty($student->sales)) {
            throw new Exception(config('error.class.1406'),'1406');
        }
        $sales = $student->sales;

        try {
            $studentPayId = 0;
            DB::transaction(function () use ($student, $planId, $stuId, $payment, $orderId, $classId, $clubClass, $contractSn, &$studentPayId,$sales) {
                // 更新学员为正式学员
                $student->status = $payment->tag==2 ? 1 : 2;
                $student->left_course_count = $student->left_course_count + $payment->course_count;
                $student->pay_count = $student->pay_count + 1;
                $student->pay_amount = $student->pay_amount + $payment->price;
                $student->saveOrFail();

                // 班级不存在添加班级
                $classStudent = ClubClassStudent::where('club_id', $student->club_id)
                    ->where('student_id', $stuId)
                    ->where('class_id', $classId)
                    ->exists();

                if ($classStudent === false) {
                    $this->addClassByBuyCourse($student->club_id, $clubClass->venue_id, $clubClass->id, $stuId, $student->name);
                }

                // 添加学员缴费
                try {
                    $studentPayment = $this->addStudentPaymentByBuyCourse($student,$sales,$payment,$orderId,$contractSn);
                } catch (Exception $e) {
                    throw new Exception($e->getMessage(),$e->getCode());
                }

                $studentPayId = $studentPayment->id;

                // 添加课程券
                $this->addStudentTicketForBuy($studentPayId, $student->club_id, $student->id, $payment->tag, $payment->use_to_date, $payment->type, $payment->price, $payment->course_count);

                // 绑定学员
                $this->studentBindApp($student->guarder_mobile, $student->id);
            });
        } catch (Exception $e) {
            throw new Exception(config('error.common.1011'),'1011');
        }

        return ['paymentRecordId' => $studentPayId];
    }

    /**
     * 购买添加课程券，不能使用状态
     * @param $paymentId
     * @param $clubId
     * @param $studentId
     * @param $expireDate
     * @param $classType
     * @param $price
     * @param $courseCount
     */
    public function addStudentTicketForBuy($paymentId, $clubId, $studentId, $tagId, $expireDate, $classType, $price, $courseCount)
    {
        for ($i = 1; $i <= $courseCount; $i++) {
            $tickets = new ClubCourseTickets();
            $tickets->payment_id = $paymentId;
            $tickets->tag_id = $tagId;
            $tickets->club_id = $clubId;
            $tickets->student_id = $studentId;
            $tickets->expired_date = Carbon::now()->addMonth($expireDate)->toDateString();
            $tickets->status = 2;
            $tickets->class_type_id = $classType;
            $tickets->unit_price = bcdiv($price,$courseCount,2);
            $tickets->save();
        }
    }

    /**
     * 购买课程添加缴费
     * @param ClubStudent $student
     * @param ClubSales $sales
     * @param ClubPayment $payment
     * @param $orderId 订单编号
     * @param $contractSn 合同编号
     * @return ClubStudentPayment
     * @throws Exception
     * @throws \Throwable
     */
    public function addStudentPaymentByBuyCourse(ClubStudent $student,ClubSales $sales,ClubPayment $payment,$orderId,$contractSn)
    {
        //查看是否有正式缴费的记录
        $checkExistsOffical = ClubStudentPayment::where('student_id',$student->id)
            ->where('payment_tag_id',2)
            ->exists();

        $is_pay_again = 0;
        if ($checkExistsOffical == true) {
            $is_pay_again = 1;
        }

        $stuPayment = new ClubStudentPayment();
        $stuPayment->student_id = $student->id;
        $stuPayment->club_id = $student->club_id;
        $stuPayment->payment_id = $payment->id;
        $stuPayment->payment_name = $payment->name;
        $stuPayment->payment_tag_id = $payment->tag;
        $stuPayment->payment_class_type_id = $payment->type;
        $stuPayment->course_count = $payment->course_count;
        $stuPayment->pay_fee = $payment->price;
        $stuPayment->equipment_issend = 0;
        $stuPayment->is_free = 0;
        if (!empty($contractSn)) {//线上缴费线上签
            $stuPayment->pay_type = 3;
        }
        $stuPayment->payment_date = Carbon::now()->toDateString();
        $stuPayment->channel_type = 4;
        $stuPayment->expire_date = Carbon::now()->addMonth($payment->use_to_date)->toDateString();
        $stuPayment->is_pay_again = $is_pay_again;
        $stuPayment->order_id = $orderId;
        $stuPayment->sales_id = $student->sales_id;
        $stuPayment->sales_dept_id = $sales->sales_dept_id;
        $stuPayment->contract_no = $contractSn;
        $stuPayment->is_app_pay = 1;

        try {
            DB::transaction(function () use ($stuPayment,$student,$is_pay_again) {
                Log::setGroup('RecommendError')->error('推广奖励记录-买单发放奖励记录-start');
                if ($is_pay_again == 0 && $student->from_stu_id > 0) {
                    Log::setGroup('RecommendError')->error('推广奖励记录-符合发送条件',['stuId' => $student->id]);
                    $reserveRecords = ClubRecommendReserveRecord::notDelete()
                        ->where('new_stu_id',$student->id)
                        ->where('stu_id',$student->from_stu_id)
                        ->first();

                    if (empty($reserveRecords)) {
                        Log::setGroup('RecommendError')->error('推广奖励记录异常-推广记录不存在',['studentId' => $student->id]);
                        return;
                    }

                    //奖励处于已发放状态，不必执行发放逻辑
                    if ($reserveRecords->recommend_status == 3) {
                        Log::setGroup('RecommendError')->error('推广奖励记录-买单奖励已经发放',['stuId' => $student->id]);
                        return;
                    }

                    $reserveRecords->recommend_status = 3;
                    $reserveRecords->saveOrFail();

                    try {
                        $this->addBuyRewardRecordsToRecommendStudent($reserveRecords);
                    } catch (Exception $e) {
                        $arr = [
                            'code' => $e->getCode(),
                            'msg' => $e->getMessage()
                        ];
                        Log::setGroup('RecommendError')->error('推广奖励记录异常-给推荐学员添加奖励失败',[$arr]);
                        throw new Exception($e->getMessage(),$e->getCode());
                    }

                    $stuPayment->reward_status = 1; //学员买单时，给此学员的推荐学员
                }

                $stuPayment->saveOrFail();
            });
        } catch (Exception $e) {
            //dd($e->getMessage());
            $arr = [
                'code' => $e->getCode(),
                'msg' => $e->getMessage()
            ];
            Log::setGroup('StuPaymentError')->error('app支付回调成功-学员添加缴费操作失败',[$arr]);
            throw new Exception($e->getMessage(),$e->getCode());
        }

        //dd(2);

        return $stuPayment;
    }

    // 购买课程添加班级
    public function addClassByBuyCourse($clubId, $venueId, $classId, $studentId, $studentName)
    {
        $studentClass = new ClubClassStudent();
        $studentClass->club_id = $clubId;
        $studentClass->venue_id = $venueId;
        $studentClass->class_id = $classId;
        $studentClass->sales_id = ClubClass::where('id', $classId)->value('teacher_id');
        $studentClass->student_id = $studentId;
        $studentClass->student_name = $studentName;
        $studentClass->is_main_class = 1;
        $studentClass->enter_class_time = Carbon::now()->format('Y-m-d');
        $studentClass->save();
    }

    // 学员绑定账号
    public function studentBindApp($mobile, $studentId)
    {
        $bind = new ClubStudentBindApp();
        $bind->student_id = $studentId;
        $bind->app_account = $mobile;
        $bind->save();
    }

    /**
     * 默认场馆、班级、销售
     * @param $clubId
     * @param $type
     * @return mixed
     */
    public function getDefaultStuData($clubId, $type)
    {
        if ($type == 1) {
            $data = ClubVenue::where('club_id', $clubId)
                ->where('name', '默认场馆')
                ->orWhere('is_default', 1)
                ->first();
        }
        if ($type == 2) {
            $data = ClubClass::where('club_id', $clubId)
                ->where('name', '默认班级')
                ->orWhere('is_default', 1)
                ->first();
        }
        if ($type == 3) {
            $data = ClubSales::where('club_id', $clubId)
                ->where('sales_name', '默认销售')
                ->orWhere('is_default', 1)
                ->first();
        }

        return $data;
    }

    /**
     * 学员绑定APP信息
     * @param $studentId
     * @return mixed
     */
    public function studentBindAppList($studentId)
    {
        $bind = ClubStudentBindApp::where('student_id', $studentId)->get();

        $result = $bind->transform(function ($items) {
            $arr['mobile'] = $items->app_account;
            $arr['bindDate'] = $items->created_at->format('Y.m.d');
            return $arr;
        });

        return $result;
    }
     /**
     * 给推荐用户添加买单奖励记录
     * @param ClubRecommendReserveRecord $reserveRecords
     * @throws Exception
     * @throws \Throwable
     */
    public function addBuyRewardRecordsToRecommendStudent(ClubRecommendReserveRecord $reserveRecords)
    {
        $checkRecordExists = ClubRecommendRewardRecord::where('recommend_id',$reserveRecords->id)
            ->where('event_type',2)
            ->exists();

        if ($checkRecordExists == true) {
            Log::setGroup('RecommendError')->error('推广奖励记录异常-买单奖励记录已存在',['recommend_id' => $reserveRecords->id]);
            return;
        }

        $tryRewardRecord = ClubRecommendRewardRecord::where('recommend_id',$reserveRecords->id)
            ->where('event_type',1)
            ->first();

        if (empty($tryRewardRecord)) {
            throw new Exception(config('error.recommend.2306'),'2306');
        }

        $rewardRecord = new ClubRecommendRewardRecord();
        $rewardRecord->club_id = $reserveRecords->club_id;
        $rewardRecord->recommend_id = $reserveRecords->id;
        $rewardRecord->user_mobile = $reserveRecords->user_mobile;
        $rewardRecord->stu_id = $tryRewardRecord->stu_id;
        $rewardRecord->stu_name = $tryRewardRecord->stu_name;
        $rewardRecord->new_mobile = $tryRewardRecord->new_mobile;
        $rewardRecord->new_stu_id = $tryRewardRecord->new_stu_id;
        $rewardRecord->new_stu_name = $tryRewardRecord->new_stu_name;
        $rewardRecord->event_type = 2;

        $rewardNumForBuy = $rewardRecord->getClubCourseRewardNumForBuy($reserveRecords->club_id);
        $rewardRecord->reward_course_num = $rewardNumForBuy['buyCourseRewardNum'];

        $rewardRecord->settle_status = 1;

        $rewardRecord->saveOrFail();
    }

    /**
     * 统计学员签到次数
     * @param $clubId
     * @param $stuId
     * @param $status 1出勤 2缺勤 3病假 4事假
     * @return mixed
     */
    public function getStuSignStatusTimes($clubId, $stuId, $status)
    {
        $count = ClubCourseSign::where('club_id', $clubId)
            ->where('student_id', $stuId)
            ->where('sign_status', $status)
            ->where('is_delete', 0)
            ->count();

        return $count;
    }

    /**
     * 统计学员缴费金额
     * @param $stuId
     * @param $clubId
     * @return mixed
     */
    public function getStuPaymentAmount($clubId, $stuId)
    {
        $sum = ClubStudentPayment::where('club_id', $clubId)
            ->where('student_id', $stuId)
            ->where('is_delete', 0)
            ->sum('pay_fee');

        return $sum;
    }

    /**
     * 统计学员销课收入
     * 出勤、缺勤、事假
     * @param $stuId
     * @param $clubId
     * @return mixed
     */
    public function getStuCourseSignIncome($clubId, $stuId)
    {
        $signId = ClubCourseSign::where('club_id', $clubId)
            ->where('student_id', $stuId)
            ->whereIn('sign_status', [1, 2, 3])
            ->pluck('id');

        $sum = ClubCourseTickets::where('club_id', $clubId)
            ->where('student_id', $stuId)
            ->whereIn('sign_id', $signId)
            ->sum('unit_price');

        return $sum;
    }

    /**
     * 统计学员签到mvp次数
     * @param $clubId
     * @param $stuId
     * @return mixed
     */
    public function getStuSignMvpTimes($clubId, $stuId)
    {
        $count = ClubCourseSign::where('club_id', $clubId)
            ->where('student_id', $stuId)
            ->where('ismvp', 1)
            ->where('is_delete', 0)
            ->count();

        return $count;
    }

    /**
     * 获取课程券数量
     * @param $stuId
     * @param $clubId
     * @param $type int 1正式券，2体验券，3赠送券
     * @param $used int 1课程券总数，2剩余数量
     * @return mixed
     */
    public function getStudentCourseCount($clubId, $stuId, $type, $used)
    {
        if ($type == 1) {
            if ($used == 1) {
                $count = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->where('tag_id', 2)
                    ->count();
            }
            else {
                $count = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->where('status', 2)
                    ->where('tag_id', 2)
                    ->count();
            }
        }

        if ($type == 2) {
            if ($used == 1) {
                $count = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->where('tag_id', 1)
                    ->count();
            }
            else {
                $count = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->where('status', 2)
                    ->where('tag_id', 1)
                    ->count();
            }
        }

        if ($type == 3) {
            if ($used == 1) {
                $count1 = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->Where('tag_id', 3)
                    ->count();
                $count2 = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->Where('reward_type', '>', 0)
                    ->count();
                $count = $count1 + $count2;
            }
            else {
                $count1 = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->where('status', 2)
                    ->Where('tag_id', 3)
                    ->count();
                $count2 = ClubCourseTickets::where('club_id', $clubId)
                    ->where('student_id', $stuId)
                    ->where('status', 2)
                    ->Where('reward_type', '>', 0)
                    ->count();
                $count = $count1 + $count2;
            }
        }

        return $count;
    }


}