<?php

namespace App\Services\Subscribe;

use App\Model\ClubChannel\Channel;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubStudentCore\ClubStudentCore;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;
use App\Services\Common\CommonService;
use Illuminate\Support\Facades\DB;
use App\Facades\ClubStudent\Student;
use Carbon\Carbon;
use App\Facades\Util\Common;
use App\Facades\Util\Log;

use Exception;

class SubscribeService
{
    /**
     * APP预约学员
     * @param $classId
     * @param $venueId
     * @param $courseId
     * @param $clubId
     * @param $studentName
     * @param $age
     * @param $sex
     * @param $mobile
     * @return mixed
     * @throws Exception
     */
    public function studentReserve($classId, $venueId, $courseId, $clubId, $studentName, $age, $sex, $mobile)
    {
        Log::setGroup('Subscribe')->error('app预约',['classId' => $classId,'venueId' => $venueId, 'courseId' => $courseId, 'clubId' => $clubId]);

        // 学员信息
        $student = ClubStudent::where('name', $studentName)
            ->where('guarder_mobile', $mobile)
            ->where('club_id', $clubId)
            ->first();

        // 班级信息
        $class = ClubClass::find($classId);
        if (empty($class)) {
            throw new Exception(config('error.class.1406'), '1406');
        }

        // 体验缴费
        $freePayment = ClubPayment::where('club_id', $clubId)
            ->where('is_free', 1)
            ->where('is_default', 1)
            ->first();
        if (empty($freePayment)) {
            throw new Exception(config('error.class.1418'), '1418');
        }

        // 默认销售
        $defaultSales = Student::getDefaultStuData($clubId, 3);
        if (empty($defaultSales)) {
            throw new Exception(config('error.Student.1681'),'1681');
        }

        // 新学员预约
        if (empty($student)) {
            $studentId = DB::transaction(function () use ($clubId, $venueId, $classId, $studentName, $mobile, $sex, $age, $courseId, $class, $freePayment, $defaultSales) {
                // 添加学员
                $studentId = $this->addStudentAndClass($clubId, $venueId, $classId, $studentName, $mobile, $sex, $age, $defaultSales->id);

                // 绑定账号
                $isBindApp = ClubStudentBindApp::where('student_id', $studentId)
                    ->where('app_account', $mobile)
                    ->exists();

                if ($isBindApp === false) {
                    $this->studentBindApp($mobile, $studentId);
                }

                // 添加课程签到
                $signId = $this->addCourseSign($clubId, $courseId, $classId, $studentId, $class->type);

                // 添加预约信息
                $this->addStudentSubscribe($studentId, $defaultSales->id, $courseId, $classId, $clubId, $signId);

                // 添加体验缴费
                $stuPayId = $this->addStudentPayment($studentId, $clubId, $freePayment->id, $freePayment->name, $freePayment->tag, $freePayment->type, $defaultSales->id, $freePayment->price, $freePayment->use_to_date);

                // 添加课程券
                $this->addCourseTicket($stuPayId, $freePayment->tag, $clubId, $studentId, $freePayment->type, $freePayment->price, $freePayment->use_to_date);

                return $studentId;
            });

            return ['id' => $studentId];
        }

        // 正式学员不可预约
        if ($student->status == 1) {
            throw new Exception(config('error.Student.1678'), '1678');
        }

        // 预约体验过不可预约
        if ($student->ex_status == 2) {
            throw new Exception(config('error.Student.1637'), '1637');
        }

        $subscribe = ClubStudentSubscribe::where('club_id', $clubId)
            ->where('student_id', $student->id)
            ->where('is_delete', 0)
            ->first();

        DB::transaction(function () use ($student, $mobile, $clubId, $courseId, $classId, $class, $subscribe, $freePayment) {
            // 删除预约过的预约记录
            $subscribe->is_delete = 1;
            $subscribe->save();

            // 删除预约过的体验缴费
            ClubStudentPayment::where('club_id', $clubId)
                ->where('student_id', $student->id)
                ->where('payment_id', $freePayment->id)
                ->update(['is_delete' => 1]);

            // 删除预约过的课程券
            ClubCourseTickets::where('club_id', $clubId)
                ->where('student_id', $student->id)
                ->where('payment_id', $freePayment->id)
                ->update(['is_delete' => 1]);

            // 删除预约过的课程签到
            ClubCourseSign::where('club_id', $clubId)
                ->where('student_id', $student->id)
                ->where('class_id', $class->id)
                ->where('course_id', $courseId)
                ->update(['is_delete' => 1]);

            // 添加课程签到
            $signId = $this->addCourseSign($clubId, $courseId, $classId, $student->id, $class->type);

            // 添加预约信息
            $this->addStudentSubscribe($student->id, $class->teacher_id, $courseId, $classId, $clubId, $signId);

            // 添加体验缴费
            $stuPayId = $this->addStudentPayment($student->id, $clubId, $freePayment->id, $freePayment->name, $freePayment->tag, $freePayment->type, $class->teacher_id, $freePayment->price, $freePayment->use_to_date);

            // 添加课程券
            $this->addCourseTicket($stuPayId, $freePayment->tag, $clubId, $student->id, $freePayment->type, $freePayment->price, $freePayment->use_to_date);
        });

        return ['id' => $student->id];
    }

    // 创建学员班级
    public function addStudentAndClass($clubId, $venueId, $classId, $studentName, $mobile, $sex, $age, $salesId)
    {
        // 创建学员
        $insertData = [
            'club_id' => $clubId,
            'sales_id' => $salesId,
            'sales_name' => ClubSales::where('id', $salesId)->value('sales_name'),
            'venue_id' => $venueId,
            'name' => $studentName,
            'sex' => $sex,
            'main_class_id' => $classId,
            'main_class_name' => ClubClass::where('id', $classId)->value('name'),
            'channel_id' => 4,
            'guarder_mobile' => $mobile,
            'birthday' => Common::getBirthdayByAge($age),
            'age' => $age,
            'status' => 2,
            'ex_status' => 1,
            'left_course_count' => 1,
            'serial_no' => Student::buildStudentSerialNo(),
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time())
        ];
        $studentId = ClubStudent::insertGetId($insertData);

        // 创建班级
        $studentClass = new ClubClassStudent();
        $studentClass->club_id = $clubId;
        $studentClass->venue_id = $venueId;
        $studentClass->sales_id = $salesId;
        $studentClass->class_id = $classId;
        $studentClass->student_id = $studentId;
        $studentClass->student_name = $studentName;
        $studentClass->enter_class_time = date('Y-m-d', time());
        $studentClass->save();

        return $studentId;
    }

    // 学员绑定账号
    public function studentBindApp($mobile, $studentId)
    {
        $bind = new ClubStudentBindApp();
        $bind->student_id = $studentId;
        $bind->app_account = $mobile;
        $bind->save();
    }

    // 添加体验缴费
    public function addStudentPayment($studentId, $clubId, $paymentId, $paymentName, $tagId, $classTypeId, $salesId, $price, $expireDate)
    {
        $studentPayment = new ClubStudentPayment();
        $studentPayment->student_id = $studentId;
        $studentPayment->club_id = $clubId;
        $studentPayment->payment_id = $paymentId;
        $studentPayment->payment_name = $paymentName;
        $studentPayment->payment_tag_id = $tagId;
        $studentPayment->payment_class_type_id = $classTypeId;
        $studentPayment->sales_id = $salesId;
        $studentPayment->course_count = 1;
        $studentPayment->pay_fee = $price;
        $studentPayment->equipment_issend = 0;
        $studentPayment->is_free = 1;
        $studentPayment->expire_date = date('Y-m-d', time() + $expireDate * 30 * 86400);
        $studentPayment->payment_date = Carbon::now()->format('Y-m-d');
        $studentPayment->saveOrFail();
        $stuPayId = $studentPayment->id;
        return $stuPayId;
    }

    // 添加课程签到
    public function addCourseSign($clubId, $courseId, $classId, $studentId, $classType)
    {
        $insertSignData = [
            'club_id' => $clubId,
            'course_id' => $courseId,
            'class_id' => $classId,
            'student_id' => $studentId,
            'sign_status' => 0,
            'is_subscribe' => 1,
            'class_type_id' => $classType,
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time())
        ];
        $signId = ClubCourseSign::insertGetId($insertSignData);
        return $signId;
    }

    // 添加课程券
    public function addCourseTicket($stuPayId, $tagId, $clubId, $studentId, $classTypeId, $price, $expireDate)
    {
        $tickets = new ClubCourseTickets();
        $tickets->payment_id = $stuPayId;
        $tickets->tag_id = $tagId;
        $tickets->club_id = $clubId;
        $tickets->student_id = $studentId;
        $tickets->expired_date = date('Y-m-d', time() + $expireDate * 30 * 86400);
        $tickets->status = 2;
        $tickets->class_type_id = $classTypeId;
        $tickets->unit_price = $price;
        $tickets->is_subscribe = 1;
        $tickets->save();
    }

    // 添加预约信息
    public function addStudentSubscribe($studentId, $salesId, $courseId, $classId, $clubId, $signId)
    {
        $subscribe = new ClubStudentSubscribe();
        $subscribe->student_id = $studentId;
        $subscribe->sales_id = $salesId;
        $subscribe->course_id = $courseId;
        $subscribe->class_id = $classId;
        $subscribe->club_id = $clubId;
        $subscribe->channel_id = 4;
        $subscribe->type = 1;
        $subscribe->sign_id = $signId;
        $subscribe->ex_status = 0;
        $subscribe->save();
    }


    /**
     * APP用户购买课程
     * @param $classId
     * @param $venueId
     * @param $clubId
     * @param $paymentId
     * @param $stuName
     * @param $stuIdCardNum
     * @param $appUserMobile
     * @return array
     * @throws Exception
     */
    public function confirmStudentInfoForBuy($classId, $venueId, $clubId, $paymentId, $stuName, $stuIdCardNum, $appUserMobile)
    {
        // 是否存在学员信息
        $student = ClubStudent::where('name', $stuName)
            ->where('guarder_mobile', $appUserMobile)
            ->where('club_id', $clubId)
            ->first();

        // 缴费信息
        $payment = ClubPayment::where('club_id', $clubId)
            ->where('id', $paymentId)
            ->first();
        if (empty($payment)) {
            throw new Exception(config('error.Payment.2101'), 2101);
        }

        // 新学员购买
        if (empty($student)) {
            $studentId = DB::transaction(function () use ($stuIdCardNum, $stuName, $clubId, $venueId, $classId, $appUserMobile, $payment, $student)
            {
                $coreId = $this->addStudentCoreForBuy($stuIdCardNum, $stuName);

                $studentId = $this->addStudentInfoForBuy($clubId, $coreId, $venueId, $classId, $stuName, $appUserMobile, $stuIdCardNum);

                return $studentId;
            });

            return ['id' => $studentId];
        }

        // 老学员购买
        return ['id' => $student->id];
    }
    // 添加证件信息
    public function addStudentCoreForBuy($idCard, $studentName)
    {
        $core = ClubStudentCore::where('card_type', 1)
            ->where('card_no', $idCard)
            ->first();

        if (!empty($core)) {
            return $core->id;
        }

        $insertData = [
            'chinese_name' => $studentName,
            'card_type' => 1,
            'card_no' => $idCard,
            'created_at' => date('Y-m-d', time()),
            'updated_at' => date('Y-m-d', time())
        ];

        $coreId = ClubStudentCore::insertGetId($insertData);
        return $coreId;
    }
    // 购买添加学员信息
    public function addStudentInfoForBuy($clubId, $coreId, $venueId, $classId, $studentName, $mobile, $idCard)
    {
        $birthday = getBirthdayByIdCard($idCard);
        $insertData = [
            'club_id' => $clubId,
            'core_id' => $coreId,
            'sales_id' => ClubClass::where('id', $classId)->value('teacher_id'),
            'venue_id' => $venueId,
            'name' => $studentName,
            'main_class_id' => $classId,
            'main_class_name' => ClubClass::where('id', $classId)->value('name'),
            'channel_id' => 4,
            'channel_name' => Channel::where('id', 4)->value('channel_name'),
            'guarder_mobile' => $mobile,
            'birthday' => $birthday,
            'age' => Carbon::parse($birthday)->diffInYears(),
            'status' => 2,
            'ex_status' => 2,
            'serial_no' => Student::buildStudentSerialNo(),
            'created_at' => date('Y-m-d H:i:s', time()),
            'updated_at' => date('Y-m-d H:i:s', time())
        ];
        $studentId = ClubStudent::insertGetId($insertData);
        return $studentId;
    }


    /**
     * 取消预约
     * @param $reserveId
     * @param $appUserMobile
     * @throws Exception
     */
    public function cancelReserve($reserveId,$appUserMobile)
    {
        $reserve = ClubStudentSubscribe::notDelete()
            ->where('type',1)
            ->find($reserveId);

        if (empty($reserve)) {
            throw new Exception(config('error.subscribe.1902'),'1902');
        }

        if (empty($reserve->sign_id)) {
            throw new Exception(config('error.subscribe.1906'),'1906');
        }

        if ($reserve->subscribe_status == 3) {//已取消
            throw new Exception(config('error.subscribe.1903'),'1903');
        } elseif ($reserve->subscribe_status == 2) {//未出勤，不能取消
            throw new Exception(config('error.subscribe.1903'),'1904');
        } elseif ($reserve->subscribe_status == 1) {//已出勤，不能取消
            throw new Exception(config('error.subscribe.1903'),'1905');
        }

        $bindStuExists = ClubStudentBindApp::notDelete()
            ->where('student_id',$reserve->student_id)
            ->where('app_account',$appUserMobile)
            ->exists();

        if ($bindStuExists === false) {
            throw new Exception(config('error.common.1003'),'1003');
        }

        try {
            DB::transaction(function () use ($reserve) {
                $reserve->subscribe_status = 3;
                $reserve->saveOrFail();

                $this->cancelCourseSign($reserve->sign_id);
                $this->cancelCourseTicketAndPayment($reserve->student_id);
            });
        } catch (Exception $e) {
            Log::setGroup('ReserveError')->error('取消课程券和缴费记录失败',[$e->getMessage()]);
            throw new Exception(config('error.subscribe.1907'),'1907');
        }
    }

    /**
     * 取消课程签到
     * @param $signId
     */
    public function cancelCourseSign($signId)
    {
        if (empty($signId)) return;
        $courseSign = ClubCourseSign::notDelete()
            ->where('id',$signId)
            //->where('is_subscribe',1)
            ->first();

        if (empty($courseSign)) return;

        $courseSign->is_delete = 1;
        $courseSign->saveOrFail();
    }

    /**
     * 取消课程和缴费记录
     * @param $studentId
     * @throws Exception
     */
    public function cancelCourseTicketAndPayment($studentId)
    {
        $stuPayment = ClubStudentPayment::notDelete()
            ->where('student_id',$studentId)
            ->where('payment_tag_id',1)
            ->first();

        if (empty($stuPayment)) {
            throw new Exception(config('error.subscribe.1908'),'1908');
        }

        $ticket =  ClubCourseTickets::notDelete()
            ->where('payment_id',$stuPayment->id)
            ->first();

        if (empty($ticket)) {
            throw new Exception(config('error.subscribe.1909'),'1909');
        }

        try {
            $stuPayment->is_delete = 1;
            $ticket->is_delete = 1;
            $stuPayment->saveOrFail();
            $ticket->saveOrFail();
        } catch (Exception $e) {
            Log::setGroup('ReserveError')->error('取消课程券和缴费记录失败',[$e->getMessage()]);

            throw new Exception($e->getMessage(),$e->getCode());
            //throw new Exception(config('error.subscribe.1907'),'1907');
        }

    }

}