<?php
namespace App\Api\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Model\ClubRole\ClubRole;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubSales\ClubSalesPerformanceBySeasonSnapshot;
use App\Model\ClubSalesPerformanceByDay\ClubSalesPerformanceByDay;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubStudentSalesHistory\ClubStudentSalesHistory;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;
use App\Services\Common\CommonService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;


class SalesController extends Controller
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


    //1.销售管理列表-查询
    public function  salesList (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $search = isset($data['search']) ? $data['search'] : '';
        $year = isset($data['year']) ? $data['year'] : "";
        //判断该季度是否属于当前季度
        $nowS = ceil((date('n')) / 3);
        $season = isset($data['season']) ? $data['season'] : $nowS;
        $isNowS = false;
        if ($season == $nowS) {
            $isNowS = true;
        }
        $status = isset($data['status']) ? $data['status'] : '';  //1=有效;0=失效
        $role_id = $this->user->role_id;
        $type = ClubRole::where('id',$role_id)->value('type');
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        //销售 type =2
        if($type == 2) {
            $CommonService = new CommonService();
            $userid = $this->user->id;
            $sale_ids = $CommonService->getAllSalesIdsByUserId($userid);

            $sale = DB::table('club_sales AS sales')
                ->select(
                    'snapshot1.target1', 'snapshot1.target2','snapshot1.id as snid', 'snapshot1.to_bind_app', 'snapshot1.to_sign', 'sales.id', 'sales.user_id', 'sales.sales_dept_id', 'sales.sales_name', 'sales.mobile', 'sales.status', 'department.name'
                )
                ->join('club_department AS department', 'sales.sales_dept_id', '=', 'department.id')
                ->join('club_sales_performance_by_season_snapshot as snapshot1', function ($join) {
                    $join->on('snapshot1.target_id', '=', 'sales.id')
                        ->where('snapshot1.target_type', '=', 2)
                        ->where('department.type', '=', 2);
                }, null, null, 'left');

            $sale2 = DB::table('club_sales AS sales')
                ->select(
                    'snapshot1.target1', 'snapshot1.target2','snapshot1.id as snid', 'snapshot1.to_bind_app', 'snapshot1.to_sign', 'sales.id', 'sales.user_id', 'sales.sales_dept_id', 'sales.sales_name', 'sales.mobile', 'sales.status', 'department.name'
                )
                ->join('club_department AS department', 'sales.sales_dept_id', '=', 'department.id')
                ->join('club_sales_performance_by_season_snapshot as snapshot1', function ($join) {
                    $join->on('snapshot1.target_id', '=', 'sales.id')
                        ->where('snapshot1.target_type', '=', 2)
                        ->where('department.type', '=', 2);
                }, null, null, 'left');

            $sale1 = DB::table('club_sales AS sa')
                ->join('club_department AS de', 'de.id', '=', 'sa.sales_dept_id')
                ->join('club_sales_performance_by_season_snapshot AS sn', 'sn.target_id', '=', 'sa.id')
                ->select(
                    array(
                        \DB::raw('COUNT(sn.target1) as target1'),
                        \DB::raw('COUNT(sn.target2) as target2'),
                        \DB::raw('COUNT(sn.to_bind_app) as to_bind_app'),
                        \DB::raw('COUNT(sn.to_sign) as to_sign')
                    )
                )
                ->where('sn.target_type', 2)
                ->where('de.type', 2);

            if (strlen($search) > 0) {
                $sale->where(function ($query) use ($search) {
                    $query->where('sales.sales_name'  , 'like','%'.$search.'%')
                        ->orwhere('sales.mobile', $search)
                        ->orwhere('sales.id', $search);
                });

                $sale2->where(function ($query) use ($search) {
                    $query->where('sales.sales_name'  , 'like','%'.$search.'%')
                        ->orwhere('sales.mobile', $search)
                        ->orwhere('sales.id', $search);
                });

                $sale1->where(function ($query) use ($search) {
                    $query->where('sa.sales_name'  , 'like','%'.$search.'%')
                        ->orwhere('sa.mobile', $search)
                        ->orwhere('sa.id', $search);
                });

            }

            if (strlen($year) > 0) {
                $sale->where('snapshot1.year', $year);
                $sale2->where('snapshot1.year', $year);
                $sale1->where('sn.year', $year);
            }

            if (strlen($season) > 0) {
                $sale->where('snapshot1.season', $season);
                $sale2->where('snapshot1.season', $season);
                $sale1->where('sn.season', $season);
            }

            if (strlen($status) > 0) {
                $sale->where('sales.status', $status);
                $sale2->where('sales.status', $status);
                $sale1->where('sa.status', $status);
            }

            $sale->where('sales.club_id', $this->user->club_id)->whereIn('sales.id',$sale_ids)->where('sales.is_delete', 0)->orderBy('sales.id', 'desc');
            $sale1->where('sa.club_id', $this->user->club_id)->whereIn('sa.id',$sale_ids)->where('sa.is_delete', 0);
            $sale2->where('sales.club_id', $this->user->club_id)->whereIn('sales.id',$sale_ids)->where('sales.is_delete', 0);

            $sale = $sale->paginate($pagePerNum);
            $mycount = $sale2->count();

            $result = array();
            $result['total'] = $mycount;
            $result['data'] = $sale->transform(function ($item) {
                $result = [
                    'target1' => $item->target1,
                    'target2' => $item->target2,
                    'to_bind_app' => $item->to_bind_app,
                    'to_sign' => $item->to_sign,
                    'snid' => $item->snid,
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'sales_dept_id' => $item->sales_dept_id,
                    'sales_name' => $item->sales_name,
                    'mobile' => $item->mobile,
                    'status' => $item->status,
                    'name' => $item->name
                ];

                return $result;
            });

            $res = $sale1->get();
            $count = $res[0];
            $toSign = $count->to_sign;
            $toBindApp = $count->to_bind_app;
            $target1 = $count->target1;
            $target2 = $count->target2;

            $result["countdata"] = array("toSign" => intval($toSign), "toBindApp" => $toBindApp, "target1" => intval($target1), "target2" => intval($target2));
            $result["isSales"] = 1;
        }else {
            $sale = DB::table('club_sales AS sales')
                ->select(
                    'snapshot1.target1', 'snapshot1.target2','snapshot1.id as snid', 'snapshot1.to_bind_app', 'snapshot1.to_sign', 'sales.id', 'sales.user_id', 'sales.sales_dept_id', 'sales.sales_name', 'sales.mobile', 'sales.status', 'department.name'
                )
                ->join('club_department AS department', 'sales.sales_dept_id', '=', 'department.id')
                ->join('club_sales_performance_by_season_snapshot as snapshot1', function ($join) {
                    $join->on('snapshot1.target_id', '=', 'sales.id')
                        ->where('snapshot1.target_type', '=', 2)
                        ->where('department.type', '=', 2);
                }, null, null, 'left');
            $sale2 = DB::table('club_sales AS sales')
                ->select(
                    'snapshot1.target1', 'snapshot1.target2','snapshot1.id as snid', 'snapshot1.to_bind_app', 'snapshot1.to_sign', 'sales.id', 'sales.user_id', 'sales.sales_dept_id', 'sales.sales_name', 'sales.mobile', 'sales.status', 'department.name'
                )
                ->join('club_department AS department', 'sales.sales_dept_id', '=', 'department.id')
                ->join('club_sales_performance_by_season_snapshot as snapshot1', function ($join) {
                    $join->on('snapshot1.target_id', '=', 'sales.id')
                        ->where('snapshot1.target_type', '=', 2)
                        ->where('department.type', '=', 2);
                }, null, null, 'left');


            $sale1 = DB::table('club_sales AS sa')
                ->join('club_department AS de', 'de.id', '=', 'sa.sales_dept_id')
                ->join('club_sales_performance_by_season_snapshot AS sn', 'sn.target_id', '=', 'sa.id')
                ->select(
                    array(
                        \DB::raw('COUNT(sn.target1) as target1'),
                        \DB::raw('COUNT(sn.target2) as target2'),
                        \DB::raw('COUNT(sn.to_bind_app) as to_bind_app'),
                        \DB::raw('COUNT(sn.to_sign) as to_sign')
                    )
                )
                ->where('sn.target_type', 2)
                ->where('de.type', 2);

            if (strlen($search) > 0) {

                $sale->where(function ($query) use ($search) {
                    $query->where('sales.sales_name'  , 'like','%'.$search.'%')
                        ->orwhere('sales.mobile', $search)
                        ->orwhere('sales.id', $search);
                });

                $sale2->where(function ($query) use ($search) {
                    $query->where('sales.sales_name'  , 'like','%'.$search.'%')
                        ->orwhere('sales.mobile', $search)
                        ->orwhere('sales.id', $search);
                });

                $sale1->where(function ($query) use ($search) {
                    $query->where('sa.sales_name'  , 'like','%'.$search.'%')
                        ->orwhere('sa.mobile', $search)
                        ->orwhere('sa.id', $search);
                });
            }

            if (strlen($year) > 0) {
                $sale->where('snapshot1.year', $year);
                $sale2->where('snapshot1.year', $year);
                $sale1->where('sn.year', $year);
            }

            if (strlen($season) > 0) {
                $sale->where('snapshot1.season', $season);
                $sale2->where('snapshot1.season', $season);
                $sale1->where('sn.season', $season);
            }

            if (strlen($status) > 0) {
                $sale->where('sales.status', $status);
                $sale2->where('sales.status', $status);
                $sale1->where('sa.status', $status);
            } else {
                if ($isNowS) {  //当前季度筛选有效销售 Jesse
                    $sale->where('sales.status', 1);
                }
            }

            $sale->where('sales.club_id', $this->user->club_id)->where('sales.is_delete', 0)->orderBy('sales.id', 'desc');
            $sale1->where('sa.club_id', $this->user->club_id)->where('sa.is_delete', 0);
            $sale2->where('sales.club_id', $this->user->club_id)->where('sales.is_delete', 0);

            $sale = $sale->paginate($pagePerNum);
            $mycount = $sale2->count();

            $result = array();
            $result['total'] = $mycount;
            $result['data'] = $sale->transform(function ($item) {
                $result = [
                    'target1' => $item->target1,
                    'target2' => $item->target2,
                    'to_bind_app' => $item->to_bind_app,
                    'to_sign' => $item->to_sign,
                    'snid' => $item->snid,
                    'id' => $item->id,
                    'user_id' => $item->user_id,
                    'sales_dept_id' => $item->sales_dept_id,
                    'sales_name' => $item->sales_name,
                    'mobile' => $item->mobile,
                    'status' => $item->status,
                    'name' => $item->name
                ];

                return $result;
            });

            $res = $sale1->get();
            $count = $res[0];
            $toSign = $count->to_sign;
            $toBindApp = $count->to_bind_app;
            $target1 = $count->target1;
            $target2 = $count->target2;

            $result["countdata"] = array("toSign" => intval($toSign), "toBindApp" => $toBindApp, "target1" => intval($target1), "target2" => intval($target2));
            $result["isSales"] = 0;
        }
        return returnMessage('200', '请求成功', $result);
    }


    public function gatAllSales(Request $request){
        $sale = ClubSales::select('id','sales_name as name')->where('club_id',$this->user->club_id)->where('is_delete',0)->get();
        return returnMessage('200', '请求成功', $sale);
    }
    //2.销售修改显示数据
    public function  salesEdit (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'salesId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $res = DB::table('club_sales_performance_by_season_snapshot AS snapshot1')
            ->join('club_department AS department','snapshot1.target_id','=','department.id')
            ->join('club_sales AS sales','snapshot1.target_id','=','sales.id')
            ->select(
                'snapshot1.target1','snapshot1.target2','snapshot1.to_bind_app','snapshot1.to_sign','snapshot1.year','snapshot1.season','snapshot1.id',
                'sales.id','sales.user_id','sales.sales_dept_id','sales.sales_name','sales.mobile','sales.status','department.name'
            )
            ->where('snapshot1.target_type',2)
            ->where('snapshot1.target_id',$input['salesId'])
            ->get();
        return returnMessage('200', '',$res);
    }

    //3.销售管理修改操作
    public function  salesEditAction (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'sales_id' => 'required|numeric',
            'target2' => 'required|numeric',
            'target1' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $field = ClubSales::find($input['sales_id']);
        if(!empty($field)){
            $field->target2 = $input["target2"];
            $field->target1 = $input["target1"];

            $field->update();
            return returnMessage('200', '');
        }else{
            return returnMessage('200', '没有相关信息');
        }
    }

    /**
     * 销售管理-名下学员搜索列表
     * @param object $request
     * @return array
     * @date 2018/9/25
     * @author edit by jesse
     */
    public function salesStudent(Request $request) {
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
            'status' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $search = isset($data['search']) ? $data['search'] : "";
        $venue_id = isset($data['venueId']) ? $data['venueId'] : "";
        $status = (int)$data['status'];
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $sale = DB::table('club_student AS Student')
            ->join('club_club AS club','club.id','=','Student.club_id')
            ->join('club_venue AS venue','venue.id','=','Student.venue_id')
            ->join('club_class AS class','class.id','=','Student.main_class_id')
            ->select(
                'Student.*','club.name AS clubname','venue.name AS venuename','class.name as classname'
            )
            ->where('Student.sales_id', $data['salesId']);

        if(strlen($search)>0){

            $sale->where(function ($query) use ($search) {
                $query->where('Student.name' , 'like', '%'.$search.'%')
                    ->orwhere('Student.guarder_mobile', $search)
                    ->orwhere('Student.id', $search);
            });
        }
        if (strlen($venue_id) > 0) {
            $sale->where('Student.venue_id', $venue_id);
        }
        if ($status === 3) {    //非正式用户
            $sale->where('Student.status', 2);
        } elseif ($status === 1) {  //正式用户
            $sale->where('Student.status', 1);
        } else {
            $sale->where('Student.status', '<>', 3);
        }
        $sale = $sale->where('Student.club_id', $this->user->club_id)->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $sale);
    }

    //5.销售管理-名下学员转移re
    public function  salesStudentChange (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|array',
            'salesId' => 'required|numeric',
            'newsalesId' => 'required|numeric',
            'newsalesName' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $studentIds = isset($input['studentId']) ? $input['studentId'] : array();

        if(count($studentIds)>0){
            foreach ($studentIds as $item) {
                $student = ClubStudent::where('sales_id', $input['salesId'])->where('id', $item)->get();

                if (count($student) > 0) {
                    $stu = ClubStudent::find($item);
                    if (!empty($stu)) {
                        $stu->sales_id = $input['newsalesId'];
                        $stu->sales_name = $input['newsalesName'];
                        $stu->update();
                    }

                    #添加记录
                    $studentSalesHistory = new ClubStudentSalesHistory();
                    $studentSalesHistory->student_id = $item;
                    $studentSalesHistory->sales_id = $input['salesId'];
                    $studentSalesHistory->sales_name = $input['newsalesName'];
                    $studentSalesHistory->operation_userid = $this->user->id;
                    $studentSalesHistory->operation_username = $this->user->username;
                    $studentSalesHistory->save();
                }
            }
            return returnMessage('200', '');
        }else{
            return returnMessage('1001', config('error.common.1001'));
        }

    }

    /**
     * 销售管理-业绩搜索列表
     * @param object $request
     * @return array
     * @date 2018/9/26
     * @author edit by jesse
     */
    public function  salesRecord (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';            //销售id

//        $year = isset($data['year']) ? $data['year'] : date('Y',time());
//        $startTime = isset($data['startTime']) ? $data['startTime'] : '';
//        $endTime = isset($data['endTime']) ? $data['endTime'] : '';
        $search = isset($data['search']) ? $data['search'] : '';
        $status = isset($data['status']) ? $data['status'] : '';
        $type = isset($data['type']) ? $data['type'] : '';   //0季度 ，1.按周统计 2.按月统计

        if($type == 0){
            $year = isset($data['year']) ? $data['year'] : '';
            $season = isset($data['season']) ? $data['season'] : '';
            $startTime = date('Y-m-d H:i:s', mktime(0, 0, 0,$season*3-3+1,1,intval($year)));
            $endTime = date('Y-m-d H:i:s', mktime(23,59,59,$season*3,date('t',mktime(0, 0 , 0,$season*3,1,intval($year))),intval($year)));
        }elseif($type == 1){
            $startTime = isset($data['startTime']) ? $data['startTime'] : '';
            $endTime = isset($data['endTime']) ? $data['endTime'] : '';
        }else{
            $month = isset($data['month']) ? $data['month'] : date('m');
            if(strlen($month)>0){
                $Y = date ( "Y" ,strtotime($month));
                $m = date ( "m" ,strtotime($month));
                $t = date ( "t" ,strtotime($month));
                $startTime = date ( "Y-m-d H:i:s", mktime ( 0, 0, 0, $m, 1, $Y ) );
                $endTime = date ( "Y-m-d H:i:s", mktime ( 23, 59, 59, $m, $t,$Y));
            }
        }

        $sale = DB::table('club_sales')->where('id',$salesId)->where('is_delete',0);
        if(strlen($search)>0){
            $sale->where(function ($query) use ($search) {
                $query->where('sales_name'  , 'like','%'.$search.'%')
                    ->orwhere('mobile', $search)
                    ->orwhere('id', $search);
            });
        }
        if(strlen($status)>0){
            $sale->where('status',$status);
        }
        $sale = DB::table('club_sales')->where('id',$salesId)->where('is_delete',0);
        $sale = $sale->get();
        foreach ($sale as $items) {
            $saleId = $items->id;
            $items->studentcount = 0;  //获取推广数据
            $items->subscribecount =0;
            $items->signcount = 0;
            if (strlen($saleId)>0) {
                $items->studentcount = ClubStudent::where([
                    ['is_delete' , 0],
                    ['sales_id', $saleId],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                ])->count();    //获取推广数据
                $items->subscribecount = ClubStudentSubscribe::notDelete()->where([
                    ['is_delete' , 0],
                    ['sales_id', $saleId],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                ])->count(); //预约数据
                $items->signcount = ClubStudentSubscribe::notDelete()->where('sales_id', $saleId)
                    ->whereHas('courseSign', function ($q) use($startTime, $endTime) {
                        $q->where('sign_status', 1)->whereBetween('sign_date', [$startTime, $endTime]);
                    })->count(); //体验数据
            }
            $items->onyuyue = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 2)
            );  //销售
            $items->onchange = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 3)
            );  //渠道
            $items->payAgain = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 0, 0 , 1)
            ); //续费
            $items->payCount = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2])
            );
            $items->payFee = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime)
            ); //销售额
            $items->payment = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 2, 0, 2)
            );  //销售自营课非续费
            $payFee1 = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1 ,2])->where('channel_type', '<>', 2)
            );  //自营课缴费且学员来源不是销售
            $payFee2 = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1 ,2], 0, 0, 2, 0, 1)
            );  //续费自营课缴费且学员来源是销售
            $payFee3 = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($saleId, $startTime, $endTime, 3)
            );  //封闭营缴费金额
            $items->otherPayment = $payFee1 + $payFee2 + $payFee3; //其他金额
        }
        return returnMessage('200', '请求成功',$sale);
    }

    /**
     * 销售图标统计
     * @param Request $request
     * @return array
     * @date 2018/9/29
     * @author jesse
     */
    public function salesPicStatistics(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'saleId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $saleId = isset($data['saleId']) ? $data['saleId'] : ''; //销售id
        $startTime = isset($data['startTime'])
            ? date('Y-m-d 00:00:00', strtotime($data['startTime']))
            : date('Y-m-d 00:00:00', time());
        $endTime = isset($data['endTime'])
            ? date('Y-m-d 23:59:59', strtotime($data['endTime']))
            : date('Y-m-d 23:59:59', time());
        $sale['salesFee'] = $this->getSalesPaymentPayFee(
            $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 2)
        );  //销售金额
        $sale['salesCourseCount'] =
            $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 2)->sum('course_count'); //销售课时
        $sale['channelFee'] = $this->getSalesPaymentPayFee(
            $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 3)
        );  //渠道费用
        $sale['channelCourseCount'] = $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 3)->sum('course_count'); //销售课时
        $sale['payAgainCourseCount'] = $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 0, 0 , 1)->sum('course_count'); //续费课时
        $sale['payAgainFee'] = $this->getSalesPaymentPayFee(
            $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1, 2], 0, 0, 0, 0 , 1)
        ); //续费费用
        $sale['selfPayFee'] = $this->getSalesPaymentPayFee(
            $this->getSalesPaymengtAll($saleId, $startTime, $endTime, [1 ,2])
        );; //自营课总费用
        $sale['closedPayFee'] = $this->getSalesPaymentPayFee(
            $this->getSalesPaymengtAll($saleId, $startTime, $endTime, 3)
        );  //封闭营缴费金额
        $sale['selfRefund'] = $this->refundType($saleId, $startTime, $endTime, [1, 2]);
        $sale['closedRefund'] = $this->refundType($saleId, $startTime, $endTime, [3]);
        return returnMessage('200', '请求成功',$sale);
    }

    //获取体验数据
    public function getsigncount($saleid,$startTime,$endTime){
        $sql = "SELECT count(*) as count 
FROM club_student_subscribe AS subscribe 
LEFT JOIN club_course_sign AS sign ON subscribe.sign_id = sign.id 
WHERE subscribe.is_delete=0 and  subscribe.sales_id = '".$saleid."'
AND sign.sign_status = '1' and subscribe.created_at >= '".$startTime."'
AND subscribe.created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //预约 销售 club_student_payment 销售=2;渠道=3;线上=4,5
    public function onyuyue($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and channel_type = '2' and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //渠道
    public function onchange($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and channel_type = '3' and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //线上
    public function online($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and channel_type in('2','3') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //销售额： club_student_payment   sales_dept_id
    public function payFee($depid,$startTime,$endTime){
        $sql = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        return $count;
    }

    //其他销售额：club_student_payment
    //channel_type=！2 & payment_class_type_id =1，2
    //channel_type=2 & payment_class_type_id =1，2 &  & is_pay_again =1
    //payment_class_type_id = 3
    public function otherPayment($depid,$startTime,$endTime){
        $sql1 = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and channel_type !=2 and payment_class_type_id in ('1','2') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res1 = DB::select($sql1);
        $sql2 = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and channel_type =2 and payment_class_type_id in ('1','2')and is_pay_again =1 and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res2 = DB::select($sql2);
        $sql3 = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$depid." and payment_class_type_id =3 and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res3 = DB::select($sql3);
        $res = $res1[0]->count+$res2[0]->count+$res3[0]->count;
        return $res;
    }

    //7.提成详情
    public function  salesExtract (Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $salesId = isset($data['salesId']) ? $data['salesId'] : '';            //部门id
        $year = isset($data['year']) ? $data['year'] : date('Y', time());
        $search = isset($data['search']) ? $data['search'] : '';
        $season = isset($data['season']) ? $data['season'] : '';

        $sale = DB::table('club_sales_performance_by_season_snapshot AS snapshot1')
            ->join('club_department AS department', 'snapshot1.target_id', '=', 'department.id')
            ->select(
                'snapshot1.*', 'department.name'
            )
            ->where('snapshot1.target_type', 2)->where('snapshot1.is_delete',0);

        if (strlen($year) > 0) {
            $sale->where('snapshot1.year', $year);
        }

        if (strlen($salesId) > 0) {
            $sale->where('snapshot1.target_id', $salesId);

        }
        if (strlen($search) > 0) {
            $sale->where('department.name', $search);

        }
        if (strlen($season) > 0) {
            $sale->where('snapshot1.season', $season);

        }

        $sale = $sale->get()->toArray();

        foreach ($sale as $item) {
            $item->performance = floatval($item->normal_class_online_performance+$item->normal_class_media_performance+
                $item->normal_class_continue_performance+$item->day_class_online_performance+
                $item->day_class_media_performance+$item->day_class_continue_performance+
                $item->closed_class_performance);

            $item->bonus =floatval($item->normal_class_online_bonus+$item->normal_class_media_bonus+
                $item->normal_class_continue_bonus+$item->day_class_online_bonus+
                $item->day_class_media_bonus+$item->day_class_continue_bonus+
                $item->closed_class_bonus);
            $item->pay = floatval($item->payment_sales_count+$item->payment_media_count+$item->payment_online_count);
            $total1 = floatval($item->normal_class_sales_no_continue_performance1 + $item->day_class_sales_no_continue_performance1);
            $total2 = floatval($item->normal_class_sales_no_continue_performance2 + $item->day_class_sales_no_continue_performance2);
            $item->total1 =floatval($total1);
            $item->total2 =floatval($total2);

            if ($total1 >0){
                $item->performance1Bili =(($total1/$item['target1'])*100)."%";
            }else{
                $item->performance1Bili = '0.0%';
            }
            if ($total2 >0){
                $item->performance2Bili =(($total2/$item['target2'])*100)."%";
            }else{
                $item->performance2Bili = '0.0%';
            }
        }
        return returnMessage('200', '请求成功', $sale);
    }

    //8.提成详情-详情
    public function  salesitems (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $salesId = isset($data['salesId']) ? $data['salesId'] : '';            //id
        $year = isset($data['year']) ? $data['year'] : '';
        $season = isset($data['season']) ? $data['season'] : '';

        $sale = DB::table('club_sales_performance_by_season_snapshot  AS snapshot1')
            ->join('club_department AS department','snapshot1.target_id','=','department.id')
            ->select(
                'snapshot1.*','department.name'
            )
            ->where('snapshot1.target_type',2)->where('snapshot1.is_delete',0);

        if(strlen($year)>0){
            $sale->where('snapshot1.year',$year);

        }
        if(strlen($salesId)>0){
            $sale->where('snapshot1.target_id',$salesId);

        }
        if(strlen($season)>0){
            $sale->where('snapshot1.season',$season);

        }
        $sale = $sale->get()->toArray();


        $sale2 = DB::table('club_sales_performance_by_season_snapshot AS snapshot1')
            ->join('club_department AS department','snapshot1.target_id','=','department.id')
            ->select(
                'snapshot1.target1','snapshot1.target2','snapshot1.to_bind_app','snapshot1.to_sign','snapshot1.year','snapshot1.season'
                ,'department.name','snapshot1.id'
            )
            ->where('target_type',2)->where('snapshot1.is_delete',0);

        if(strlen($year)>0){
            $sale2->where('snapshot1.year',int($year)-1);

        }
        if(strlen($salesId)>0){
            $sale2->where('snapshot1.target_id',$salesId);

        }
        if(strlen($season)>0){
            $sale2->where('snapshot1.season',$season);

        }
        $sale2 = $sale2->get()->toArray();
        $res["toYear"] = $sale;
        $res["yeYear"] = $sale2;
        return returnMessage('200', '请求成功', $res);
    }

    //9.销售管理-用户-获取海报
    public function  salesBill (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';            //销售id
        $sale = DB::table('club_sales AS sales')
            ->join('club_club_qrcode_image AS image','sales.club_id','=','image.club_id')
            ->select(
                'image.file_path','sales.sales_name')
            ->where('sales.id',$salesId)
            ->where('sales.is_delete',0)
            ->get();
        return returnMessage('200', '请求成功', $sale);
    }

    /**
     * 销售管理-用户-图表
     * @param object $request
     * @return array
     * @date 2018/9/26
     * @author edit by jesse
     */
    public function salesChat(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'type' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $salesId = isset($data['salesId']) ? $data['salesId'] : ''; //销售id
        $type = isset($data['type']) ? $data['type'] : ""; //1 按照周统计  2.按照月统计
        $year = isset($data['year']) ? $data['year'] : date('Y');
        $season = isset($data['season']) ? $data['season'] : ceil((date('n'))/3);
        if($type == 1){
            $dataarr = $this->gettimeweek($season,$year);
            $dataarr2 = $this->gettimeweek($season, intval($year)-1);
        }else{
            $dataarr = $this->getAllMoth($season,$year);
            $dataarr2 = $this->getAllMoth($season, intval($year)-1);
        }
        $result = array();
        foreach ($dataarr as $item) {
            $startTime = $item["beginTime"];
            $endTime = $item["endTimme"];
            $salespaymentAll = $this->getSalesPaymengtAll($salesId, $startTime, $endTime);
            $item["payFee"] = $this->getSalesPaymentPayFee($salespaymentAll);
            $item["onyuyue"] = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, '', 0, 0, 2)
            );  //销售
            $item["onchange"] = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, '', 0, 0, 3)
            ); //渠道
            $result["toYear"][] = $item;
        }
        foreach ($dataarr2 as $item2){
            $startTime = $item2["beginTime"];
            $endTime = $item2["endTimme"];
            $salespaymentAll = $this->getSalesPaymengtAll($salesId, $startTime, $endTime);
            $item["payFee"] = $this->getSalesPaymentPayFee($salespaymentAll);
            $item["onyuyue"] = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, '', 0, 0, 2)
            );  //销售
            $item["onchange"] = $this->getSalesPaymentPayFee(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, '', 0, 0, 3)
            ); //渠道
            $result["yeYear"][] = $item2;
        }
        return returnMessage('200', '请求成功', $result);
    }

    /**
     * 销售管理-销售概况--统计列表
     * @param object $request
     * @return array
     * @date 2018/9/25
     * @author edit by jesse
     */
    public function salesCount(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'type' => 'required|numeric',   //1 按周统计 2 按月统计
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';            //销售id
        $type = isset($data['type']) ? $data['type'] : "";
        $year = isset($data['year']) ? $data['year'] : date('Y');
        $season = isset($data['season']) ? $data['season'] : ceil((date('n'))/3);
        if ($type == 1) {
            $dataarr = $this->gettimeweek($season, $year);
        } else {
            $dataarr = $this->getAllMoth($season, $year);
        }
        $result = array();
        foreach ($dataarr as $item) {
            $startTime = $item["beginTime"];
            $endTime = $item["endTimme"];
            $item["beginTime"] = date('Y-m-d', strtotime($item["beginTime"]));
            $item["endTimme"] = date('Y-m-d', strtotime($item["endTimme"]));
            //总销售额 包含所有课程
            $salespaymentAll = $this->getSalesPaymengtAll($salesId, $startTime, $endTime);
            $allPayFee = $this->getSalesPaymentPayFee($salespaymentAll);
            //来自app付费 测试环境无字段，先注释
            $salespaymentAppPay = $this->getSalesPaymengtAll($salesId, $startTime, $endTime, [1, 2, 3], 1);
            $appPayFee = $this->getSalesPaymentPayFee($salespaymentAppPay);
            $appPayCount = $this->getSalesPaymentCount($salespaymentAppPay);
            //待签约
            $waitCount = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, '', 0 , 1)
            );
            //自营课信息
            $salespaymentZy = $this->getSalesPaymengtAll($salesId, $startTime, $endTime, [1, 2]);
            $zyPayFee = $this->getSalesPaymentPayFee($salespaymentZy);
            $zyPayCount = $this->getSalesPaymentCount($salespaymentZy);
            $zyPrice = $zyPayCount === 0 ? 0 : number_format($zyPayFee / $zyPayCount, 2);
            $zySaleCount = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, [1, 2], 0, 0, 2)
            );  //销售
            $zySourceCount = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, [1, 2], 0, 0, 3)
            ); //渠道
            $zyFreeCount = $this->getSalesPaymentCount(
                $this->getSalesPaymengtAll($salesId, $startTime, $endTime, [1, 2], 0, 0, 0, 2)
            ); //体验用户
            //封闭营信息
            $fbPayFee = $this->getSalesPaymentPayFee(
                $salespaymentZy = $this->getSalesPaymengtAll($salesId, $startTime, $endTime, 3)
            );
            $item["salespayment"] = $zyPayFee;
            $item["saleprice"] = $zyPrice;
            $item["salesfenbi"] = $fbPayFee;
            $item["saleszifei"] = $zyPayCount;
            $item["appPaySum"] = $appPayFee;
            $item["appPayCount"] = $appPayCount;
            $item["salesqianyue"] = $waitCount;
            $item["refund"] = $this->refund($salesId, $startTime, $endTime);
            $item["refundcount"] = $this->refundcount($salesId, $startTime, $endTime);
            $item["payFee"] = $allPayFee;
            $item["onyuyue"] = $zySaleCount;
            $item["onchange"] = $zySourceCount;
            $item["tiyan"] = $zyFreeCount;
            $result[] = $item;
        }
        if (strstr($request->path(), 'salesExport') !== false) {
            if (count($result) > 0) {
                $array = ['时间', '销售额', '自营课收入/单价', '封闭营收入',
                    '自营课付费（销售/渠道/线上）体验','来自APP付费','待签约','退款'];
                array_unshift($result, $array);
                $xlsName = 'club_list_ls' . time();
                Excel::create(iconv('UTF-8', 'GBK', $xlsName), function ($excel) use ($result) {
                    $excel->sheet('data', function ($sheet) use ($result) {
                        $sheet->rows($result);
                    });
                })->export('xls');
                return returnMessage('200', '导出成功');
            } else {
                return returnMessage('200', '暂无数据');
            }
        }
        return returnMessage('200', '请求成功', $result);
    }

    public function getSalesPaymengtAll(
        $depid,
        $startTime,
        $endTime,
        $pClassType = '',
        $isApp = 0,
        $isWait = 0,
        $channel_type = 0,
        $studentSatatus = 0,
        $isPayAgain = 0
    ) {
        $salesPayMent = $this->salespaymentQuery($depid, $startTime, $endTime);
        $salesPayMent = $this->pClassType($salesPayMent, $pClassType);
        if ($isApp) {
            $salesPayMent = $this->isAppPay($salesPayMent, $isApp);
        }
        if ($isWait) {
            $salesPayMent = $this->isWait($salesPayMent, $isWait);
        }
        if ($channel_type) {
            $salesPayMent = $this->salesSource($salesPayMent, $channel_type);
        }
        if ($studentSatatus) {
            $salesPayMent = $this->savourStudents($salesPayMent);
        }
        if ($isPayAgain) {
            $salesPayMent = $this->isPayAgain($salesPayMent, $isPayAgain);
        }
        return $salesPayMent;
    }

    /**
     * 销售计算销售额基础
     * @param $depid        int         销售编号
     * @param $startTime    string      开始时间
     * @param $endTime      string      结束时间
     * @return object
     * @date 2018/9/25
     * @author jesse
     */
    public function salespaymentQuery($depid, $startTime, $endTime) {
        $studentPayment = ClubStudentPayment::where(
            [
                ['is_delete', 0],
                ['sales_id', $depid],
                ['created_at', '>=', $startTime],
                ['created_at', '<=', $endTime]
            ]
        );
        return $studentPayment;
    }

    /**
     * 定义课程类型
     * @param $query
     * @param string|array $pClassType  1 常规班 2 走训班 3 封闭营
     * @return mixed
     * @date 2018/9/26
     * @author jesse
     */
    public function pClassType($query, $pClassType = '')
    {
        if (!empty($pClassType)) {
            if (is_array($pClassType)) {
                return $query->whereIn('payment_class_type_id', $pClassType);
            } else {
                return $query->where('payment_class_type_id', $pClassType);
            }
        }
        return $query;
    }

    /**
     * 是否app支付
     * @param $query    object
     * @param $isApp    int
     * @return mixed
     * @date 2018/9/25
     * @author jesse
     */
    public function isAppPay($query, $isApp)
    {
        return $query->where('is_app_pay', $isApp);
    }

    /**
     * 是否待签约
     * @param $query
     * @param $isWait   bool    是否待签约 true 是 false 否
     * @return mixed
     * @date 2018/9/25
     * @author jesse
     */
    public function isWait($query, $isWait)
    {
        $condition = $isWait ? '=' : '<>';
        return $query->where('contract_no', $condition, '');
    }

    /**
     * 是否续费
     * @param $query
     * @param $isWait   1 是续费 2 不是续费
     * @return mixed
     * @date 2018/9/26
     * @author jesse
     */
    public function isPayAgain($query, $isWait = 0)
    {
        if ($isWait === 1) {
            return $query->where('is_pay_again', 1);
        } elseif ($isWait === 2) {
            return $query->where('is_pay_again', 0);
        }
        return $query;
    }

    /**
     * 销售渠道来源
     * @param $query
     * @param int $channel_type
     * @return mixed
     * @date 2018/9/26
     * @author jesse
     */
    public function salesSource($query, $channel_type = 2)
    {
        return $query->where('channel_type', $channel_type);
    }

    /**
     * 体验的学生
     * @param $query
     * @param int $status
     * @return mixed
     * @date 2018/9/26
     * @author jesse
     */
    public function savourStudents($query, $status = 2)
    {
        return $query->whereHas('student', function ($q) use($status) {
            $q->where('status', $status)
                ->whereHas('signs', function ($sq){
                   $sq->where('sign_status', 1);
                });
        });
    }

    /**
     * 获取销售总金额
     * @param $query
     * @return mixed
     * @date 2018/9/25
     * @author jesse
     */
    public function getSalesPaymentPayFee($query){
        $payFee = $query->sum('pay_fee');
        return $payFee;
    }

    /**
     * 获取销售总次数
     * @param $query
     * @return mixed
     * @date 2018/9/25
     * @author jesse
     */
    public function getSalesPaymentCount($query){
        $count = $query->count();
        return $count;
    }

    //销售自营课：自营课  payment_class_type_id 1,2       /查询出来条数
    public function salespayment($depid,$startTime,$endTime)
    {
        $sql = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_id= " . $depid . " and payment_class_type_id in ('1','2') and  created_at >='" . $startTime . "' AND created_at <= '" . $endTime . "'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        return $count;
    }

    public function salespaymentcount($salesId,$startTime,$endTime){
        $sql = "SELECT count(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_id= ".$salesId." and payment_class_type_id in ('1','2') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = intval($res[0]->count);
        return $count;
    }

    //封闭营收入 payment_class_type_id =3
    public function salesfenbi($depid,$startTime,$endTime){
        $sql = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and  sales_id= ".$depid." and payment_class_type_id = 3 and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        return $count;
    }

    //自营课付费  count（ payment_class_type_id 1,2）
    public function saleszifei($depid,$startTime,$endTime){
        $sql = "SELECT count(pay_fee) as count from club_student_payment WHERE is_delete=0 and  sales_id= ".$depid." and payment_class_type_id in ('1','2') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = intval($res[0]->count);
        return $count;
    }
    //channel_type  销售=2;渠道=3;线上=4，5

    //来自app付费  channel_type  sum(pay_fee)  count(条数)
    public function appPaySum($depid,$startTime,$endTime){
        $sql = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and  sales_id= ".$depid." and payment_class_type_id in ('1','2') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        return $count;
    }
    public function appPayCount($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and  sales_id= ".$depid." and payment_class_type_id in ('1','2') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = intval($res[0]->count);
        return $count;
    }
    //contract_no =‘’ 待签约
    public function salesqianyue($depid,$startTime,$endTime){
        $sql = "SELECT count(pay_fee) as count from club_student_payment WHERE is_delete=0 and  sales_id= ".$depid." and contract_no ='' and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = intval($res[0]->count);
        return $count;
    }
    //体验
    public function tiyan($depid,$startTime,$endTime){
        $sql = "SELECT count(id) as count from club_student_subscribe WHERE is_delete=0 and  sales_id= ".$depid." and subscribe_status = 1 and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = intval($res[0]->count);
        return $count;
    }
    //退款  club_student_refund   refund_money
    public function refund($depid,$startTime,$endTime){
        $sql = "SELECT SUM(refund_money) AS count FROM club_student_refund AS refund LEFT JOIN club_student_payment As Payment on Payment.id = refund.student_payment_id WHERE refund.is_delete=0 and  Payment.sales_id= ".$depid."  and  refund.created_at >='".$startTime."' AND refund.created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        return $count;
    }


    public function refundType($depid,$startTime,$endTime, $type){
        $type = implode(',', $type);
        $sql = "SELECT SUM(refund_money) 
                  AS count FROM club_student_refund AS refund 
                  LEFT JOIN club_student_payment As Payment on Payment.id = refund.student_payment_id 
                  WHERE refund.is_delete=0 and  Payment.sales_id= ".$depid."  
                  and  refund.created_at >='".$startTime."' and Payment.payment_class_type_id in ($type)
                  AND refund.created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        return $count;
    }

    public function refundcount($depid,$startTime,$endTime){
        $sql = "SELECT count(*) AS count FROM club_student_refund AS refund LEFT JOIN club_student_payment As Payment on Payment.id = refund.student_payment_id WHERE refund.is_delete=0 and  Payment.sales_id= ".$depid."  and  refund.created_at >='".$startTime."' AND refund.created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = intval($res[0]->count);
        return $count;
    }

    //12.销售管理-用户--导出
    public function  salesExport (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'type' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';            //销售id
        $type = isset($data['type']) ? $data['type'] : "";
        $year = isset($data['year']) ? $data['year'] : date('Y');
        $season = isset($data['season']) ? $data['season'] : ceil((date('n'))/3);
        $clubId = isset($data['clubId']) ? $data['clubId'] : '';

        if($type == 1){
            $dataarr = $this->gettimeweek($season,$year);
        }else{
            $dataarr = $this->getAllMoth($season,$year);
        }
        $result = array();
        foreach ($dataarr as $item){
            $startTime = $item["beginTime"];
            $endTime = $item["endTimme"];
            $salespayment =$this->salespayment($salesId,$startTime,$endTime);
            $salespaymentcount =$this->salespaymentcount($salesId,$startTime,$endTime);
            if($salespayment>0){
                $saleprice = number_format($salespayment/$salespaymentcount,2);
            }else{
                $saleprice = 0;
            }

            $item["salespayment"] = $salespayment;
            $item["saleprice"] = $saleprice;
            $item["salesfenbi"] = $this->salesfenbi($salesId,$startTime,$endTime);
            $item["saleszifei"] = $this->saleszifei($salesId,$startTime,$endTime);
            $item["appPaySum"] = $this->appPaySum($salesId,$startTime,$endTime);
            $item["appPayCount"] = $this->appPayCount($salesId,$startTime,$endTime);

            $item["salesqianyue"] = $this->salesqianyue($salesId,$startTime,$endTime);
            $item["refund"] = $this->refund($salesId,$startTime,$endTime);
            $item["refundcount"] = $this->refundcount($salesId,$startTime,$endTime);

            $item["payFee"] = $this->payFee($salesId,$startTime,$endTime);
            $item["onyuyue"] = $this->onyuyue($salesId,$startTime,$endTime);
            $item["onchange"] = $this->onchange($salesId,$startTime,$endTime);
            $item["online"] = $this->online($salesId,$startTime,$endTime);
            $item["tiyan"] = $this->tiyan($salesId,$startTime,$endTime);


            $result[] = $item;
        }

        $resArray = array();
        if(count($result) > 0){
            foreach ($result as $key => $item) {
                $resArray[$key] = array(
                    $item["beginTime"].'-'.$item["endTimme"],
                    $item["payFee"],
                    $item["salespayment"].'/'.$item["saleprice"],
                    $item["salesfenbi"],
                    $item["saleszifei"].'('.$item["onyuyue"].'/'. $item["onchange"].'/'.$item["online"].')'.$item["tiyan"],
                    $item["appPaySum"].'-'.$item["appPayCount"],
                    $item["salesqianyue"],
                    $item["refund"].'-'.$item["refundcount"],
                );
            }
            $array = array('时间', '销售额', '自营课收入/单价', '封闭营收入', '自营课付费（销售/渠道/线上）体验','来自APP付费','待签约','退款');
            array_unshift($resArray, $array);
        }else{
            return returnMessage('200', '暂无数据');
        }

        // 有数据
        if (count($resArray)>0) {
            $xlsName = 'club_list_ls'.time();

            Excel::create(iconv('UTF-8', 'GBK', $xlsName),function ($excel) use ($resArray){
                $excel->sheet('data',function ($sheet) use ($resArray){
                    $sheet->rows($resArray);
                });
            })->export('xls');

            return returnMessage('200', '导出成功');

        }else{
            return returnMessage('200', '暂无数据');
        }
    }

    //13.1销售管理-用户--缴费记录-报表
    public function salesClassChat(Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'startTime' => 'required|date',
            'endTime' => 'required|date',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $salesId = $data['salesId'];
        $startTime = isset($data['startTime']) ? $data['startTime'] :"";
        $endTime= isset($data['endTime']) ? $data['endTime'] :"";

        $result = array();
        $result["sales"] = $this->chatsales($salesId,$startTime,$endTime);
        $result["channel"] = $this->chatchannel($salesId,$startTime,$endTime);
        $result["payAgain"] =  $this->chatpayAgain($salesId,$startTime,$endTime);

        $ziyingke =  $this->ziyingke($salesId,$startTime,$endTime);
        $fenbiying =  $this->fenbiying($salesId,$startTime,$endTime);

        $result["count"] =  array("ziyingke"=>$ziyingke["count"],"fenbiying"=>$fenbiying["count"]);
        $result["num"] =  array("ziyingke"=>$ziyingke["num"],"fenbiying"=>$fenbiying["num"]);

        return returnMessage('200', '请求成功', $result);
    }

    public function chatsales($salesId,$startTime,$endTime){
        $array = array("count"=>0,"num"=>0);
        $sql = "SELECT SUM(pay_fee) as count,count(id) AS num from club_student_payment  WHERE is_delete=0 and sales_id='".$salesId."' and channel_type = 2 and created_at >='".$startTime."'AND created_at <'".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        $num = $res[0]->num;
        $array = array("count"=>$count,"num"=>$num);
        return $array;

    }

    public function chatchannel($salesId,$startTime,$endTime){
        $array = array("count"=>0,"num"=>0);
        $sql = "SELECT SUM(pay_fee) as count,count(id) AS num from club_student_payment  WHERE  is_delete=0 and sales_id='".$salesId."' and channel_type = 3 and created_at >='".$startTime."'AND created_at <'".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        $num = $res[0]->num;
        $array = array("count"=>$count,"num"=>$num);
        return $array;
    }

    public function chatpayAgain($salesId,$startTime,$endTime){
        $array = array("count"=>0,"num"=>0);
        $sql = "SELECT SUM(pay_fee) as count,count(id) AS num from club_student_payment  WHERE  is_delete=0 and sales_id='".$salesId."' and is_pay_again = 1 and created_at >='".$startTime."'AND created_at <'".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        $num = $res[0]->num;
        $array = array("count"=>$count,"num"=>$num);
        return $array;
    }

    public function fenbiying($salesId,$startTime,$endTime){
        $array = array("count"=>0,"num"=>0);
        $sql = "SELECT SUM(refund.refund_money) as count,count(payment.id) AS num from club_student_payment as payment left join club_student_refund as refund ON refund.student_payment_id= payment.id WHERE payment.sales_id='".$salesId."' and payment.payment_class_type_id ='3' and payment.created_at >='".$startTime."'AND payment.created_at <'".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        $num = intval($res[0]->num);
        $array = array("count"=>$count,"num"=>$num);
        return $array;
    }

    public function ziyingke($salesId,$startTime,$endTime){
        $array = array("count"=>0,"num"=>0);
        $sql = "SELECT SUM(refund.refund_money) as count,count(payment.id) AS num from club_student_payment as payment left join club_student_refund as refund ON refund.student_payment_id= payment.id WHERE  payment.is_delete=0 and payment.sales_id='".$salesId."' and payment.payment_class_type_id in('1','2') and payment.created_at >='".$startTime."'AND payment.created_at <'".$endTime."'";
        $res = DB::select($sql);
        $count = floatval($res[0]->count);
        $num = intval($res[0]->num);
        $array = array("count"=>$count,"num"=>$num);
        return $array;
    }

    //13.销售管理-用户--缴费记录
    public function  salesloglist (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
            'startTime' => 'required|date',
            'endTime' => 'required|date',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';
        $channel_type = isset($data['channelType']) ? $data['channelType'] : ''; //销售=2;渠道=3;线上=4，5',
        $payment_tag_id = isset($data['paymentTagId']) ? $data['paymentTagId'] :"";
        $startTime = isset($data['startTime']) ? $data['startTime'] :"";
        $endTime= isset($data['endTime']) ? $data['endTime'] :"";

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $sale = DB::table('club_student_payment AS Payment')
            ->join('club_payment AS tag','tag.id','=','Payment.payment_id')
            ->join('club_student AS Student','Student.id','=','Payment.student_id')
            ->join('club_class AS class','Student.main_class_id','=','class.id')
            ->join('club_sales AS sales','Payment.sales_id','=','sales.id')
            ->join('club_channel AS channel', 'Payment.channel_type','=','channel.id')
            ->select(
                'Payment.id','Payment.payment_name as paymentName',
                'Payment.payment_tag_id as paymentTagId','Payment.pay_fee as payFee',
                'Payment.remark','Payment.payment_date as paymentDate',
                'Payment.channel_type as channelType', 'Payment.contract_no',
                'Payment.updated_at as updateAt','Payment.expire_date as expireDate',
                'Student.name as studentName','Student.id as studentId','class.name',
                'sales.sales_name as salesName','tag.original_price as originalPrice',
                'channel.channel_name',DB::raw('tag.original_price - Payment.pay_fee as packageFee')
            )
            ->where('Payment.sales_id',$salesId)
            ->where('Payment.created_at', '>=',$startTime)
            ->where('Payment.created_at', '<',$endTime)
            ->where('Payment.is_delete',0);


        if(strlen($channel_type)>0){
            $sale->where('Payment.channel_type',$channel_type);
        }

        if(strlen($payment_tag_id)>0){
            $sale->where('Payment.payment_tag_id',$payment_tag_id);
        }

        $sale = $sale->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $sale);
    }

    //13.1销售管理-用户--缴费记录导出
    public function  salesloglistexport (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'startTime' => 'required|date',
            'endTime' => 'required|date',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $salesId = isset($data['salesId']) ? $data['salesId'] : '';
        $channel_type = isset($data['channelType']) ? $data['channelType'] : ''; //销售=2;渠道=3;线上=4，5',
        $payment_tag_id = isset($data['paymentTagId']) ? $data['paymentTagId'] :"";
        $startTime = isset($data['startTime']) ? $data['startTime'] :"";
        $endTime= isset($data['endTime']) ? $data['endTime'] :"";
        $clubId = isset($data['clubId']) ? $data['clubId'] : '';


        $sale = DB::table('club_student_payment AS Payment')
            ->join('club_payment AS tag','tag.id','=','Payment.payment_id')
            ->join('club_student AS Student','Student.id','=','Payment.student_id')
            ->join('club_class AS class','Student.main_class_id','=','class.id')
            ->join('club_sales AS sales','Payment.sales_id','=','sales.id')
            ->select(
                'Payment.id','Payment.payment_name as paymentName','Payment.payment_tag_id as paymentTagId','Payment.pay_fee as payFee','Payment.remark','Payment.payment_date as paymentDate','Payment.channel_type as channelType',
                'Payment.contract_no','Payment.updated_at as updateAt','Payment.expire_date as expireDate','Student.name as studentName','Student.id as studentId','class.name','sales.sales_name as salesName','tag.original_price as originalPrice'
            )
            ->where('Payment.sales_id',$salesId)
            ->where('Payment.created_at', '>=',$startTime)
            ->where('Payment.created_at', '<',$endTime)
            ->where('Payment.is_delete',0);

        if(strlen($channel_type)>0){
            $sale->where('Payment.channel_type',$channel_type);
        }

        if(strlen($payment_tag_id)>0){
            $sale->where('Payment.payment_tag_id',$payment_tag_id);
        }

        $sale = $sale->get();
        //导出数据
        $resArray = array();
        if(count($sale) > 0){
            foreach ($sale as $key => $item) {
                if(strlen($item->contract_no)==0){
                    $contract = "待签";
                }else{
                    $contract = "已签";
                }
                $resArray[$key] = array(
                    $item->id,
                    $item->studentId.'.'.$item->studentName,
                    $item->salesName,
                    $item->name,
                    $item->channelType,

                    $item->paymentName,
                    $item->originalPrice,
                    $item->originalPrice-$item->payFee,
                    $item->payFee,
                    $contract,
                    $item->paymentDate,
                    $item->expireDate,
                    $item->updateAt,
                    $item->remark,
                );
            }
            $array = array('缴费号', '学员', '销售员', '班级', '来源(销售=2;渠道=3;线上=4，5)', '方案','价格','优惠折扣','实际付费','合同','缴费时间','过期时间','操作时间','备注');
            array_unshift($resArray, $array);
        }else{
            return returnMessage('200', '暂无数据');
        }

        // 有数据
        if (count($resArray)>0) {
            $xlsName = 'club_list_ls'.time();
            Excel::create(iconv('UTF-8', 'GBK', $xlsName), function ($excel) use ($resArray){
                $excel->sheet('data',function ($sheet) use ($resArray){
                    $sheet->rows($resArray);
                });
            })->export('xls');
            return returnMessage('200', '导出成功');
        }else{
            return returnMessage('200', '暂无数据');
        }
    }

    //14.销售管理-用户--退款记录
    public function  refundlist (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
            'startTime' => 'required|date',
            'endTime' => 'required|date',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $refundNum = isset($data['refundNum']) ? $data['refundNum'] : '';
        $name = isset($data['name']) ? $data['name'] : '';
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] :"";
        $endTime= isset($data['endTime']) ? $data['endTime'] :"";

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $sale = DB::table('club_student_refund AS refund')
            ->join('club_student_payment AS Payment','refund.student_payment_id','=','Payment.id')
            ->join('club_student AS Student','Student.id','=','refund.student_id')
            ->join('club_sales AS sales','sales.id','=','refund.sales_id')
            ->select(
                'refund.id','refund.student_payment_id as studentPaymentId','Payment.pay_fee as payFee','Student.name as studentName','sales.sales_name as salesName','Payment.payment_name as panmentName','refund.refund_money as refundMoney','refund.refund_operation_sales_id','refund.remark','refund.refund_date as refundDate'
            )
            ->where('refund.sales_id',$salesId)
            ->where('refund.created_at', '>=',$startTime)
            ->where('refund.created_at', '<',$endTime)
            ->where('refund.is_delete',0);
        if(strlen($name)>0){
            $sale->where('Student.name','like','%'.$name.'%');

        }

        if(strlen($refundNum)>0){
            $sale->where('refund.id',$refundNum);
        }

        $sale = $sale->paginate($pagePerNum);

        $sale2 = DB::table('club_student_refund AS refund')
            ->join('club_student_payment AS Payment','refund.student_payment_id','=','Payment.id')
            ->join('club_student AS Student','Student.id','=','refund.student_id')
            ->join('club_sales AS sales','sales.id','=','refund.sales_id')
            ->select(
                'refund.id','refund.student_payment_id as studentPaymentId','Payment.pay_fee as payFee','Student.name as studentName','sales.sales_name as salesName','Payment.payment_name as panmentName','refund.refund_money as refundMoney','refund.refund_operation_sales_id','refund.remark','refund.refund_date as refundDate'
            )
            ->where('refund.sales_id',$salesId)
            ->where('refund.created_at', '>=',$startTime)
            ->where('refund.created_at', '<',$endTime)
            ->where('refund.is_delete', 0);
        if(strlen($name)>0){
            $sale2->where('Student.name','like','%'.$name.'%');

        }

        if(strlen($refundNum)>0){
            $sale2->where('refund.id',$refundNum);
        }

        $mycount = $sale2->count();


        $result = array();
        $result['total'] = $mycount;
        $result['data'] = $sale->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'studentPaymentId' => $item->studentPaymentId,
                'payFee' => $item->payFee,
                'studentName' => $item->studentName,
                'salesName' => $item->salesName,
                'paymentName' => $item->panmentName,
                'refundMoney' => $item->refundMoney,
                'refund_operation_sales_id' => $item->refund_operation_sales_id,
                'operationName' => ClubSales::where('id',$item->refund_operation_sales_id)->value('sales_name'),
                'remark' => $item->remark,
                'refundDate' => $item->refundDate
            ];

            return $result;
        });



        return returnMessage('200', '请求成功', $result);
    }

    //15.销售管理-用户--退款记录--导出
    public function  refundlistexport (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'salesId' => 'required|numeric',
            'startTime' => 'required|date',
            'endTime' => 'required|date',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $clubId = isset($data['clubId']) ? $data['clubId'] : '';
        $refundNum = isset($data['refundNum']) ? $data['refundNum'] : '';
        $name = isset($data['name']) ? $data['name'] : '';
        $salesId = isset($data['salesId']) ? $data['salesId'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] :"";
        $endTime= isset($data['endTime']) ? $data['endTime'] :"";

        $sale = DB::table('club_student_refund AS refund')
            ->join('club_student_payment AS Payment','refund.student_payment_id','=','Payment.id')
            ->join('club_student AS Student','Student.id','=','refund.student_id')
            ->join('club_sales AS sales','sales.id','=','refund.sales_id')
            ->select(
                'refund.id','refund.student_payment_id as studentPaymentId','Student.name as studentName','sales.sales_name as salesName','Payment.payment_name as paymentName','refund.refund_money as refundMoney','refund.refund_operation_sales_id','refund.remark'
            )
            ->where('refund.sales_id',$salesId)
            ->where('refund.created_at', '>=',$startTime)
            ->where('refund.created_at', '<',$endTime)
            ->where('refund.is_delete', 0);
        if(strlen($name)>0){
            $sale->where('Student.name','like','%'.$name.'%');
        }

        if(strlen($refundNum)>0){
            $sale->where('refund.id',$refundNum);
        }
        $sale = $sale->get();

        //导出数据
        $resArray = array();
        if(count($sale) > 0){
            foreach ($sale as $key => $item) {
                $resArray[$key] = array(
                    $item->id,
                    $item->studentPaymentId,
                    $item->studentName,
                    $item->salesName,
                    $item->panmentName,
                    0,
                    $item->refundMoney,
                    $item->refund_operation_sales_id,
                    $item->remark,
                );
            }
            $array = array('编号', '缴费号', '学员', '销售员', '缴费计划', '价格','退款金额','操作者','备注');
            array_unshift($resArray, $array);
        }else{
            return returnMessage('200', '暂无数据');
        }

        // 有数据
        if (count($resArray)>0) {
            $xlsName = 'club_list_ls'.time();

            Excel::create(iconv('UTF-8', 'GBK', $xlsName),function ($excel) use ($resArray){
                $excel->sheet('data',function ($sheet) use ($resArray){
                    $sheet->rows($resArray);
                });
            })->export('xls');

            return returnMessage('200', '导出成功');

        }else{
            return returnMessage('200', '暂无数据');
        }
    }

    //获取所有月
    public function getAllMoth($key,$year){
        $session = array(1=>array(1,2,3),2=>array(4,5,6),3=>array(7,8,9),4=>array(10,11,12));
        $mon = $session[$key];
        $mon_arr =array();
        foreach($mon as $items){
            $item["beginTime"]= date("Y-m-d H:i:s",mktime(0, 0 , 0,$items,1,date("Y")));
            $item["endTimme"] = date("Y-m-d H:i:s",mktime(23,59,59,$items+1 ,0,date("Y")));
            $mon_arr[] = $item;
        }
        return $mon_arr;
    }

    //获取所有周
    public function gettimeweek($key,$year){
        $weekarr =array();
        $start = ($key-1)*2+$key;
        $end = ($key-1)*2+$key+2;

        $begindate = mktime(0, 0 , 0,$start,1,date("Y"));
        $enddate = mktime(23,59,59,$end+1 ,0,date("Y"));
        // 季度开始时间
        $beginTime= date("Y-m-d H:i:s",$begindate);
        $endTimme = date("Y-m-d H:i:s",$enddate);

        $lastday=date("Y-m-d H:i:s",strtotime("$beginTime Sunday"));
        // echo('输入的时间星期第一天是：'.
        $firstweekstart = date("Y-m-d H:i:s",strtotime("$lastday - 6 days"));
        // echo('输入的时间星期最后一天是：'.
        $endlastday = date("Y-m-d H:i:s",strtotime($lastday)+86399);
        $weekarr[] = array("beginTime"=>$beginTime,"endTimme"=>$endlastday);
        for($i=1;$i<20;$i++){
            $begindatefor = date("Y-m-d H:i:s",($begindate + $i*7*86400));
            $lastday=date("Y-m-d H:i:s",strtotime("$begindatefor Sunday"));
            // echo('输入的时间星期第一天是：'.
            $weekstart = date("Y-m-d H:i:s",strtotime("$lastday - 6 days"));

            if($lastday <= $endTimme) {
                $getlastday = date("Y-m-d H:i:s",strtotime($lastday)+86399);
                if($getlastday>=$weekstart){
                    $weekarr[] = array("beginTime"=>$weekstart,"endTimme"=>$getlastday);
                }

            }else{
                $getlastday = date("Y-m-d H:i:s",strtotime($lastday)+86399);
                if($endTimme>=$weekstart) {
                    $weekarr[] = array("beginTime" => $weekstart, "endTimme" => $endTimme);
                }
                break;
            }
        }
        return $weekarr;
    }
}
