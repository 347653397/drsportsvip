<?php
namespace App\Api\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Model\ClubRole\ClubRole;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubDepartment\ClubDepartment;
use App\Model\ClubSales\ClubSalesPerformanceBySeasonSnapshot;
use App\Model\ClubSales\ClubSalesPerformanceBySeasonSnashot;
use App\Model\ClubSalesPerformanceByDay\ClubSalesPerformanceByDay;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubUser\ClubUser;
use App\Model\Venue\Venue;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;


class SalesTreamController extends Controller
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

    //1.销售团队管理列表-查询
    public function  treamList (Request $request){
        $data = $request->all();
        $year = isset($data['year']) ? $data['year'] : "";
        $season = isset($data['season']) ? $data['season'] : '';
        $sale = DB::table('club_department AS department')
            ->select(
                'snapshot1.target1','snapshot1.target2','snapshot1.to_bind_app','snapshot1.to_sign','snapshot1.year','snapshot1.season','snapshot1.id as sid'
                ,'department.name','snapshot1.id','department.id AS departmentId','department.parent_id AS parentId','department.principal_id as departmentUser'
            )
            ->join('club_sales_performance_by_season_snapshot as snapshot1',function($join){
                $join->on('snapshot1.target_id','=','department.id')
                    ->where('snapshot1.target_type','=',1)
                    ->where('department.type','=',2);
            }, null,null,'left');

        if(strlen($year)>0){
            $sale->where('snapshot1.year',$year);
        }

        if(strlen($season)>0){
            $sale->where('snapshot1.season',$season);
        }
        $sale = $sale->where('department.club_id',$this->user->club_id)->where('department.type',2)->where('department.is_delete',0)->get();

        $dep_id = $this->user->dept_id;
        $role_id = $this->user->role_id;
        $type = ClubRole::where('id',$role_id)->value('type');

        //是否是销售
        if($type == 2){
            $isSales = 1;
        }else{
            $isSales = 0;
        }
        //如果是部门负责人，可以修改指标

        $result = array();
        $result['total'] = count($sale);
        $result['data'] = $sale->transform(function ($item) {
            $ids = $item->departmentUser;
            $userid = $this->user->id;
            $self = 0;
            if (strlen(trim($ids))>0){
                $idarr = explode($ids,',');
                if(in_array($userid,$idarr)){
                    $self = 1;
                }
            }
            $result = [
                'target1' => $item->target1,
                'target2' => $item->target2,
                'to_bind_app' => $item->to_bind_app,
                'to_sign' => $item->to_sign,
                'year' => $item->year,
                'season' => $item->season,
                'sid' => $item->sid,
                'name' => $item->name,
                'id' => $item->id,
                'departmentId' => $item->departmentId,
                'parentId' => $item->parentId,
                'teamLeader'=> $this->getTreamLoader($item->departmentUser),
                'text'=> $this->getFirstName($item->parentId),
                'self'=>$self
            ];
            return $result;
        });

        $tree = array();
        foreach($sale as $item){
            $item["isSales"] = $isSales;
            $tree[$item['departmentId']] = $item;
        }

        $res = $this->getgenerateTree($tree);
        return returnMessage('200', '请求成功', $res);
    }

//    部门负责人
    function getTreamLoader($ids){
        $arr = explode(',',$ids);
        $idstr = join("','",$arr);
        $sql = "SELECT username FROM club_user WHERE id in ('".$idstr."')";
        $res = DB::select($sql);
        $loaderName = '';
        foreach ($res as $items){
            $loaderName.=$items->username;
        }
        return $loaderName;
    }
// 上级部门
    function getFirstName($id){
        $res = ClubDepartment::where('id',$id)->value('name');
        if(strlen($res)==0){
            $res ="#";
        }
        return $res;
    }

    function getgenerateTree($items){
        $tree = array();
        foreach($items as $item){
            if(isset($items[$item['parentId']])){
                $items[$item['parentId']]['children'][] = &$items[$item['departmentId']];
            }else{
                $tree[] = &$items[$item['departmentId']];
            }
        }
        return $tree;
    }

    //2.销售团队修改显示数据
    public function  treamEdit (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'snapshotId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $res = DB::table('club_sales_performance_by_season_snapshot AS snapshot1')
            ->join('club_department AS department','snapshot1.target_id','=','department.id')
            ->select(
                'snapshot1.target1','snapshot1.id','snapshot1.target2','snapshot1.to_bind_app','snapshot1.to_sign','snapshot1.year','snapshot1.season'
                ,'department.name'
            )
            ->where('snapshot1.id',$input['snapshotId'])
            ->get();
        return returnMessage('200', '',$res);
    }

    //3.销售团队管理修改操作
    public function  treamEditAction (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'snapshotId' => 'required|numeric',
            'target2' => 'required|numeric',
            'target1' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $field = ClubSalesPerformanceBySeasonSnashot::find($input['snapshotId']);
        if(!empty($field)){
            $field->target2 = $input["target2"];
            $field->target1 = $input["target1"];
            $field->update();
            return returnMessage('200', '修改成功');
        }else{
            return returnMessage('200', '修改失败');
        }
    }

    //4.1业绩销售额详情
    public function  treamRecord (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'depId' => 'required|numeric',
            'type' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $depId = isset($data['depId']) ? $data['depId'] : '';            //部门id
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
        if(strlen($startTime)==0 || strlen($endTime)==0){
            return returnMessage('1802', config('error.sales.1802'));
        }
        if ( $startTime >= $endTime) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $depId = $this->isParentGroup($depId);
        $salesStr = $this->getdepAllSalts($depId);

        if(strlen($salesStr)>0){
            $items["studentcount"] = $this->getstudentcount($salesStr,$startTime,$endTime);  //获取推广数据
            $items["subscribecount"] = $this->getsubscribecount($salesStr,$startTime,$endTime); //预约数据
            $items["signcount"] = $this->getsigncount($salesStr,$startTime,$endTime);   //体验数据
        }else{
            $items["studentcount"] = 0;  //获取推广数据
            $items["subscribecount"] =0;
            $items["signcount"] = 0;
        }

        if (is_array($depId)) {
            //TODO::时间原因暂时调整 后期整合
            $allCount = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                ]
            )->whereIn('sales_dept_id', $depId)->count();
            $onyuyue = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['channel_type', 2]
                ]
            )->whereIn('sales_dept_id', $depId)->count();
            $onchange = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['channel_type', 3]
                ]
            )->whereIn('sales_dept_id', $depId)->count();
            $online = 0;
            $payAgain = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['is_pay_again', 1]
                ]
            )->whereIn('sales_dept_id', $depId)->count();
            $payFee = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                ]
            )->whereIn('sales_dept_id', $depId)->sum('pay_fee');
            $selfPayFee = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['channel_type', 2]
                ]
            )->whereIn('payment_class_type_id', [1, 2])
                ->whereIn('sales_dept_id', $depId)->sum('pay_fee');
            $otherFee1 = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['channel_type', '<>', 2],
                ]
            )->whereIn('payment_class_type_id', [1, 2])
                ->whereIn('sales_dept_id', $depId)->sum('pay_fee');
            $otherFee2 = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['channel_type', 2],
                    ['is_pay_again', 1]
                ]
            )->whereIn('payment_class_type_id', [1, 2])
                ->whereIn('sales_dept_id', $depId)->sum('pay_fee');
            $otherFee3 = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                    ['payment_class_type_id', 3]
                ]
            )->whereIn('sales_dept_id', $depId)->sum('pay_fee');
            $otherFee = $otherFee1 + $otherFee2 + $otherFee3;
        } else {
            $allCount = ClubStudentPayment::where(
                [
                    ['is_delete', 0],
                    ['created_at', '>=', $startTime],
                    ['created_at', '<=', $endTime],
                ]
            )->where('sales_dept_id', $depId)->count();
            $onyuyue = $this->onyuyue($depId,$startTime,$endTime);
            $onchange = $this->onchange($depId,$startTime,$endTime);
            $online =$this->online($depId,$startTime,$endTime);
            $payAgain = $this->payAgain($depId,$startTime,$endTime);
            $payFee = $this->payFee($depId,$startTime,$endTime); //销售额
            $selfPayFee = $this->payment($depId,$startTime,$endTime);  //销售自营课
            $otherFee = $this->otherPayment($depId,$startTime,$endTime);  //其他销售额
        }
        $items["name"] =  ClubDepartment::where('id',$data["depId"])->value('name'); //预约
        $items["onyuyue"] =  $onyuyue; //预约
        $items["onchange"] =  $onchange; //渠道
        $items["online"] =  $online ;  //线上
        $items["payAgain"] = $payAgain; //续费
        $items["pay"] = $allCount; //总数
        $items["payFee"] = $payFee; //销售额
        $items["Payment"] = $selfPayFee; //销售自营课
        $items["otherPayment"] = $otherFee;
        return returnMessage('200', '请求成功',$items);
    }

//    获取所有子目录
    public function getallDep($depId){
        global $result;
        $res = ClubDepartment::where('parent_id',$depId)->where("type",2)->select('id','name')->where('is_delete',0)->get()->toArray();
        if(count($res)>0){
            foreach ($res as $item){
                $result[] = $item;
                $id = $item["id"];
                $this->getallDep($id);
            }
        }
        return $result;
    }

    /**
     * 判断团队是否父级团队
     * @param $depid
     * @return array|int
     * @date 2018/9/27
     * @author jesse
     */
    public function isParentGroup($depid)
    {
        $ClubDepartment = ClubDepartment::where([['parent_id', 0],['id', $depid]])->first();
        if (count($ClubDepartment)) {
            //是最父级部门，把所属小组id查出
            $depIdArr = ClubDepartment::where('parent_id', $depid)->pluck('id')->toArray();
            array_push($depIdArr, $depid);
            return $depIdArr;
        }
        return $depid;
    }

    //获取所有部门下的销售
    public function getdepAllSalts($depid){
        //jesse
        //判断是否部门
        if (is_array($depid)) {
            $sale = ClubSales::whereIn('sales_dept_id', $depid)->where('is_delete',0)->get()->toArray();
        } else {
            $sale = ClubSales::where('sales_dept_id',$depid)->where('is_delete',0)->get()->toArray();
        }

        $arr = array();
        foreach ($sale as $item){
            $arr[] = $item["id"];
        }
        if(count($arr)> 0){
            $str = $ids = join("','",$arr);
        }else{
            $str ='';
        }
        return $str;
    }
    //1.获取推广数据
    public function getstudentcount($salesIds,$startTime,$endTime){
        $sql = "SELECT count(*) as count FROM club_student WHERE is_delete=0 and sales_id in ('".$salesIds."') and created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //2.获取预约数据
    public function getsubscribecount($salesIds,$startTime,$endTime){
        $sql = "SELECT count(*) as count FROM club_student_subscribe WHERE  is_delete=0 and sales_id in ('".$salesIds."') and created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //3.获取体验数据
    public function getsigncount($salesIds,$startTime,$endTime){
        $sql = "SELECT count(*) as count FROM club_student_subscribe AS subscribe LEFT JOIN club_course_sign AS sign ON subscribe.sign_id = sign.id WHERE  subscribe.is_delete=0 and subscribe.sales_id in ('".$salesIds."')AND sign.sign_status = '1' and subscribe.created_at >= '".$startTime."'AND subscribe.created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //4.预约 销售 club_student_payment 销售=2;渠道=3;线上=4,5
    public function onyuyue($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and channel_type = '2' and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //5.渠道
    public function onchange($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and channel_type = '3' and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //6.线上
    public function online($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and channel_type in('2','3') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //7.续费  club_student_payment is_pay_again  1=续费
    public function payAgain($depid,$startTime,$endTime){
        $sql = "SELECT count(*) as count 
                  from club_student_payment 
                  WHERE is_delete=0 and sales_dept_id= ".$depid." 
                  and is_pay_again =1 and created_at >='".$startTime."' 
                  and payment_tag_id = 2
                  AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

//销售额： club_student_payment   sales_dept_id
    public function payFee($depid,$startTime,$endTime){
        $sql = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        if(!$count){
            $count = 0;
        }
        return $count;
    }

    //销售自营课：club_student_payment  channel_type=2 & payment_class_type_id =1，2 & is_pay_again =0
    public function payment($depid,$startTime,$endTime){
        $sql = "SELECT SUM(pay_fee) as count 
                  from club_student_payment 
                  WHERE is_delete=0 and sales_dept_id= ".$depid." and channel_type =2 
                  and payment_class_type_id in (1,2) 
                  and created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        if(!$count){
            $count = 0;
        }
        return $count;
    }
//其他销售额：club_student_payment
//channel_type=！2 & payment_class_type_id =1，2
//channel_type=2 & payment_class_type_id =1，2 &  & is_pay_again =1
//payment_class_type_id = 3

    public function otherPayment($depid,$startTime,$endTime){
        $sql1 = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and channel_type !=2 and payment_class_type_id in ('1','2') and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res1 = DB::select($sql1);
        $sql2 = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and channel_type =2 and payment_class_type_id in ('1','2')and is_pay_again =1 and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res2 = DB::select($sql2);
        $sql3 = "SELECT SUM(pay_fee) as count from club_student_payment WHERE is_delete=0 and sales_dept_id= ".$depid." and payment_class_type_id =3 and  created_at >='".$startTime."' AND created_at <= '".$endTime."'";
        $res3 = DB::select($sql3);
        $res = $res1[0]->count + $res2[0]->count + $res3[0]->count;
        return $res;
    }


//4.提成详情
    public function  treamExtract (Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'depId' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $depId = isset($data['depId']) ? $data['depId'] : '';            //部门id
        $season = isset($data['season']) ? $data['season'] : '';
        $year = isset($data['year']) ? $data['year'] : '';

        $sale = DB::table('club_sales_performance_by_season_snapshot AS snapshot1')
            ->join('club_department AS department', 'snapshot1.target_id', '=', 'department.id')
            ->select('snapshot1.target_id','snapshot1.spread_count','snapshot1.subscribe_count','snapshot1.experience_count',
                'snapshot1.payment_sales_count',
                'snapshot1.payment_media_count','snapshot1.payment_online_count','snapshot1.payment_continue_count','snapshot1.performance1',
                'snapshot1.normal_class_sales_no_continue_performance1','snapshot1.day_class_sales_no_continue_performance1',
                'snapshot1.target1','snapshot1.bonus1','snapshot1.target2','snapshot1.bonus2',
                'snapshot1.normal_class_sales_no_continue_performance2','snapshot1.day_class_sales_no_continue_performance2',

                'snapshot1.normal_class_online_performance','snapshot1.normal_class_media_performance',
                'snapshot1.normal_class_continue_performance','snapshot1.day_class_online_performance',
                'snapshot1.day_class_media_performance','snapshot1.day_class_continue_performance',
                'snapshot1.closed_class_performance',

                'snapshot1.normal_class_online_bonus','snapshot1.normal_class_media_bonus',
                'snapshot1.normal_class_continue_bonus','snapshot1.day_class_online_bonus',
                'snapshot1.day_class_media_bonus','snapshot1.day_class_continue_bonus',
                'snapshot1.closed_class_bonus',
                'department.name'
            )
            ->where('snapshot1.target_type', 1)->where('snapshot1.is_delete',0);

        if(strlen($year)>0){
            $sale->where('snapshot1.year',$year);

        }
        if (strlen($depId) > 0) {
            $sale->where('snapshot1.target_id', $depId);

        }

        if (strlen($season) > 0) {
            $sale->where('snapshot1.season', $season);

        }

        $sale = $sale->get()->toArray();
        foreach ($sale as $item) {
            $item->performance = $item->normal_class_online_performance+$item->normal_class_media_performance+
                $item->normal_class_continue_performance+$item->day_class_online_performance+
                $item->day_class_media_performance+$item->day_class_continue_performance+
                $item->closed_class_performance;

            $item->bonus =$item->normal_class_online_bonus+$item->normal_class_media_bonus+
                $item->normal_class_continue_bonus+$item->day_class_online_bonus+
                $item->day_class_media_bonus+$item->day_class_continue_bonus+
                $item->closed_class_bonus;
            $item->pay = $item->payment_sales_count+$item->payment_media_count+$item->payment_online_count;
            $total1 = $item->normal_class_sales_no_continue_performance1 + $item->day_class_sales_no_continue_performance1;
            $total2 = $item->normal_class_sales_no_continue_performance2 + $item->day_class_sales_no_continue_performance2;
            $item->total1 =$total1;
            $item->total2 =$total2;

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


//5提成详情-详情
    public function  treamitems (Request $request){
        $data = $request->all();
        $validate = Validator::make($data, [
            'depId' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $depId = isset($data['depId']) ? $data['depId'] : '';            //部门id
        $year = isset($data['year']) ? $data['year'] : '';
        $season = isset($data['season']) ? $data['season'] : '';

        $sale = DB::table('club_sales_performance_by_season_snapshot  AS snapshot1')
            ->join('club_department AS department','snapshot1.target_id','=','department.id')
            ->select(
                'snapshot1.*','department.name'
            )
            ->where('snapshot1.target_type',1)
            ->where('snapshot1.is_delete',0);

        if(strlen($year)>0){
            $sale->where('snapshot1.year',$year);

        }
        if(strlen($depId)>0){
            $sale->where('snapshot1.target_id',$depId);

        }
        if(strlen($season)>0){
            $sale->where('snapshot1.season',$season);

        }
        $sale = $sale->get();


        $sale2 = DB::table('club_sales_performance_by_season_snapshot AS snapshot1')
            ->join('club_department AS department','snapshot1.target_id','=','department.id')
            ->select(
                'snapshot1.*','department.name'
            )
            ->where('snapshot1.target_type',1)
            ->where('snapshot1.is_delete',0);

        if(strlen($year)>0){
            $sale2->where('snapshot1.year',intval($year)-1);

        }
        if(strlen($depId)>0){
            $sale2->where('snapshot1.target_id',$depId);

        }
        if(strlen($season)>0){
            $sale2->where('snapshot1.season',$season);

        }
        $sale2 = $sale2->get();
        $res["toYear"] = $sale;
        $res["yeYear"] = $sale2;
        return returnMessage('200', '请求成功', $res);
    }





}
