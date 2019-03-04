<?php

namespace App\Services\Payment;

use App\Model\ClubClass\ClubClass;

use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubPaymentTag\ClubPaymentTag;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use Exception;
use App\Facades\Util\Common;

class PaymentService
{
    /**
     * 通过班级获取缴费方案
     * @param $classId
     * @return mixed
     * @throws Exception
     */
    public function getClassPayment($classId)
    {
        // 班级信息
        $class = ClubClass::find($classId);
        if (empty($class)) {
            throw new Exception(config('error.class.1406'), 1406);
        }

        // 缴费方案
        $payment = ClubPayment::valid()->showInApp()
            ->where('payment_tag', $class->pay_tag_name)
            ->where('club_id', $class->club_id)
            ->where('is_default',0)
            //->where('is_free', 0)
            ->get();

        $list = [];
        collect($payment)->each(function ($item) use (&$list) {
            $list[] = [
                'planId' => $item->id,
                'planName' => $item->name,
                'originPrice' => $item->original_price,
                'price' => $item->price,
                'courseCount' => $item->course_count,
                'privateLeaveCount' => $item->private_leave_count,
                'unitPrice' => Common::numberFormatFloat($item->price/$item->course_count,2)
            ];
        });

        return $list;
    }

    /**
     * 通过缴费记录获取缴费方案
     * @param $studentPaymentId
     * @return mixed
     * @throws Exception
     */
    public function getPaymentByStudent($studentPaymentId)
    {
        // 缴费记录
        $studentPayment = ClubStudentPayment::notDelete()->where('id', $studentPaymentId)->first();
        if (empty($studentPayment)) {
            throw new Exception(config('error.Student.1644'), 1644);
        }

        // 缴费方案
        $payment = ClubPayment::valid()->where('id', $studentPayment->payment_id)->first();
        if (empty($payment)) {
            throw new Exception(config('error.Payment.2101'), 2101);
        }

        $arr['planId'] = $payment->id;
        $arr['planName'] = $payment->name;
        $arr['originPrice'] = $payment->original_price;
        $arr['price'] = $payment->price;
        $arr['courseCount'] = $payment->course_count;
        $arr['privateLeaveCount'] = $payment->private_leave_count;

        return $arr;
    }

    /**
     * 多个缴费记录id获取缴费方案
     * @param $paymentRecordIds
     * @return array
     */
    public function getPaymentByPaymentIds($paymentRecordIds)
    {
        $arr = [];

        $stuPayments = ClubStudentPayment::whereIn('id',$paymentRecordIds)
            ->pluck('payment_id','id');

        $payments = ClubPayment::valid()->showInApp()
            ->whereIn('id',$stuPayments->values())
            ->get();

        collect($stuPayments)->each(function ($paymentId,$recordId) use (&$arr,$payments) {
            collect($payments)->each(function ($payment) use (&$arr,$paymentId,$recordId) {
                if ($payment->id == $paymentId) {
                    $arr[$recordId] = [
                        'planId' => $payment->id,
                        'planName' => $payment->name,
                        'originPrice' => $payment->original_price,
                        'price' => $payment->price,
                        'courseCount' => $payment->course_count,
                        'privateLeaveCount' => $payment->private_leave_count,
                        'unitPrice' => Common::numberFormatFloat($payment->price/$payment->course_count,2)
                    ];
                }
            });
        });

        return $arr;
    }

    public function getOnePayment($paymentId)
    {
        $payment = ClubPayment::valid()->showInApp()->find($paymentId);

        return [
            'planId' => $payment->id,
            'planName' => $payment->name,
            'originPrice' => $payment->original_price,
            'price' => $payment->price,
            'courseCount' => $payment->course_count,
            'privateLeaveCount' => $payment->private_leave_count,
            'unitPrice' => Common::numberFormatFloat($payment->price/$payment->course_count,2)
        ];
    }

    public function contractComplete($payRecordId,$contractSn){
        $studentPayment = ClubStudentPayment::find($payRecordId);
        if (empty($studentPayment)) {
            throw new Exception(config('error.studentPayment.2201'), 2201);
        }
        $studentPayment->contract_no = $contractSn;
        $res = $studentPayment->save();
        if(empty($res)){
            throw new Exception(config('error.studentPayment.2202'), 2202);
        }
    }

}