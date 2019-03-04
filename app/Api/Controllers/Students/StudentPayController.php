<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/10
 * Time: 15:42
 */

namespace App\Api\Controllers\Students;


use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubSalesPerformanceByDay\ClubSalesPerformanceByDay;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubStudentRefund\ClubStudentRefund;
use App\Model\ClubStudentSalesHistory\ClubStudentSalesHistory;
use App\Model\ClubUser\ClubUser;
use App\Services\Common\CommonService;
use Carbon\Carbon;
use Dotenv\Validator;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Tymon\JWTAuth\JWTAuth;
use App\Facades\Util\Log;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\Recommend\ClubRecommendRewardRecord;
use Exception;


class StudentPayController extends Controller
{
    protected  $operationLog;

    public function __construct()
    {
        //操作日志
        $this->operationLog = new CommonService();
    }

    /**
     * 缴费记录列表
     * @param Request $request
     * @return array
     */
    public function payRecord(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'studentId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $clubId = $data['user']['club_id'];
        $studentId = $data['studentId'];

        // 学员不存在
        $student = ClubStudent::find($data['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $studentPay = ClubStudentPayment::where('student_id',$studentId)
            ->where('club_id',$clubId)
            ->with('sales','payment','operator')
            ->where('is_delete',0)
            ->orderBy('id', 'desc')
            ->paginate($data['pagePerNum'], ['*'], 'currentPage', $data['currentPage']);

        $result['totNum'] = $studentPay->total();
        $result['result'] = $studentPay->transform(function ($item){
            $arr = [
                'paymentId' => $item->id,
                'program' => isset($item->payment->name) ? $item->payment->name : '',
                'seller' => count($item->sales) ? $item->sales->sales_name : '',
                'classCount' => $item->course_count,
                'price' => $item->pay_fee,
                'contractNum' =>$item->contract_no,
                'payType' => $item->pay_type,
                'orderNum' => $item->order_id,
                'isGrant' => $item->equipment_issend,
                'payTime' => $item->payment_date,
                'expiredTime'  => $item->expire_date,
                'operator' => count($item->operator) ? $item->operator->username : '',
                'isFree' => ClubPayment::where('id', $item->payment_id)->value('is_free'),
                'remark' => $item->remark
            ];
            return $arr;
        });

        return returnMessage('200','请求成功',$result);
    }

    /**
     * 添加缴费
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function addPay(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'studentId' => 'required|numeric',
            'paymentPlanId' => 'required|numeric',
            'sellerId' => 'required|numeric',
            'contractNum' => 'nullable|numeric',
            'payTime' => 'required|date',
            'payType' => 'nullable|numeric',
            'remark' => 'nullable|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法错误');
        }

        $operatorId = $data['user']['id'];//操作员id
        $clubId = $data['user']['club_id']; //该学员所在俱乐部
        $studentId = $data['studentId'];//学员id
        $paymentPlanId = $data['paymentPlanId']; //缴费方案id
        $sellerId = $data['sellerId']; //销售员id
        $contractNum = isset($data['contractNum']) ? $data['contractNum'] : ''; //合同编号
        $payTime = $data['payTime']; //缴费日期
        $payType = isset($data['payType']) ? $data['payType'] : 0; //付款方式
        $remark = isset($data['remark']) ? $data['remark'] : ''; //备注

        // 缴费学员
        $student = ClubStudent::find($studentId);

        // 缴费方案
        $payment = ClubPayment::find($paymentPlanId);

        // 正式学员不能添加非正式缴费
        if ($student->status == 1 && $payment->use_to_student_type == 3) {
            return returnMessage('1646', config('error.Student.1646'));
        }

        // 非正式学员不能添加正式缴费
        if ($student->status == 2 && $payment->use_to_student_type == 2) {
            return returnMessage('1647', config('error.Student.1647'));
        }

        // 学员缴费方案
        $stuPayment = ClubStudentPayment::where('student_id',$studentId)->where('club_id',$clubId)->get();

        // 是否续费
        $classType = 0;
        foreach ($stuPayment as $studentVal){
            if($studentVal->payment_class_type_id == 1 || $studentVal->payment_class_type_id == 2){
                $classType += 1;
            }
        }
        if(empty($classType)){
            $isPayAgain = 0;
        }else{
            $isPayAgain = 1;
        }
        try{
            $studentPayment = new ClubStudentPayment();
            DB::transaction(function() use($studentPayment,$isPayAgain,$operatorId,$clubId,$studentId,$paymentPlanId,$sellerId,$contractNum,$payTime,$payType,$remark, $payment) {
                // $payment = ClubPayment::where('id',$paymentPlanId)->first(); //查找过期日期
                $student = ClubStudent::find($studentId);
                $sales = ClubSales::find($sellerId);
                $day = $payment->use_to_date*31; //有效天数
                $lostTime = date("Y-m-d",strtotime("+$day  day",strtotime($payTime)));//过期时间
                $studentPayment->club_id = $clubId; //俱乐部
                $studentPayment->payment_name = $payment->name;//缴费方案名称
                $studentPayment->payment_tag_id = $payment->tag;//缴费的付费类别
                $studentPayment->payment_class_type_id = $payment->type;//缴费的班级类型
                $studentPayment->course_count = $payment->course_count;//课时数
                $studentPayment->private_leave_count = $payment->private_leave_count; // 事假额
                $studentPayment->sales_dept_id = $sales->sales_dept_id;//销售部门id
                $studentPayment->price = $payment->original_price; // 原价
                $studentPayment->pay_fee= $payment->price;//价格
                $studentPayment->student_id = $studentId;
                $studentPayment->payment_id = $paymentPlanId;
                $studentPayment->sales_id = $sellerId;
                $studentPayment->pay_type = $payType;
                $studentPayment->contract_no = $contractNum;
                $studentPayment->channel_type = $student->channel_id;
                $studentPayment->payment_date = $payTime;
                $studentPayment->expire_date = $lostTime;
                $studentPayment->is_free = $payment->is_free;
                $studentPayment->remark = $remark;
                $studentPayment->equipment_issend = 0; //装备是否发放
                $studentPayment->is_pay_again = $isPayAgain;//是否续费
                $studentPayment->operation_user_id = $operatorId;//操作员id

                //查看是否有正式缴费的记录
                $checkExistsOffical = ClubStudentPayment::where('student_id',$student->id)
                    ->where('payment_tag_id',2)
                    ->exists();

                $is_pay_again = 0;
                if ($checkExistsOffical == true) {
                    $is_pay_again = 1;
                }

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

                    $studentPayment->reward_status = 1; //学员买单时，给此学员的推荐学员
                }


                $studentPayment->saveOrFail();

                $course = ClubCourseTickets::where('club_id',$clubId)->where('student_id',$studentId)->where('status',2)->count();
                $leftCourse = $course + $payment->course_count;
                //更新club_student
                if ($payment->is_free == 0) {
                    $student->pay_count = $student->pay_count + 1;
                    $student->pay_amount = $student->pay_amount + $payment->price;
                    $student->saveOrFail();
                }
                if($payment->tag == 2){
                    ClubStudent::where('id',$studentId)->update(['status'=>1]);
                }
                ClubStudent::where('id',$studentId)->update(['left_course_count' => $leftCourse,'is_pay_again'=> $isPayAgain]);
                for($i = 0; $i < $payment->course_count; $i++){
                    $ticket = new ClubCourseTickets();
                    $ticket->club_id = $clubId;
                    $ticket->student_id = $studentId;
                    $ticket->payment_id = $studentPayment->id;
                    $ticket->tag_id = $payment->tag;
                    $ticket->expired_date = $lostTime;
                    $ticket->status = 2;
                    $ticket->class_type_id = $payment->type;
                    $ticket->unit_price = number_format($payment->price/$payment->course_count,2,'.','');
                    $ticket->reward_type =
                    $ticket->saveOrFail();
                }
            });
        }catch (\Exception $e){
            return returnMessage('400','添加失败',$e);
        }

        return returnMessage('200','添加成功');
    }

    //销售统计---未完成  添加缴费时候调用
    public function SaleStatistics($data)
    {
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : ''; //该学员所在俱乐部
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';//学员id
        $paymentPlanId = isset($data['paymentPlanId']) ? $data['paymentPlanId'] : '';//缴费方案id
        $sellerId = isset($data['sellerId']) ? $data['sellerId'] : '';//销售员id
        $contractNum = isset($data['contractNum']) ? $data['contractNum'] : '';//合同编号
        $payTime = isset($data['payTime']) ? $data['payTime'] : '';//缴费日期
        $remark = isset($data['remark']) ? $data['remark'] : '';//付款方式
        $price = 0;//销售额

        $performance = ClubSalesPerformanceByDay::where('sales_id',$sellerId)->where('day',$payTime)->first();//查找是否有这条记录

        if(empty($performance)){
            $sales = ClubSales::find($sellerId);
            $user = ClubSales::find($sellerId)->user;
            $payment = ClubStudentPayment::where('payment_date',$payTime)
                ->where('channel_type',1)
                ->where('club_id',$clubId)
                ->where('student_id',$studentId)
                ->where('payment_id',$paymentPlanId)
                ->whereIn('payment_class_type_id',[1,2])
                ->get();
            $people = count($payment);
            foreach ($payment as $val){
                $price += $val->pay_fee;
            }
            //自营
            if(count($payment) <= 1){

            }

            $result['result'] = [
                'sales_id' => $sales->id,
                'sales_dept_id' => $sales->sales_dept_id,
                'sales_club_id' => $user->club_id,
                'performance1' => $price,

                'day'=> $payTime,
            ];
            return $payment;
        }

    }

    /**
     * 修改缴费
     * @param Request $request
     * @return array
     */
    public function editPay(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'paymentId' => 'required|numeric',
            'studentId' => 'required|numeric',
            'contractNum' => 'nullable|String',
            'payTime' => 'nullable|date|required',
            'remark' => 'nullable'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $studentId = isset($data['studentId']) ? $data['studentId'] : '' ;//学员编号
        $paymentId = isset($data['paymentId']) ? $data['paymentId'] : '' ;//缴费id
        $contractNum = isset($data['contractNum']) ? $data['contractNum'] : '' ;//合同编号
        $payTime = isset($data['payTime']) ? $data['payTime'] : '' ;//缴费日期
        $remark = isset($data['remark']) ? $data['remark'] : '' ;//备注

        $studentPayment = ClubStudentPayment::find($paymentId);

        if($studentPayment->student_id != $studentId){
            return returnMessage('404','未找到学员编号');
        }
        try{
            $studentPayment->contract_no = $contractNum;
            $studentPayment->payment_date = $payTime;
            $studentPayment->remark = $remark;
            $studentPayment->save();
        }catch(\Exception $e){
            return returnMessage('400','修改失败');
        }
        //操作日志
//        $this->operationLog->addOperationLog($data['user']['id'],2,2,'' ,'');

        return returnMessage('200','修改成功');

    }

    /**
     * 删除缴费记录
     * @param Request $request
     * @return array
     */
    public function delPay(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'paymentId' => 'required|numeric',
            'studentId' => 'required|numeric'

        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : ''; //该学员所在俱乐部
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';
        $id = isset($data['paymentId']) ? $data['paymentId'] : '';

        $studentPayment = ClubStudentPayment::find($id);

        if($studentId != $studentPayment['student_id']){
            return returnMessage('404','未找到学员编号');
        }
        if($studentPayment->pay_type == 3){
            return returnMessage('102','线上缴费不允许删除');
        }
        $ticket = ClubCourseTickets::where('club_id',$clubId)->where('student_id',$studentId)->where('payment_id',$studentPayment->payment_id)->whereIn('status' ,[1,3,4])->get();//学员对应方案课程卷

        try{
            if($ticket->isEmpty()){
                ClubCourseTickets::where('club_id',$clubId)->where('student_id',$studentId)->where('payment_id',$studentPayment->payment_id)->update(['is_delete' => 1]);//删除学员对应课程券
                $studentPayment->update(['is_delete' => 1]);
            }else{
                return returnMessage('400','当前缴费课程使用，无法删除');
            }
        }catch (\Exception $e){
            return returnMessage('400','删除失败');
        }
        return returnMessage('200','删除成功');
    }

    /**
     * 缴费详情
     * @param Request $request
     * @return array
     */
    public function payDetails(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'paymentId' => 'required|numeric',
            'studentId' => 'required|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法请求');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : ''; //该学员所在俱乐部
        $paymentId = isset($data['paymentId']) ? $data['paymentId'] : '';//缴费编号id
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';//学员id

        //统计已使用、未使用、已失效、已退款
        $useTicket = ClubCourseTickets::where([
            ['club_id',$clubId],
            ['payment_id',$paymentId],
            ['student_id',$studentId],
            ['status',1]
        ])->count(); //使用
        $unusedTicket = ClubCourseTickets::where([
            ['club_id',$clubId],
            ['payment_id',$paymentId],
            ['student_id',$studentId],
            ['status',2]
        ])->count();//未使用
        $loseTicket = ClubCourseTickets::where([
            ['club_id',$clubId],
            ['payment_id',$paymentId],
            ['student_id',$studentId],
            ['status',3]
        ])->count();//已失效
        $refundTicket = ClubCourseTickets::where([
            ['club_id',$clubId],
            ['payment_id',$paymentId],
            ['student_id',$studentId],
            ['status',4]
        ])->count();//已失效

        $studentPayment = ClubStudentPayment::where('club_id',$clubId)->where('id',$paymentId)->with(['Payment','Student'=>function($query){
            $query->with('refund');
        },'sales'])->first();
        if(empty($studentPayment)){
            return returnMessage('404','未找到数据');
        }
        if($studentPayment->student_id != $studentId){
            return returnMessage('404','未找到学员编号');
        }
        $result = [];
        $result['baseMsg'] = [
            'paymentId' => $studentPayment->id,
            'paymentPlanName' => $studentPayment->payment_name,
            'studentInfo' => $studentPayment->student->name.'['.$studentPayment->student->id.']',
            'sellerInfo' => $studentPayment->sales->sales_name,
            'classCount' => $studentPayment->course_count,
            'workLeave' => $studentPayment->private_leave_count,
            'price' => $studentPayment->price,
            'realPayment' => $studentPayment->pay_fee,
            'reimburseMsg' => $this->getRefundPrice($studentPayment->student_id,$paymentId,$clubId),
            'contract' => $studentPayment->contract_no ,
            'orderMsg' => $studentPayment->order_id,//暂无
            'remark' => $studentPayment->remark,
            'payTime' => $studentPayment->payment_date,
            'expiredTime' => $studentPayment->expire_date
        ];
        $result['courseTicketMsg'] = [
            'used' => $useTicket,
            'unused' => $unusedTicket,
            'expired' => $loseTicket,
            'refunded' => $refundTicket
        ];
        return returnMessage('200','请求成功',$result);
    }
    /**
     * 计算学员退款信息
     */
    public function getRefundPrice($studentId,$paymentId,$clubId){
        $refund = ClubStudentRefund::where('student_id',$studentId)->where('student_payment_id',$paymentId)->where('club_id',$clubId)->get();
        $price = 0;
        foreach ($refund as $val){
            $price += $val->refund_money;
        }
        return $price;

    }


    /**
     * 修改详情 销售员/实付款/过期时间
     * @param Request $request
     * @return array
     */
    public function editDetails(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'paymentId' => 'required|numeric',
            'studentId' => 'required|numeric',
            'salesId' => 'nullable|numeric',
            'realityPay' => 'nullable|numeric',
            'pastDate' => 'nullable|date'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法请求');
        }

        /*--------club_id-------*/
        $paymentId = isset($data['paymentId']) ? $data['paymentId'] : '';//缴费id
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';//学员编号
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';//销售id
        $realityPay = isset($data['realityPay']) ? $data['realityPay'] : '';//实付款
        $pastDate = isset($data['pastDate']) ? $data['pastDate'] : '';//过期时间

        $studentPayment = ClubStudentPayment::find($paymentId);

        if($studentId != $studentPayment->student_id){
            return returnMessage('404','未找到学员编号');
        }
        if(!empty($salesId)){
            try{
                $this->endAddStudentSalesHistory($studentId);
                $this->startAddStudentSalesHistory($salesId,$studentId);
                $studentPayment->sales_id = $salesId;
            }catch (\Exception $e){
                return returnMessage('400','修改失败',$e);
            }

        }
        if(!empty($realityPay)){
            if($realityPay == 0){
                return returnMessage('400','实际缴费不能为0');
            }
            $studentPayment->pay_fee = $realityPay;
        }
        if(!empty($pastDate)){
            $studentPayment->expire_date = $pastDate;
        }
        $studentPayment->save();
        return returnMessage('200','修改成功');
    }
    // 结束学员销售
    public function endAddStudentSalesHistory($studentId)
    {
        ClubStudentSalesHistory::where('student_id', $studentId)
            ->where('end_date', null)
            ->update(['end_date' => date('Y-m-d', time())]);
    }

    // 添加学员销售
    public function startAddStudentSalesHistory($salesId, $studentId)
    {
        $salesHistory = new ClubStudentSalesHistory();
        $salesHistory->student_id = $studentId;
        $salesHistory->sales_id = $salesId;
        $salesHistory->start_date = date('Y-m-d', time());
        $salesHistory->save();
    }
    /**
     *  checkbox 课程券
     * @param Request $request
     * @return array
     */
    public function ticket(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'studentId' => 'required|numeric',
            'paymentId' => 'required|numeric',
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : ''; //该学员所在俱乐部
        $id = isset($data['paymentId']) ? $data['paymentId'] : '';//缴费记录编号
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';//学员id

        $studentPayment= ClubStudentPayment::find($id);
        if(empty($studentPayment)){
            return returnMessage('404','未找到对应的记录');
        }
        $ticket = ClubCourseTickets::where('student_id',$studentId)
            ->where('club_id',$clubId)
            ->where('payment_id',$id)
            ->whereHas('studentPayment',function ($query){
                $query->where('is_free',0);
            })
            ->where('status',2)
            ->get(['id']);
        $result['result'] = $ticket->transform(function($item){
            $arr = [
                'courseId' => $item->id
            ];
            return $arr;
        });

        return returnMessage('200','请求成功',$result);

    }

    /**
     * 退款
     * @param Request $request
     * @return array
     */
    public function refund(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'studentId' => 'required|numeric',
            'paymentId' => 'required|numeric',
            'reimbursePrice' => 'required|numeric',
            'remark' => 'nullable',
            'courseTicketId'=> 'required|String',
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $operatorId  = isset($data['user']['id']) ? $data['user']['id'] : '';
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
        $id = isset($data['paymentId']) ? $data['paymentId'] : '';//缴费编号
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';//学员id
        $reimbursePrice = isset($data['reimbursePrice']) ? $data['reimbursePrice'] : '';//退款金额
        $remark = isset($data['remark']) ?$data['remark'] : '';//备注
        $courseTicketId = isset($data['courseTicketId']) ?  $data['courseTicketId'] : '';//课程卷id
        $arr = explode(',',$courseTicketId);

        $studentPayment = ClubStudentPayment::find($id);
        if(empty($studentPayment->sales_id)){
            return returnMessage('404','未找到对应的信息');
        }
        try{
            DB::transaction(function () use ($clubId,$studentId,$id,$courseTicketId,$reimbursePrice,$remark,$studentPayment,$operatorId,$arr){
                $refund = new  ClubStudentRefund();
                $refund->club_id = $clubId;
                $refund->student_id = $studentId;
                $refund->student_payment_id = $id;
                $refund->refund_course_ids = $courseTicketId;
                $refund->refund_money = $reimbursePrice;
                $refund->remark = $remark;
                $refund->sales_id = $studentPayment->sales_id;
                $refund->refund_operation_sales_id = $operatorId;
                $refund->refund_date = date('Y-m-d',time());
                $refund->save();
                $count = 0;
                foreach ($arr as $val){
                    $count += 1;
                    ClubCourseTickets::where('id',$val)->update(['status' => 4]);
                }

                $studentCourse = ClubStudent::where('id',$studentId)->value('left_course_count');
                $studentNow = $studentCourse - $count;
                ClubStudent::where('club_id',$clubId)->where('id',$studentId)->update(['left_course_count' => $studentNow]);
            });

        }catch(\Exception $e){
            return returnMessage('400','退款失败',$e);
        }
        return returnMessage('200','请求成功');
    }

    /**
     * 课程卷
     * @param Request $request
     * @return array
     */
    public function studentTickets(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'paymentId' => 'required|numeric',
            'studentId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = $data['user']['club_id'];
        $paymentId = $data['paymentId'];
        $studentId = $data['studentId'];

        $payment = ClubStudentPayment::find($paymentId);
        if(empty($payment)){
            return returnMessage('404','未找到该缴费记录');
        }

        $studentTicket = ClubCourseTickets::where('club_id',$clubId)
            ->where('payment_id',$paymentId)
            ->where('student_id',$studentId)
            ->paginate($data['pagePerNum'], ['*'], 'currentPage', $data['currentPage']);

        $result['totNum'] = $studentTicket->total();
        $result['result'] = $studentTicket->transform(function($item) use($clubId,$studentId){
            $arr = [
                'courseTicketId' => $item->id,
                'price' => $item->unit_price,
                'expiredTime'=> $item->expired_date,
                'status' => $this->getTicketType($item->status),
                'courseId' => $item->course_id,
                'remark' => $this->getRefundRemark($clubId,$studentId,$item->id) ? $this->getRefundRemark($clubId,$studentId,$item->id) : ''
            ];
            return $arr;
        });
        return returnMessage('200','请求成功',$result);
    }

    /**
     * 获取课程卷状态
     * @param $status
     * @return null|string
     */
    public function getTicketType($status){
        switch ($status){
            case  "1" : return '已使用'; break;
            case  "2" : return '未使用'; break;
            case  "3" : return '已失效'; break;
            case  "4" : return '已退款'; break;
            default: return null;
        }
    }

    /**
     * 获取退款备注
     * @param $clubId
     * @param $studentId
     * @param $courseId
     * @return mixed
     */
    protected  function getRefundRemark($clubId,$studentId,$courseId){
        $remark = ClubStudentRefund::where('club_id',$clubId)
            ->where('student_id',$studentId)
            ->where('refund_course_ids','like','%'.$courseId.'%')
            ->value('remark');
        return $remark;
    }
    /**
     * 装备发放
     */
    public function editGrant(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'id' => 'required|numeric',
            'isGrant' => 'required|numeric'
        ]);

        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $id = $data['id'];
        $isGrant = empty($data['isGrant']) ? 1 : 0;
        try{
            $studentPayment = ClubStudentPayment::find($id);
            $studentPayment->equipment_issend = (int)$isGrant;
            $studentPayment->save();
        }catch (\Exception $e){
            return returnMessage('400','修改失败',$e);
        }
        $result['isGrant'] = $isGrant;
        return returnMessage('200','修改成功',$result);
    }

    /**
     * 缴费方案select
     * @param Request $request
     * @return array
     */
    public function getPaymentSelect(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'studentId' => 'required|numeric'
        ]);
        if($validate->fails()){
            return  returnMessage('101','非法操作');
        }
        $studentId = $data['studentId'];
        $clubId = $data['user']['club_id'];
        $paymentId = ClubStudentPayment::where('student_id',$studentId)
            ->where('club_id',$clubId)
            ->whereHas('Payment',function ($query){
                $query->where('limit_to_buy',1);
            })
            ->where('is_delete', 0)
            ->pluck('payment_id');
        $payment = ClubPayment::with('paymentTag')
            ->whereNotIn('id',$paymentId)
            ->where('club_id',$clubId)
            ->where('is_delete', 0)
            ->where('status',1)
            ->get();
        $arr = [];
        collect($payment)->each(function ($item) use (&$arr){
            array_push($arr,[
                'id' => $item->id,
                'paymentName' => $item->name,
                'paymentTag' => isset($item->paymentTag->name) ? $item->paymentTag->name : ''
            ]);
        });
        return returnMessage('200','请求成功',$arr);
    }

    /**
     * 发送合同
     * @param Request $request
     * @return array|mixed|string
     */
    public function sendContract(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'id' => 'required|numeric',
            'studentId' => 'required|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = $data['user']['club_id'];
        $clubStudent = ClubStudent::where('id',$data['studentId'])
            ->where('is_delete',0)
            ->first()
            ->toArray();

        if (empty($clubStudent)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $studentPayment = ClubStudentPayment::find($data['id']);

        // 非正式缴费不可发送合同
        if ($studentPayment->payment_tag_id != 2) {
            return returnMessage('2104', config('error.Payment.2104'));
        }

        $payment = ClubPayment::find($studentPayment->payment_id);

        // 过期缴费不可发送合同
        if (!$payment->use_to_date) {
            return returnMessage('2108', config('error.Payment.2108'));
        }

        $bindUserExists = ClubStudentBindApp::notDelete()
            ->where('student_id',$data['studentId'])
            ->where('app_account',$clubStudent['guarder_mobile'])
            ->exists();

        if ($bindUserExists === false) {
            return returnMessage('1685', config('error.Student.1685'));
        }

        $formData = [
            'json' =>  [
                'stuId' => $data['studentId'],
                'classId' => $clubStudent['main_class_id'],
                'clubId' => $clubId,
                'clubName' => Club::where('id', $clubId)->value('name'),
                'classType' => ClubClass::where('id',$clubStudent['main_class_id'])->value('type'),
                'userMobile' => $clubStudent['guarder_mobile'],
                'payRecordId' => $data['id'],
                'startDate' => Carbon::now()->format('Y-m-d'),
                'endDate' => Carbon::now()->addMonths($payment->use_to_date)->format('Y-m-d')
            ]
        ];

        $client = new Client();
        $url = env('INNER_DOMAIN');
        try {
            $response = $client->post($url, $formData);
            $info = \GuzzleHttp\json_decode($response->getBody()->getContents(), true);
        }catch (\Exception $e){
            return $e->getMessage();
        }
        return $info;
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
}