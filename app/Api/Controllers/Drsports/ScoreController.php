<?php
namespace App\Api\Controllers\Drsports;

use App\Http\Controllers\Controller;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\DB;

class ScoreController extends Controller
{
    /**
     * 兑换课程
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function exchangeCourse(Request $request)
    {
        $planId = $request->input('planId');
        $stuId = $request->input('stuId');
        $appUserMobile = $request->input('appUserMobile');
        $courseNum = $request->input('courseNum');

        $payment = ClubPayment::valid()->find($planId);

        if (empty($payment)) {
            return returnMessage('2101',config('error.Payment.2101'));
        }

        if ($payment->tag != 3) {//不是积分兑换课时的活动缴费
            return returnMessage('2106',config('error.Payment.2106'));
        }

        $bindStudentExists = ClubStudentBindApp::notDelete()
            ->where('student_id',$stuId)
            ->where('app_account',$appUserMobile)
            ->exists();

        if ($bindStudentExists === false) {
            return returnMessage('1684',config('error.Student.1684'));
        }

        $student = ClubStudent::notDelete()->find($stuId);

        if (empty($student)) {
            return returnMessage('1610',config('error.Student.1610'));
        }

        try {
            $this->addScoreCourseForStudent($student,$payment,$courseNum);
        } catch (Exception $e) {
            return returnMessage('2601',config('error.score.2601'));
        }

        return returnMessage('200');
    }

    /**
     * 增加一条积分兑换课时的活动缴费方案
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function addOneScoreCoursePayment(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric'
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        try {
            $clubPayment = $this->addScoreCoursePayment($input['clubId']);
        } catch (Exception $e) {
            return returnMessage('2107', config('error.Payment.2107'));
        }

        return returnMessage('200','',['planId' => $clubPayment->id]);
    }

    /**
     * 给学员添加积分兑换的课程
     * @param ClubStudent $student
     * @param ClubPayment $payment
     * @param $courseNum
     * @throws \Throwable
     */
    public function addScoreCourseForStudent(ClubStudent $student,ClubPayment $payment,$courseNum)
    {
        DB::transaction(function () use ($student,$payment,$courseNum) {
            for ($i=0;$i<$courseNum;$i++) {
                $studentPayment = new ClubStudentPayment();
                $studentPayment->student_id = $student->id;
                $studentPayment->club_id = $student->club_id;
                $studentPayment->payment_id = $payment->id;
                $studentPayment->payment_name = $payment->name;
                $studentPayment->payment_tag_id = $payment->tag;
                $studentPayment->payment_class_type_id = $payment->type;
                $studentPayment->sales_id = $student->sales_id;
                $studentPayment->course_count = $payment->course_count;
                $studentPayment->pay_fee = $payment->price;
                $studentPayment->equipment_issend = 0;
                $studentPayment->expire_date = Carbon::now()->addMonth($payment->use_to_date)->toDateString();
                $studentPayment->payment_date = Carbon::now()->toDateString();
                $studentPayment->saveOrFail();

                $tickets = new ClubCourseTickets();
                $tickets->payment_id = $studentPayment->id;
                $tickets->club_id = $student->club_id;
                $tickets->student_id = $student->id;
                $tickets->expired_date = Carbon::now()->addMonth($payment->use_to_date)->toDateString();
                $tickets->status = 2;
                $tickets->class_type_id = $payment->type;
                $tickets->unit_price = bcdiv($payment->price,$payment->course_count,2);
                $tickets->reward_type = 3;  //积分兑换券
                $tickets->saveOrFail();
            }

            $student->left_course_count += $payment->course_count * $courseNum;
            $student->saveOrFail();
        });
    }

    /**
     * 添加一条积分兑换课时缴费方案
     * @param $clubId
     * @return ClubPayment
     * @throws \Throwable
     */
    public function addScoreCoursePayment($clubId)
    {
        // 添加活动缴费方案
        $payment = new ClubPayment();
        $payment->club_id = $clubId;
        $payment->name = "积分兑换课时";
        $payment->payment_tag = '活动缴费';
        $payment->type = 1;
        $payment->tag = 3;
        $payment->price = 0;
        $payment->original_price = 0;
        $payment->min_price = 0;
        $payment->course_count = 1;
        $payment->use_to_student_type = 1;
        $payment->private_leave_count = 0;
        $payment->use_to_date = 12;
        $payment->show_in_app = 0;
        $payment->limit_to_buy = 0;
        $payment->is_free = 1;
        $payment->status = 1;
        $payment->saveOrFail();

        return $payment;
    }
}