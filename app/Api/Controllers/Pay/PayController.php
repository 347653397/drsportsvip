<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/7/4
 * Time: 16:27
 */

namespace App\Api\Controllers\Pay;


use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubChannel\ClubChannel;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubIncomeSnapshot\ClubIncomeSnapshot;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubPaymentTag\ClubPaymentTag;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubStudentRefund\ClubStudentRefund;
use Faker\Provider\Payment;
use Illuminate\Pagination\Paginator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;


class PayController extends  Controller
{
    /**
     * 获取缴费方案与筛选
     * @param Request $request
     * @return array
     */
    public function getPayment(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data,[
            'Payment' => 'nullable|max:50',
            'type' => 'nullable|numeric',
            'studentType' => 'nullable|Numeric',
            'status' => 'nullable|Numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);

        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = $data['user']['club_id'];
        $paymentName = isset($data['Payment']) ? $data['Payment'] : '' ;//缴费方案名称
        $type = isset($data['type']) ? $data['type'] : ''; //班级类型
        $status = $data['status'];//是否有效
        $studentType = isset($data['studentType']) ? $data['studentType'] : '';//适用学员

        $payment = ClubPayment::with('classType:id,name')
            ->where('club_id',$clubId)
            ->where('is_delete',0)
            ->where(function($query) use($paymentName){
                if(!empty($paymentName)){
                    $query->where('name','like','%'.$paymentName.'%');
                }
            })
            ->where(function($query) use($type){
                if(!empty($type)){
                    $query->where('type',$type);
                }
            })->where(function($query) use($studentType){
                if(!empty($studentType)){
                    $query->where('use_to_student_type',$studentType);
                }
            })
            ->where(function($query) use ($status){
                //0全部 1失效 2有效
                if($status == 0){
                    $query->where('status',1)->Orwhere('status',0);
                }elseif($status == 1){
                    $query->where('status',0);
                }elseif($status == 2){
                    $query->where('status',1);
                }else{
                    $query->where('status',1);
                }
            })
            ->orderBy('show_in_app', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($data['pagePerNum'], ['*'], 'currentPage', $data['currentPage']);

        $result['totNum'] = $payment->total();
        $result['result'] = $payment->transform(function ($item){
            $result = [
                'id'=> $item->id,
                'type' => $item->type,
                'Payment' => $item->name,
                'tag' => $item->tag,
                'price' => $item->price,
                'courseCount' => $item->course_count,
                'originalCost' => $item->original_price,
                'floorPrice' => $item->min_price,
                'applyStudent' => $item->use_to_student_type,
                'paymentTag' => $item->payment_tag,
                'status' => $item->status,
                'validDate' => $item->use_to_date,
                'pushMoney' => $item->percentage_bonus,
                'weight' => $item->weight,
                'isShowApp' => $item->show_in_app,
                'isPurchaseLimit' => $item->limit_to_buy,
                'leaveCount' => $item->private_leave_count
            ];
            return $result;
        });

        return returnMessage('200','请求成功',$result);
    }

    /**
     * 返回学生类型
     * @param $type int 学生类型1,2,3
     * @return string
     */
    public function getStudentType($type){
        if($type == 1){
            return '所有';
        }
        if($type == 2){
            return '正式学员';
        }
        if($type == 3){
            return '非正式学员';
        }
    }

    /**
     * 添加缴费方案
     * @param Request $request
     * @return array
     */
    public function addPayment(Request $request){
        $data = $request->all();
        $validate =  \Validator::make($data, [
            'name' => 'required|string|max:50',
            'paymentTag' => 'required|string|max:10',
            'tag' => 'required|Numeric',
            'type' => 'required|Numeric',
            'price' => 'required|Numeric',
            'originalCost' => 'required|Numeric',
            'floorPrice' => 'required|Numeric',
            'courseCount' => 'required|Numeric',
            'leaveCount' => 'required|Numeric',
            'validDate' => 'nullable|Numeric',
            'applyStudent' => 'required|Numeric',
            'pushMoney' => 'nullable|Numeric',
            'weight' => 'nullable|Numeric',

        ]);
        if($validate->fails()){
            return returnMessage('101', '非法操作');
        }
        if($data['originalCost'] < $data['price']){
            return returnMessage('102', '原价需大于等于价格');
        }
        if($data['floorPrice'] > $data['price']){
            return returnMessage('103', '底价不得大于价格');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
        $name = isset($data['name'])? $data['name'] : '';//缴费方案
        $paymentTag = isset($data['paymentTag'])? $data['paymentTag'] : '';//缴费标签
        $tag = isset($data['tag'])? $data['tag'] : '';//标签
        $type = isset($data['type'])? $data['type'] : '';//产品类型
        $price = isset($data['price'])? $data['price'] : '';//价格
        $originalCost = isset($data['originalCost'])? $data['originalCost'] : '';//原价
        $floorPrice = isset($data['floorPrice'])? $data['floorPrice'] : '';//底价
        $courseCount = isset($data['courseCount'])? $data['courseCount'] : '';//课时数
        $leaveCount = isset($data['leaveCount'])? $data['leaveCount'] : 0;//事假数
        $validDate = isset($data['validDate'])? $data['validDate'] : '';//有效期
        $applyStudent = isset($data['applyStudent'])? $data['applyStudent'] : ''; //适用学员
        $pushMoney = isset($data['pushMoney'])? $data['pushMoney'] : null;//提成
        $weight = isset($data['weight'])? $data['weight'] : null; //权重
        $isShowApp = 0;//是否在App展示
        if(isset($data['isShowApp'])){
            if($data['isShowApp'] == 'true'){
                $isShowApp = 1;
            }
        }
        $isPurchaseLimit = 0;//是否限购
        if(isset($data['isPurchaseLimit'])){
            if($data['isPurchaseLimit'] == 'true'){
                $isPurchaseLimit = 1;
            }
        }
        $isFree = 0;//是否免费
        if($tag == 1){
            $isFree = 1;
        }
        try{
            $payment = new ClubPayment();
            $payment->club_id = $clubId;
            $payment->name = $name;
            $payment->payment_tag = $paymentTag;
            $payment->tag = $tag;
            $payment->type = $type;
            $payment->price = $price;
            $payment->original_price = $originalCost;
            $payment->min_price = $floorPrice;
            $payment->course_count = $courseCount;
            $payment->private_leave_count = $leaveCount;
            $payment->use_to_date = $validDate;
            $payment->use_to_student_type = $applyStudent;
            $payment->percentage_bonus = $pushMoney;
            $payment->weight = $weight;
            $payment->show_in_app = (int)$isShowApp;
            $payment->limit_to_buy = (int)$isPurchaseLimit;
            $payment->status = 1;
            $payment->is_free = $isFree;
            $payment->save();
        }catch (\Exception $e){
            return returnMessage('400', '添加数据失败');
        }
        return returnMessage('200','添加成功');
    }

    /**
     * 修改缴费方案
     * @param Request $request
     * @return array
     */
    public function editPayment(Request $request){
        $data = $request->all();
        $validate =  \Validator::make($data, [
            'id' => 'required|Numeric',
            'name' => 'required|string|max:50',
            'paymentTag' => 'required|string|max:10',
            'tag' => 'required|Numeric',
            'type' => 'required|Numeric',
            'price' => 'required|Numeric',
            'originalCost' => 'required|Numeric',
            'floorPrice' => 'required|Numeric',
            'courseCount' => 'required|Numeric',
            'leaveCount' => 'required|Numeric',
            'validDate' => 'required|Numeric',
            'applyStudent' => 'required|Numeric',
            'pushMoney' => 'nullable|Numeric',
            'weight' => 'nullable|Numeric',
        ]);
        if($validate->fails()){
            return returnMessage('101', '非法操作');
        }
        if($data['originalCost'] < $data['price']){
            return returnMessage('101', '非法操作');
        }
        if($data['floorPrice'] > $data['price']){
            return returnMessage('101', '非法操作');
        }
        $id = isset($data['id']) ? $data['id'] : '';//缴费方案id
        $name = isset($data['name']) ? $data['name'] : '';//缴费方案
        $paymentTag = isset($data['paymentTag']) ? $data['paymentTag'] : '';//缴费标签
        $tag = isset($data['tag']) ? $data['tag'] : '';//标签
        $type = isset($data['type']) ? $data['type'] : '';//产品类型
        $price = isset($data['price']) ? $data['price'] : '';//价格
        $originalCost = isset($data['originalCost']) ? $data['originalCost'] : '';//原价
        $floorPrice = isset($data['floorPrice']) ? $data['floorPrice'] : '';//底价
        $courseCount = isset($data['courseCount']) ? $data['courseCount'] : '';//课时数
        $leaveCount = isset($data['leaveCount']) ? $data['leaveCount'] : 0;//事假数
        $validDate = isset($data['validDate']) ? $data['validDate'] : '';//有效期
        $applyStudent = isset($data['applyStudent']) ? $data['applyStudent'] : ''; //适用学员
        $pushMoney = isset($data['pushMoney']) ? $data['pushMoney'] : null;//提成
        $weight = isset($data['weight'])? $data['weight'] : null; //权重
        $isShowApp = 0;//是否在App展示
        if(isset($data['isShowApp'])){
            if($data['isShowApp'] == 'true'){
                $isShowApp = 1;
            }
        }

        try{
            $payment = ClubPayment::find($id);
        }catch (\Exception $e){
            return returnMessage('400','请求失败');
        }
        try{
            $payment->name = $name;
            $payment->payment_tag = $paymentTag;
            $payment->tag = $tag;
            $payment->type = $type;
            $payment->price = $price;
            $payment->original_price = $originalCost;
            $payment->min_price = $floorPrice;
            $payment->course_count = $courseCount;
            $payment->private_leave_count = $leaveCount;
            $payment->use_to_date = $validDate;
            $payment->use_to_student_type = $applyStudent;
            $payment->percentage_bonus = $pushMoney;
            $payment->weight = $weight;
            $payment->show_in_app = (int)$isShowApp;
            $payment->save();
        }catch (\Exception $e){
            return returnMessage('400','修改失败');
        }
        return returnMessage('200','修改成功');

    }

    /**
     * 设置有效失效
     * @param Request $request
     * @return array
     */
    public function editStatus(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'id' => 'required|Numeric',
            'status' => 'required|Numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $id = isset($data['id']) ? $data['id'] : '' ;
        $status = $data['status'];

        $payment = ClubPayment::find($id);
        try{
            $payment->status = $status;
            $payment->save();
        }catch(\Exception $e){
            return returnMessage('400','修改失败');
        }
        $result['status'] = $status;
            return returnMessage('200','修改成功',$result);
    }

    /**
     * 删除缴费方案
     */

    public function delPayment(Request $request){
        $data = $request->all();
        $validate = \Validator::make($data,[
            'id' => 'required|Numeric',
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $id = isset($data['id']) ? $data['id'] : '' ;
        $payment = ClubPayment::find($id);

        try{
            if(ClubClass::where('pay_tag_name',$payment->payment_tag)->whereHas('student')->count()){
                return returnMessage('400','该缴费方案关联的班级已存在学员，不允许删除操作');
            }
            $payment->is_delete = 1;
            $payment->save();
        }catch(\Exception $e){
            return returnMessage('400','删除失败',$e);
        }
        return returnMessage('200','删除成功');
    }


    //缴费总览
    public function paymentSituation(Request $request){
        $data = $request->all();

        $validate = \Validator::make($data,[
            'salesId' => 'numeric|nullable',
            'startTime' => 'date|nullable',
            'endTime' => 'date|nullable',
            'summary' => 'numeric|nullable'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : ''; //俱乐部id
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';//销售id
        $startTime = isset($data['startTime']) ? strtotime($data['startTime']) : time(); //开始日期 date('Y-m-d',time())不传默认当前
        $endTime = isset($data['endTime']) ? strtotime($data['endTime']) : mktime(23,59,59,date('m'),date('t'),date('Y'));//结束日期 不传默认当前
        $summary = isset($data['summary']) ? $data['summary'] : 2;//汇总方式  不传默认按月

        $date = getSummer($startTime,$endTime,$summary);
        /**
         * 去年同期对比因为性能问题，暂时去掉
         */
        /*
        $lastYear = [];
        foreach ($date as $v){
            $lastYear[] = [
                'start' => date('Y-m-d',strtotime('-1 year',strtotime($v['start']))),
                'end' =>  date('Y-m-d',strtotime('-1 year',strtotime($v['end'])))
            ];
        }
        */
        //$lastYearDate = $this->getLastYear($lastYear,$clubId,$salesId); //返回去年同期额x,y

        foreach ($date as $val){
            $income[]  = ClubStudentPayment::where(function ($query) use($clubId){
                if(!empty($clubId)){
                    $query->where('club_id',$clubId);
                }
            })->where(function ($query) use($salesId){
                if(!empty($salesId)){
                    $query->where('sales_id',$salesId);
                }
            })->where(function ($query) use($val){
                if(!empty($val)){
                    //$query->whereBetween('payment_date',[$val['start'],$val['end']]);
                    $query->where('payment_date', '>=', $val['start'])->where('payment_date', '<=', $val['end']);
                }
            })->with([
                'payment'=> function($query) {
                $query->where('tag',2);
            }])->get()->toArray();
        }


        $totSale = 0;
        $totSelfPrice = 0;
        $totSelfCount = 0;
        $totClosePrice = 0;
        $totCourseCount = 0;
        //$totOnLineCount = 0;
        $totSaleCount = 0;
        $totChannelCount = 0;
        $totAppCount = 0;
        $totContractCount = 0;
        $totRefundPrice = 0;
        $totExperienceCount = 0;
        $totRefundCount = 0;
        $arrAll = $arrSale = $arrChannel = $arrOnLine = $arr = $result = [];
        $allAppPay = 0;
        $allAppPayDesc = '';
        if(count($income)>0) {
          foreach ($income as $key => $val) {
              $sales = 0;//销售额
              $selfPrice = 0; //自营课
              $closePrice = 0; //封闭营
              $courseCount = 0;//总课程
              $contractCount = 0; //待签约
              $refundPrice = 0; //退款
              $selfCount = 0;//自营课数量
              // $onLineCount = 0;//线上
              $salesCount = 0; //销售
              $channelCount = 0; //渠道
              $appCount = 0; //app付费
              $experienceCount = 0; //体验
              $refundCount = 0;
              // $onLinePrice = 0;
              $salesPrice = 0;
              $channelPrice = 0;
              $totUnitPrice = $unitPrice = 0;
              $appPay = 0;
              $appPayDesc = '';
              foreach ($val as $k => $v) {
                  $sales += $v['pay_fee'];
                  $totSale += $v['pay_fee'];
                  //自营课
                  if ($v['payment']['type'] == 1 || $v['payment']['type'] == 2) {
                      $selfCount += 1;
                      $totSelfCount += 1;
                      $totSelfPrice += $v['pay_fee'];
                      $totCourseCount += $v['course_count'];
                      $totUnitPrice = $totSelfPrice / $totCourseCount;
                      $selfPrice += $v['pay_fee']; //自营课
                      $courseCount += $v['course_count']; // 总课程数
                      $unitPrice = $selfPrice / $courseCount;//课程单价
                  }
                  //封闭营
                  if ($v['payment']['type'] == 3) {
                      $totClosePrice += $v['pay_fee'];
                      $closePrice += $v['pay_fee'];

                  }
                  //todo 线上

//                if($this->channelParent($v['channel_type']) == 1){
//                    $onLineCount += 1;
//                    $totOnLineCount += 1;
//                    $onLinePrice += $v['pay_fee'];
//                };
                  //销售
                  //if ($this->channelParent($v['channel_type']) == 2 || $this->channelParent($v['channel_type'] == 4)) {
                  if ($v['channel_type'] == 2 ) {
                      $salesCount += 1;
                      $totSaleCount += 1;
                      $salesPrice += $v['pay_fee'];
                  }
                  //渠道
                  if ($v['channel_type'] == 3) {
                      $channelCount += 1;
                      $totChannelCount += 1;
                      $channelPrice += $v['pay_fee'];
                  }
                  //app付费
                  if ($v['channel_type'] == 4) {
                      $appCount += 1;
                      $totAppCount += 1;
                      $appPay += $v['pay_fee'];
                      $allAppPay += $v['pay_fee'];
                      $appPayDesc = '¥'.$appPay ."/". $appCount.'次';
                      $allAppPayDesc = '¥'.$allAppPay ."/". $totAppCount.'次';
                  }
                  //待签约
                  if (empty($v['contract_no']) && $v['payment_tag_id'] == 2) {
                      $contractCount += 1;
                      $totContractCount += 1;
                  }
                  $temp = number_format($totSelfPrice / $totSelfCount, 2, ".", "");
                  $arr = [
                      'time' => '全部',
                      'sale' => $totSale,
                      'selfPrice' => $totSelfPrice . '/' . $temp,
                      'closePrice' => $totClosePrice,
//                    'experience' => $totSaleCount.'次 ('.$totSaleCount.'次/'.$totChannelCount.'次/'.$totOnLineCount.'次) '.$totExperienceCount.'次',
                      'experience' => $totSelfCount . '次 (' . $totSaleCount . '次/' . $totChannelCount . '次/' . ') ', // . $totExperienceCount . '次',
                      'appPay' => $allAppPayDesc,
                      'toSignUp' => $totContractCount . '次',
                      'refund' => $totRefundPrice . '/' . $totRefundCount . '次'
                  ];

                  $temp = number_format($selfPrice / $selfCount, 2, ".", "");

                  $result['result'][$key] = [
                      'time' => $date[$key]['start'] . '~' . $date[$key]['end'],
                      'start' => $date[$key]['start'],
                      'end' => $date[$key]['end'],
                      'sale' => $sales,
                      'selfPrice' => $selfPrice . '/' . $temp,
                      'closePrice' => $closePrice,
//                    'experience' => $selfCount.'次 ('.$salesCount.'次/'.$channelCount.'次/'.$onLineCount.'次) '.$experienceCount.'次',
                      'experience' => $selfCount . '次 (' . $salesCount . '次/' . $channelCount . '次/' . ') ', //. $experienceCount . '次',
                      'appPay' => $appPayDesc,
                      'toSignUp' => $contractCount . '次',
                      'refund' => $refundPrice . '/' . $refundCount . '次'
                  ];

              }
              $arrAll[$key] = $sales;
              $arrSale[$key] = $salesPrice;
              $arrChannel[$key] = $channelPrice;
//            $arrOnLine[$key] = $onLinePrice;
                }
        }

        if (isset($result['result'])){

            foreach ($result['result'] as &$v){
                //体验
                $experience = ClubCourseSign::where('sign_status', 1)->where('is_experience', 1)->where('sign_date', '>=', $v['start'])->where('sign_date', '<=', $v['end'])->where('club_id', $clubId)->get()->count();
                $v['experience'] = $v['experience'] . $experience . '次';
                $totExperienceCount = $totExperienceCount + $experience;

                $refunds = ClubStudentRefund::where('club_id', $clubId)->where('refund_date', '>=', $v['start'])->where('refund_date', '<=', $v['end'])->get();
                $refundCount = 0;
                $refundPrice = 0;
                if($refunds->isEmpty()){
                    $v['refund'] = '0/0次';
                } else {
                    $refundCount = 0;
                    $refundPrice = 0;
                    foreach ($refunds as $value){
                        $refundCount = $refundCount + 1;
                        $refundPrice = $refundPrice + $value->refund_money;
                    }
                    $v['refund'] = $refundPrice . '/' . $refundCount . '次';
                    $totRefundCount = $totRefundCount + $refundCount;
                    $totRefundPrice = $totRefundPrice + $refundPrice;
                }
            }

        }

        if(!empty($arr)){
            //全部
            $arr['experience'] = $arr['experience'] . $totExperienceCount . '次';
            $arr['refund'] = $totRefundPrice . '/' . $totRefundCount . '次';
            array_unshift($result['result'],$arr);
        //x y数据
        foreach ($date as $ko => $vo){

                $result['xAxis'][] = $vo['start'].'~'.$vo['end'];
            
        }
        $result['yAxis'] = [
            [
                'name' => '总金额',
                'price' => $arrAll
            ],
            [
                'name' => '销售',
                'price' => $arrSale
            ],
            [
                'name' => '渠道',
                'price' => $arrChannel
            ],
//            [
//                'name' => '线上',
//                'price' => $arrOnLine
//            ],

        ];

        //$result['xAxisLastYear'] = $lastYearDate['xAxisLastYear'];
        //$result['yAxisLastYear'] = $lastYearDate['yAxisLastYear'];
        }
        return returnMessage('200','请求成功',$result);

    }
    //获取去年同期数据
    public function getLastYear($arr,$clubId,$salesId){
        foreach ($arr as $val){
            $income[]  = ClubStudentPayment::where(function ($query) use($clubId){
                if(!empty($clubId)){
                    $query->where('club_id',$clubId);
                }
            })->where(function ($query) use($salesId){
                if(!empty($salesId)){
                    $query->where('sales_id',$salesId);
                }
            })->where(function ($query) use($val){
                if(!empty($val)){
                    $query->whereBetween('payment_date',[$val['start'],$val['end']]);
                }
            })->with('payment')->get()->toArray();
        }

        $sales = 0;
        $salesPrice = 0;
        $channelPrice = 0;
//        $onLinePrice = 0;
        $lastYearAll = $lastYearChannel = $lastYearOnLine = $lastYearSales = [];
        foreach ($income as $key => $val){
                foreach ($val as $v){
                    $sales += $v['pay_fee']; //总金额
                    //线上4: 5:
//                    if($this->channelParent($v['channel_type']) == 4 || $this->channelParent($v['channel_type']) == 5){
//                        $onLinePrice += $v['pay_fee'];
//                    };
                    //销售
                    if($this->channelParent($v['channel_type']) == 2){
                        $salesPrice += $v['pay_fee'];
                    }
                    //渠道
                    if($this->channelParent($v['channel_type']) == 3){
                        $channelPrice += $v['pay_fee'];
                    }


                }
            $lastYearAll[$key] = $sales;//总金额
            $lastYearChannel[$key] = $channelPrice;// 渠道
//            $lastYearOnLine[$key] = $onLinePrice;// 线上
            $lastYearSales[$key] = $salesPrice;//销售
        }

        foreach ($arr as $vo){
            $result['xAxisLastYear'][] = $vo['start'].'~'. $vo['end'];
        }
        $result['yAxisLastYear'] = [
            [
                'name' => '总金额',
                'price' => $lastYearAll,
            ],
            [
                'name' => '销售',
                'price' => $lastYearSales,
            ],
            [
                'name' => '渠道',
                'price' => $lastYearChannel,
            ],
//            [
//                'name' => '线上',
//                'price' => $lastYearOnLine,
//            ],
        ];
        return  $result;
    }
    //get channel父级
    public function channelParent($id){
        $channelParent = ClubChannel::where('parent_id',0)->get();//所有的一级分类
        $channel = ClubChannel::find($id);

        //不存在父级
        if(empty($channel->parent)){
           return $id;
        }
        //存在父级找到父级
        foreach ($channelParent as $val){
            if($val->id == $channel->parent_id){
                return $val->id;
            }
        }
    }

    /**
     * 缴费总览导出
     */
    public function paymentAllExport(Request $request){

        $data = $request->all();

        $validate = \Validator::make($data,[
            'salesId' => 'numeric|nullable',
            'startTime' => 'date|nullable',
            'endTime' => 'date|nullable',
            'summary' => 'numeric|nullable'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = isset($data['clubId']) ? $data['clubId'] : ''; //俱乐部id
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';//销售id
        $startTime = isset($data['startTime']) ? strtotime($data['startTime']) : time(); //开始日期 date('Y-m-d',time())不传默认当前
        $endTime = isset($data['endTime']) ? strtotime($data['endTime']) : mktime(23,59,59,date('m'),date('t'),date('Y'));//结束日期 不传默认当前
        $summary = isset($data['summary']) ? $data['summary'] : 2;//汇总方式  不传默认按月

        $date = getSummer($startTime,$endTime,$summary);
        $lastYear = [];
        foreach ($date as $v){
            $lastYear[] = [
                'start' => date('Y-m-d',strtotime('-1 year',strtotime($v['start']))),
                'end' =>  date('Y-m-d',strtotime('-1 year',strtotime($v['end'])))
            ];
        }
        foreach ($date as $val){
            $income[]  = ClubStudentPayment::where(function ($query) use($clubId){
                if(!empty($clubId)){
                    $query->where('club_id',$clubId);
                }
            })->where(function ($query) use($salesId){
                if(!empty($salesId)){
                    $query->where('sales_id',$salesId);
                }
            })->where(function ($query) use($val){
                if(!empty($val)){
                    $query->whereBetween('payment_date',[$val['start'],$val['end']]);
                }
            })->with('payment')->get()->toArray();
        }

        $totSale = 0;
        $totSelfPrice = 0;
        $totClosePrice = 0;
        $totCourseCount = 0;
        $totSaleCount = 0;
        $totChannelCount = 0;
        $totAppCount = 0;
        $totContractCount = 0;
        $totRefundPrice = 0;
        $totExperienceCount = 0;
        $totRefundCount = 0;
        $result = [];

        foreach ($income as $key => $val){
            $sales = 0;//销售额
            $selfPrice = 0; //自营课
            $closePrice = 0; //封闭营
            $courseCount = 0;//总课程
            $contractCount = 0; //待签约
            $refundPrice =0; //退款
            $selfCount = 0;//自营课数量
//            $onLineCount = 0;//线上
            $salesCount = 0; //销售
            $channelCount = 0 ; //渠道
            $appCount = 0; //app付费
            $experienceCount = 0; //体验
            $refundCount = 0;
//            $onLinePrice = 0;
            $salesPrice = 0;
            $channelPrice = 0;
            $totUnitPrice = $unitPrice = 0;

            foreach ($val as $k => $v){
                $sales += $v['pay_fee'];
                $totSale += $v['pay_fee'];
                //自营课
                if($v['payment']['type'] == 1 || $v['payment']['type'] == 2){
                    $selfCount += 1;
                    $totSelfPrice += $v['pay_fee'];
                    $totCourseCount += $v['course_count'];
                    $totUnitPrice = $totSelfPrice/$totCourseCount;
                    $selfPrice += $v['pay_fee']; //自营课
                    $courseCount += $v['course_count']; // 总课程数
                    $unitPrice = $selfPrice/$courseCount;//课程单价
                }
                //封闭营
                if($v['payment']['type'] == 3){
                    $totClosePrice += $v['pay_fee'];
                    $closePrice += $v['pay_fee'];

                }
                //线上
//                if($this->channelParent($v['channel_type']) == 1){
//                    $onLineCount += 1;
//                    $totOnLineCount += 1;
//                    $onLinePrice += $v['pay_fee'];
//                };
                //销售
                if($this->channelParent($v['channel_type']) == 2){
                    $salesCount += 1;
                    $totSaleCount += 1;
                    $salesPrice += $v['pay_fee'];
                }
                //渠道
                if($this->channelParent($v['channel_type']) == 3){
                    $channelCount += 1;
                    $totChannelCount += 1;
                    $channelPrice += $v['pay_fee'];
                }
                //体验
                $experience = ClubCourseSign::where('student_id',$v['student_id'])->where('sign_status',1)->where('is_subscribe',1)->get();
                foreach ($experience as $v4){
                    $experienceCount += 1;
                    $totExperienceCount += 1;
                }


                //app付费
                if($v['channel_type'] == 4){
                    $appCount += 1;
                    $totAppCount += 1;
                }
                //待签约
                if(empty($v['contract_no'])){
                    $contractCount += 1;
                    $totContractCount += 1;
                }
                //退款
                $refund = ClubStudentRefund::where('club_id',$clubId)->where('student_id',$v['student_id'])->where('student_payment_id',$v['id'])->get();
                foreach ($refund as $v3){
                    $refundPrice += $v3->refund_money;
                    $refundCount += 1;
                    $totRefundCount += 1;
                    $totRefundPrice += $v3->refund_money;
                }

                $arr = [
                    'time' => '全部',
                    'sale' => $totSale,
                    'selfPrice' => $totSelfPrice.'/'.number_format($totUnitPrice,2,".",""),
                    'closePrice' => $totClosePrice,
//                    'experience' => $totSaleCount.'次 ('.$totSaleCount.'次/'.$totChannelCount.'次/'.$totOnLineCount.'次) '.$totExperienceCount.'次',
                    'experience' => $totSaleCount.'次 ('.$totSaleCount.'次/'.$totChannelCount.'次/'.') '.$totExperienceCount.'次',
                    'appPay' => $totAppCount.'次',
                    'toSignUp' => $totContractCount.'次',
                    'refund' =>  $totRefundPrice.'/'.$totRefundCount.'次'
                ];
                $result['result'][$key] = [
                    'time' =>  $date[$key]['start'].'~'. $date[$key]['end'],
                    'sale' => $sales,
                    'selfPrice' => $selfPrice.'/'.number_format($unitPrice,2,".",""),
                    'closePrice' => $closePrice,
//                    'experience' => $selfCount.'次 ('.$salesCount.'次/'.$channelCount.'次/'.$onLineCount.'次) '.$experienceCount.'次', //上线注释掉了
                    'experience' => $selfCount.'次 ('.$salesCount.'次/'.$channelCount.'次/'.') '.$experienceCount.'次',
                    'appPay' => $appCount.'次',
                    'toSignUp'=> $contractCount.'次',
                    'refund' => $refundPrice.'/'.$refundCount.'次'
                ];
            }
        }
        if(!empty($arr)){
            array_unshift($result['result'],$arr);

            $cellData =['时间','销售额','自营课收入/单价','封闭营收入','自营课付费(未知/新增/渠道/续费)','来自App付费','待签约','退款'];
            array_unshift($result['result'],$cellData);
            $cellData = $result['result'];

            $date = date('Y-m-d' ,time()).'pay_overview';
             Excel::create($date,function ($excel) use ($cellData){
                $excel->sheet('pay',function ($sheet) use($cellData){
                    $sheet->rows($cellData);
                });
            })->export('xls');
            return returnMessage('200', '导出成功',[]);
        }
    }







    /**
     * 某个时间段的缴费记录
     * @param Request $request
     * @return array
     */
    public  function getPaymentRecord(Request $request){
        $data = $request->all();

        $validate = \Validator::make($data,[
            'startTime' => 'date|required',
            'endTime' => 'date|required',
            'saleId' => 'numeric|nullable',
            'source' => 'numeric|nullable',
            'Payment' => 'numeric|nullable',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] : '';
        $endTime = isset($data['endTime']) ? $data['endTime'] : '';
        $saleId = isset($data['saleId']) ? $data['saleId'] : '';
        $source = isset($data['source']) ? $data['source'] : '';
        $payment = isset($data['Payment']) ? $data['Payment'] : '';
        $pagePreNum = isset($data['pagePreNum']) ? $data['pagePreNum'] : 10;//一页显示的数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1;//页码

        $offset = ($currentPage-1)*$pagePreNum;
        $studentPayment = ClubStudentPayment::where(function($query) use($saleId){
            if(!empty($saleId)){
                $query->where('sales_id',$saleId);
            }
        })
            ->where('club_id',$clubId)
            ->where(function($query) use($source){
                if(!empty($source)){
                    $query->where('channel_type',$source);
                }
            })
            ->where(function($query) use ($payment){
                if(!empty($payment)){
                    $query->where('payment_id',$payment);
                }
            })
            ->with('payment','refund','sales','student')
            ->whereBetween('payment_date',[$startTime,$endTime])
            ->get();

         $result['totNum'] = count($studentPayment);
         $studentPayment = ClubStudentPayment::where(function($query) use($saleId){
                                if(!empty($saleId)){
                                    $query->where('sales_id',$saleId);
                                }
                        })
                        ->where('club_id',$clubId)
                        ->where(function($query) use($source){
                            if(!empty($source)){
                                $query->where('channel_type',$source);
                            }
                        })
                        ->with('payment','refund','sales','student')
                        ->whereBetween('payment_date',[$startTime,$endTime])
                        ->offset($offset)
                        ->limit($pagePreNum)
                        ->get();


        $countRefund2 = $countRefund = $countClose = $countSelf = $countRenew = $countChannel = $countSale = $count = 0;
        //$countOnLine = 0
        $refund = $priceClose = $priceRefund = $priceSelf = $priceRenew  = $priceChannel   = $priceSale = 0;
//      $priceOnLine=0
        $result['countRefund2'] = $result['countClose'] = $result['countRefund'] = $result['countSelf'] =  $result['countRenew'] = $result['countSale'] = $result['countChannel'] = $result['refundClose'] = $result['refundSummary'] = $result['totClose'] = $result['totSummary']  = $result['totRenew'] = $result['totChannel'] = $result['totSale']= 0;
//        $result['totOnline'] = 0
          foreach ($studentPayment as $val){

              //计算 销售2 渠道3 线上:4 5 线上暂时不要
              switch ($val->channel_type){
                  case "2": $countSale += 1;$priceSale += $val->pay_fee; $result['totSale']= $priceSale;$result['countSale']=$countSale; break;
                  case "3": $countChannel +=1;$priceChannel += $val->pay_fee; $result['totChannel']= $priceChannel;$result['countChannel']=$countChannel; break;
                  // case "4": $countOnLine +=1;$priceOnLine += $val->pay_fee; $result['totOnline']= $priceOnLine .' '.$countOnLine;break;
                  //case "5": $countOnLine +=1;$priceOnLine += $val->pay_fee; $result['totOnline']= $priceOnLine .' '.$countOnLine;break;
              }
              //续费
              if(!empty($val->is_pay_again)){
                  $priceRenew += $val->pay_fee;
                  $countRenew += 1;
              }
              $result['totRenew'] = $priceRenew;
              $result['countRenew'] = $countRenew;
              //自营
             if($val->payment->type == 1 || $val->payment->type == 2){
                  $countSelf += 1;
                  $priceSelf += $val->pay_fee;
                  $result['totSummary'] = $priceSelf;
                  $result['countSelf'] = $countSelf;
                  if(!empty($val->refund)){
                      $countRefund += count($val->refund);
                      $priceRefund += $val->refund->refund_money;
                      $result['refundSummary'] = $priceRefund;
                      $result['countRefund'] = $countRefund;
                  }
             }
             //封闭营
             if($val->payment->type == 3){
                 $countClose += 1;
                 $priceClose += $val->pay_fee;
                 $result['totClose'] = $priceClose;
                 $result['countClose'] = $countClose;
                 if(!empty($val->refund)) {
                     $countRefund2 += count($val->refund);
                     $refund += $val->refund->refund_money;
                     $result['refundClose'] = $refund;
                     $result['countRefund2'] = $countRefund2;
                 }
             }

          }


          $result['result'] = $studentPayment->transform(function($item){
                $arr = [
                    'payId' => $item->id,
                    'student' => $item->student->name,
                    'sale' => count($item->sales) ? $item->sales->sales_name : '',
                    'className' => $this->getClassName($item->student->main_class_id),
                    'source' => $this->getChannelName($item->channel_type),
                    'payment' => $item->payment->name,
                    'price' => $item->payment->price,
                    'discount' => $item->payment->price - $item->pay_fee,
                    'actualPrice' => $item->pay_fee,
                    'contract' => $item->contract_no ? $item->contract_no : '待签',
                    'payTime' => $item->payment_date,
                    'pastTime' => $item->expire_date,
                    'remark' => $item->remark,
                    'operationTime' => date('Y-m-d',strtotime($item->created_at))
                ];
                return $arr;
          });
          $result['xAxis'] = ['汇总','退款'];
          $result['yAxis']= [
              [
                  'name' => '自营课',
                  'price' => [$result['totSummary'],$result['refundSummary']],
                  'count' => [$result['countSelf'],$result['countRefund']]
              ],
              [
                  'name' => '封闭营',
                  'price' => [$result['totClose'],$result['refundClose']],
                  'count' => [$result['countClose'],$result['countRefund2']]
              ]
          ];
        return returnMessage('200','请求成功',$result);
    }

    //获取主班级名称
    public function getClassName($id){
        $class = ClubClass::find($id);
        return $class['name'];
    }
    /**
     * 获取渠道名称
     * @param $channelId
     * @return mixed
     */
    public function getChannelName($channelId){
       $channel = ClubChannel::find($channelId);
       return $channel['channel_name'];
    }
    /**
     * 某一时间段记录导出
     */
    public function paymentRecordExport(Request $request){
        $data = $request->all();

        $validate = \Validator::make($data,[
            'startTime' => 'date|required',
            'endTime' => 'date|required',
            'saleId' => 'numeric|nullable',
            'source' => 'numeric|nullable',
            'payment' => 'numeric|nullable',

        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['clubId']) ? $data['clubId'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] : '';
        $endTime = isset($data['endTime']) ? $data['endTime'] : '';
        $saleId = isset($data['saleId']) ? $data['saleId'] : '';
        $source = isset($data['source']) ? $data['source'] : '';
        $payment = isset($data['payment']) ? $data['payment'] : '';


        $studentPayment = ClubStudentPayment::where(function($query) use($saleId){
            if(!empty($saleId)){
                $query->where('sales_id',$saleId);
            }
        })
            ->where('club_id',$clubId)
            ->where(function($query) use($source){
                if(!empty($source)){
                    $query->where('channel_type',$source);
                }
            })
            ->where(function($query) use($payment){
                if(!empty($payment)){
                    $query->where('payment_id',$payment);
                }
            })
            ->with('payment','refund','sales','student')
            ->whereBetween('payment_date',[$startTime,$endTime])
            ->get();
        $result['result'] = $studentPayment->transform(function($item){
            $arr = [
                'payId' => $item->id,
                'student' => $item->student->name,
                'sale' => $item->sales->sales_name,
                'className' => $this->getClassName($item->student->main_class_id),
                'source' => $this->getChannelName($item->channel_type),
                'payment' => $item->payment->name,
                'price' => $item->payment->price,
                'discount' => $item->payment->price - $item->pay_fee,
                'actualPrice' => $item->pay_fee,
                'contract' => $item->contract_no ? $item->contract_no : '待签',
                'payTime' => $item->payment_date,
                'pastTime' => $item->expire_date,
                'operationTime' => date('Y-m-d',strtotime($item->created_at)),
                'remark' => $item->remark
            ];
            return $arr;
        });
        $cellData = ['缴费号','学员','销售员','班级','来源','方案','价格','优惠折扣','实际付费','合同','缴费时间','过期时间','操作时间','备注'];
        if(!empty(count($result['result']))){

                $result['result'] = collect($result['result'])->toArray();

                array_unshift($result['result'],$cellData);
                $cellData = $result['result'];
                $date = date('Y-m-d' ,time()).'pay_details';
                Excel::create($date,function ($excel) use ($cellData){
                    $excel->sheet('pay',function ($sheet) use($cellData){
                        $sheet->rows($cellData);
                    });
                })->export('xls');

                return returnMessage('200', '导出成功',[]);
            }
    }

    /**
     * 某一时间段的退款记录
     * @param Request $request
     * @return array
     */

        public function getRefundRecord(Request $request){
        $data = $request->all();

        $validate = \Validator::make($data,[
            'startTime' => 'date|required',
            'endTime' => 'date|required',
            'refundId' => 'numeric|nullable',
            'salesId' => 'numeric|nullable',
            'studentId' => 'numeric|nullable',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] : date('Y-m-d',time());
        $endTime = isset($data['endTime']) ? $data['endTime'] : date('Y-m-d',time());
        $refundId = isset($data['refundId']) ? $data['refundId'] : '';
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';
        $pagePreNum = isset($data['pagePreNum']) ? $data['pagePreNum'] : 10;//一页显示的数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1;//页码

        $offset = ($currentPage-1)*$pagePreNum;
        $refund = ClubStudentRefund::where(function ($query) use($refundId){
            if(!empty($refundId)){
                $query->where('id',$refundId);
            }
        })
            ->whereHas('student',function($query) use($salesId){
                if(!empty($salesId)){
                    $query->where('sales_id',$salesId);
                }
            })->where(function ($query) use($studentId){
                if(!empty($studentId)){
                    $query->where('student_id',$studentId);
                }
            })
            ->where('club_id',$clubId)
            ->with('student')
            ->whereBetween('refund_date',[$startTime,$endTime])
            ->offset($offset)
            ->limit($pagePreNum)
            ->get();
        $result['totNum'] = count($refund);
        /*
        $refund = ClubStudentRefund::where(function ($query) use($refundId){
            if(!empty($refundId)){
                $query->where('id',$refundId);
            }
            })
            ->whereHas('student',function($query) use($salesId){
                if(!empty($salesId)){
                    $query->where('sales_id',$salesId);
                }
            })->where(function ($query) use($studentId){
                if(!empty($studentId)){
                    $query->where('student_id',$studentId);
                }
            })
            ->where('club_id',$clubId)
            ->with('student')
            ->whereBetween('refund_date',[$startTime,$endTime])
            ->offset($offset)
            ->limit($pagePreNum)
            ->get();
        */

        $result['result'] = $refund->transform(function($item){
            $arr = [
                'id' => $item->id,
                'payId' => $item->student_payment_id,
                'student'=> $item->student->name,
                'sales' => $item->student->sales_name,
                'payPlan' => $this->getPaymentName($item->student_payment_id,1),
                'price' => $this->getPaymentName($item->student_payment_id,2),
                'refundPrice' => $item->refund_money,
                'handlers' => $item->refund_operation_sales_id,
                'remark' => $item->remark,
                'operationTime' => $item->refund_date
            ];
            return $arr;

        });
        return returnMessage('200','请求成功',$result);
    }

    /**
     * 某一时间段的退款记录导出
     */
    public function refundRecordExport(Request $request){
        $data = $request->all();

        $validate = \Validator::make($data,[
            'startTime' => 'date|nullable',
            'endTime' => 'date|nullable',
            'refundId' => 'numeric|nullable',
            'salesId' => 'numeric|nullable',
            'studentId' => 'numeric|nullable',

        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

        $clubId = isset($data['clubId']) ? $data['clubId'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] : date('Y-m-d',time());
        $endTime = isset($data['endTime']) ? $data['endTime'] : date('Y-m-d',time());
        $refundId = isset($data['refundId']) ? $data['refundId'] : '';
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';
        $studentId = isset($data['studentId']) ? $data['studentId'] : '';

        $refund = ClubStudentRefund::where(function ($query) use($refundId){
            if(!empty($refundId)){
                $query->where('id',$refundId);
            }
        })
            ->whereHas('student',function($query) use($salesId){
                if(!empty($salesId)){
                    $query->where('sales_id',$salesId);
                }
            })->where(function ($query) use($studentId){
                if(!empty($studentId)){
                    $query->where('student_id',$studentId);
                }
            })
            ->where('club_id',$clubId)
            ->with('student')
            ->whereBetween('refund_date',[$startTime,$endTime])
            ->get();

        $result['result'] = $refund->transform(function($item){
            $arr = [
                'id' => $item->id,
                'payId' => $item->student_payment_id,
                'student'=> $item->student->name,
                'sales' => $item->student->sales_name,
                'payPlan' => $this->getPaymentName($item->student_payment_id,1),
                'price' => $this->getPaymentName($item->student_payment_id,2),
                'refundPrice' => $item->refund_money,
                'handlers' => $item->refund_operation_sales_id,
                'remark' => $item->remark,
                'operationTime' => $item->refund_date
            ];
            return $arr;
        });

        if(!empty(count($result['result']))){
            $result['result']  = collect($result['result'])->toArray();
            $cellData = ['编号','缴费号','学员','销售员','缴费计划','价格','退款金额','操作者','备注','操作时间'];
            array_unshift($result['result'],$cellData);
            $cellData =  $result['result'];
            $date = date('Y-m-d' ,time()).'refund_details';
            Excel::create($date,function ($excel) use ($cellData){
                $excel->sheet('pay',function ($sheet) use($cellData){
                    $sheet->rows($cellData);
                });
            })->export('xls');

            return returnMessage('200', '导出成功',[]);
        }
    }

    //获取缴费方案数据  1：缴费方案名称 2：缴费方案价格
    public function getPaymentName($id,$status){
       $studentPayment = ClubStudentPayment::find($id)->payment;
       if($status == 1){
           return $studentPayment->name;
       }
       if($status == 2){
           return $studentPayment->price;
       }
    }
    /**
     * 学员select
     */
    public function getStudent(Request $request){
          $data = $request->all();
            $clubId = $data['user']['club_id'];
          $student = ClubStudent::where('club_id',$clubId)->get();
          $result['result'] = $student->transform(function($item){
              $arr = [
                'id' => $item->id,
                'name' => $item->name,
              ];
            return $arr;
          });
          return returnMessage('200','请求成功',$result);
    }

    public function getPaymentTag(){
        $data = ClubPaymentTag::all();
        $result['result'] = $data->transform(function ($item){
            $arr = [
                'id' => $item->id,
                'name' => $item->name
            ];
            return $arr;
        });
    return  returnMessage('200','请求成功',$result);
    }
}