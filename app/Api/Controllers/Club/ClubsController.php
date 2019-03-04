<?php
namespace App\Api\Controllers\Club;

use App\Api\Controllers\Permission\DepartmentController;
use App\Http\Controllers\Controller;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassTime\ClubClassTime;
use App\Model\ClubCoachCostByCourse\ClubCoachCostByCourse;
use App\Model\ClubDepartment\ClubDepartment;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubRole\ClubRole;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubUser\ClubUser;
use App\Model\ClubVenue\ClubVenue;
use App\Model\Permission\Department;
use App\Model\Permission\Permission;
use App\Model\Permission\Role;
use App\Model\Permission\RoleMenu;
use App\Model\Permission\RolePermission;
use App\Model\Permission\User;
use App\Model\Club\Club;
use App\Model\Club\ClubDetail;
use App\Model\Club\ClubImage;
use App\Model\Club\ClubQrcodeImage;
use App\Model\Club\ClubVideo;

use App\Services\Club\IClubService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Model\Recommend\ClubCourseReward;
use App\Facades\Util\Log;


class ClubsController extends Controller
{
    /**
     * @var
     */
    private $user;

    /**
     * @var
     */
    private $clubService;

    public function __construct(IClubService $clubService)
    {
        try {
            //获取该用户
            $this->user = JWTAuth::parseToken()->authenticate();
        } catch (\Exception $exception) {

        }

        $this->clubService = $clubService;
    }
    //1.俱乐部列表
    public function  clublist (Request $request){

        $input = $request->all();
        $validate = Validator::make($input, [
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $cname = isset($input['name']) ? $input['name'] : "";
        $provinceId = isset($input['provinceId']) ? $input['provinceId'] : "";
        $cityId = isset($input['cityId']) ? $input['cityId'] : "";
        $districtId = isset($input['districtId']) ? $input['districtId'] : "";

        $mode = isset($input['mode']) ? $input['mode'] : "";
        $type = isset($input['type']) ? $input['type'] : "";
        $status = isset($input['status']) ? $input['status'] : "";

        $pagePerNum = isset($input['pagePerNum']) ? $input['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($input['currentPage']) ? $input['currentPage'] : 1; //页数

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $club_id =$this->user->club_id;
        $club = Club::select('id','parent_id as parentId','account_id as accountId','name','province_id AS provinceId','province_name AS provinceName','city_id AS cityId','city_name AS cityName','district_id AS districtId','district_name AS districtName','mode','type', 'mobile','status')->where('is_delete',0);
        $club2 = Club::select('id','parent_id as parentId','account_id as accountId','name','province_id AS provinceId','province_name AS provinceName','city_id AS cityId','city_name AS cityName','district_id AS districtId','district_name AS districtName','mode','type', 'mobile','status')->where('is_delete',0);

        $club->where(function ($query) use ($club_id) {
            $query->where('parent_id'  ,$club_id )
                ->orwhere('id', $club_id);
        });
        $club2->where(function ($query) use ($club_id) {
            $query->where('parent_id'  ,$club_id )
                ->orwhere('id', $club_id);
        });


        if(strlen($cname)>0){
            $club->where('name','like','%'.$cname.'%');
            $club2->where('name','like','%'.$cname.'%');
        }

        if(strlen($provinceId)>0){
            $club->where('province_id',$provinceId);
            $club2->where('province_id',$provinceId);
            if(strlen($cityId)>0){
                $club->where('city_id',$cityId);
                $club2->where('city_id',$cityId);
                if(strlen($districtId)>0){
                    $club->where('district_id',$districtId);
                    $club2->where('district_id',$districtId);
                }
            }
        }

        if(strlen($mode)>0){
            $club->where('mode',$mode);
            $club2->where('mode',$mode);
        }

        if(strlen($type)>0){
            $club->where('type',$type);
            $club2->where('type',$type);
        }

        if(strlen($status)>0){
            $club->where('status',$status);
            $club2->where('status',$status);
        }
        $res = $club->paginate($pagePerNum);
        $count = $club2->count();



        $result = array();

        $result['data'] = $res->transform(function ($item) {
            if($item->id == $this->user->club_id){
                $myself =1;
            }else{
                $myself =0;
            }
            $result = [
                'id'=> $item->id,
                'parentId'=> $item->parentId,
                'accountId'=> $item->accountId,
                'name'=> $item->name,
                'provinceId'=> $item->provinceId,
                'provinceName'=> $item->provinceName,
                'cityId'=> $item->cityId,
                'cityName'=> $item->cityName,
                'districtId'=> $item->districtId,
                'districtName'=> $item->districtName,
                'mode'=> $item->mode,
                'type'=> $item->type,
                'mobile'=> $item->mobile,
                'status'=> $item->status,
                'myself'=> $myself
            ];

            return $result;
        });
        $result['isShow']= $this->isSencondClub($this->user->club_id);
        $result['total'] = $count;
        return returnMessage('200', '',$result);
    }

    public function isSencondClub($club_id){
        $isShow = 0;
        $club = Club::where("parent_id",0)->where('is_delete',0)->pluck('id');
        if(count($club)>0){
            $clubId = $club[0];
            if($club_id == $clubId){
                $isShow = 1;
            }
        }

        return $isShow;
    }

    // 2.添加俱乐部
    public function clubadd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'name' => 'required|string|max:50',
            'provinceId' => 'required|numeric',
            'provinceName' => 'required|string',
            'cityId' => 'nullable|numeric',
            'cityName' => 'nullable|string',
            'districtId' => 'nullable|numeric',
            'districtName' => 'nullable|string',
            'type' => 'required|numeric',
            'mode' => 'required|numeric',
            'phone' => ['required','regex:/^1[3|4|5|7|8]\d{9}$/'],
            'preAccount' => 'required|string',
            'account' => 'required|string',
            'username' => 'required|string',
            'password' => 'required|string',

        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        if(Club::where('pre_account',$input['preAccount'])->exists()){
            return returnMessage('1205',config('error.club.1205'));
        }
        $imgData = isset($input['imgData']) ? $input['imgData'] : array();
        $imgqrcodeData = isset($input['imgqrcodeData']) ? $input['imgqrcodeData'] : array();
        $videoData = isset($input['videoData']) ? $input['videoData'] : array();

        if(Club::where('pre_account',$input['preAccount'])->exists()){
            return returnMessage('400','该前缀已存在');
        }

        $permission = Permission::find(1);

        try {
            DB::transaction(function () use ($input, $imgData, $imgqrcodeData, $videoData, $permission) {
                // 创建俱乐部
                $field = new Club();
                $field->name = $input["name"];
                $field->province_id = $input["provinceId"];
                $field->province_name = $input["provinceName"];
                $field->pre_account = $input['preAccount'];
                $field->city_id = isset($input["cityId"]) ? $input["cityId"] : '';
                $field->city_name = isset($input["cityName"]) ? $input["cityName"] : '';
                $field->district_id = isset($input["districtId"]) ? $input["districtId"] : '';
                $field->district_name = isset($input["districtName"]) ? $input["districtName"] : '';
                $field->type = $input["type"];
                $field->mode = $input["mode"];
                $field->parent_id = $input['user']['club_id'];
                $field->mobile = $input["phone"];
                $field->class_count = 1;
                $field->effect_class_count = 1;
                $field->updated_at = date("Y-m-d H:i:s");
                $clubres = $field->save();
                if (!$clubres) throw new \Exception('插入失败');
                $clubId = $field->id;

                // 创建默认角色
                $role = new ClubRole();
                $role->role_name = '超级管理员';
                $role->role_desc = "最高权限拥有者";
                $role->club_id = $clubId;
                $role->is_efficacy = 1;
                $role->is_grant_delete = 1;
                $role->is_admin = 1;
                $roleres = $role->save();
                if (!$roleres) throw new \Exception('插入失败');
                $role_id = $role->id;

                // 添加教练角色
                $role = new ClubRole();
                $role->role_name = '教练';
                $role->role_desc = "教练";
                $role->club_id = $clubId;
                $role->is_efficacy = 1;
                $role->type = 1;
                $role->is_grant_delete = 1;
                $role->is_admin = 0;
                $roleres = $role->save();
                if (!$roleres) throw new \Exception('插入失败');
                $coachRoleId = $role->id;

                $coachMenu = new RoleMenu();
                $coachMenu->club_id = $clubId;
                $coachMenu->role_id = $coachRoleId;
                $coachMenu->menu_id = $permission->id;
                $coachMenu->save();

                $coachPermission = new RolePermission();
                $coachPermission->club_id = $clubId;
                $coachPermission->role_id = $coachRoleId;
                $coachPermission->permission_id = $permission->id;
                $coachPermission->save();

                // 添加教练部门
                $department = new ClubDepartment();
                $department->name = '教练部';
                $department->parent_id = 0;
                $department->type = 1;
                $department->is_grant_delete = 1;
                $department->club_id = $clubId;
                $depart = $department->save();
                if (!$depart) throw new \Exception('插入失败');

                // 添加销售部门
                $department = new ClubDepartment();
                $department->name = '销售部';
                $department->parent_id = 0;
                $department->type = 2;
                $department->is_grant_delete = 1;
                $department->club_id = $clubId;
                $depres = $department->save();
                if (!$depres) throw new \Exception('插入失败');
                $salesDeptId = $department->id;

                // 添加销售角色
                $role = new ClubRole();
                $role->role_name = '销售';
                $role->role_desc = "销售";
                $role->club_id = $clubId;
                $role->is_efficacy = 1;
                $role->type = 2;
                $role->is_grant_delete = 1;
                $role->is_admin = 0;
                $roleres = $role->save();
                if (!$roleres) throw new \Exception('插入失败');
                $salesRoleId = $role->id;

                $salesMenu = new RoleMenu();
                $salesMenu->club_id = $clubId;
                $salesMenu->role_id = $salesRoleId;
                $salesMenu->menu_id = $permission->id;
                $salesMenu->save();

                $salesPermission = new RolePermission();
                $salesPermission->club_id = $clubId;
                $salesPermission->role_id = $salesRoleId;
                $salesPermission->permission_id = $permission->id;
                $salesPermission->save();

                // 添加管理员权限
                $menuPermission = Permission::where('parent_id', 0)->get();
                $rolePermission = Permission::all();
                foreach ($menuPermission as $value) {
                    $menu = new RoleMenu();
                    //todo 俱乐部字段
                    $menu->club_id = $clubId;
                    $menu->role_id = $role_id;
                    $menu->menu_id = $value->id;
                    $menuRes = $menu->save();
                    if(!$menuRes) throw new \Exception('插入失败');
                }
                foreach ($rolePermission as $value) {
                    $permission = new RolePermission();
                    //todo 俱乐部字段
                    $permission->club_id = $clubId;
                    $permission->role_id = $role_id;
                    $permission->permission_id = $value->id;
                    $permissionRes = $permission->save();
                    if(!$permissionRes) throw new \Exception('插入失败');
                }

                // 添加用户
                $user = new ClubUser();
                $user->tel = $input['phone'];
                $user->account = $input['preAccount'].strtolower($input['account']);
                $user->username = $input['username'];
                $user->password = md5($input['password']);
                $user->role_id = $role_id;
                $user->club_id = $clubId;
                $user->dept_id = 30;
                $user->dept_name = '销售部';
                $userres = $user->save();
                if (!$userres) {
                    throw new \Exception("用户信息添加失败");
                }
                $userid = $user->id;

                // 俱乐部绑定管理员账号
                $account = Club::find($clubId);
                $account->account_id = $userid;
                $updateaccount = $account->update();
                if (!$updateaccount) throw new \Exception('更新失败');

                // 添加默认销售用户
                $salesUser = new ClubUser();
                $salesUser->tel = $input['phone'];
                $salesUser->account = strtolower($input['preAccount']).'sales'.$clubId;
                $salesUser->username = '默认销售';
                $salesUser->password = md5($input['password']);
                $salesUser->role_id = $salesRoleId;
                $salesUser->club_id = $clubId;
                $salesUser->dept_id = $salesDeptId;
                $salesUser->dept_name = '销售部';
                $salesUser->save();
                $salesUserId = $salesUser->id;
                // 添加默认销售用户
                $sales = new ClubSales();
                $sales->club_id = $clubId;
                $sales->user_id = $salesUserId;
                $sales->sales_dept_id = $salesDeptId;
                $sales->sales_name = '默认销售';
                $sales->mobile = $input['phone'];
                $sales->status = 1;
                $salesRes = $sales->save();
                if (!$salesRes) {
                    throw new \Exception("销售信息添加失败");
                }
                $sales_id = $sales->id;

                // 添加默认场馆
                $venue = new ClubVenue();
                $venue->name = '默认场馆';
                $venue->club_id = $clubId;
                $venue->province = $input['provinceName'];
                $venue->city = $input['cityName'];
                $venue->district = $input['districtName'];
                $venue->province_id = $input['provinceId'];
                $venue->city_id = $input['cityId'];
                $venue->district_id = $input['districtId'];
                $venue->price_in_app = 300;
                $venue->status = 1;
                $venue->class_count = 1;
                $venue->effect_class_count = 1;
                $venueRes = $venue->save();
                if (!$venueRes) {
                    throw new \Exception("场馆信息添加失败");
                }
                $venueId = $venue->id;

                // 添加默认班级
                $class = new ClubClass();
                $class->name = '默认班级';
                $class->club_id = $clubId;
                $class->type = 1;
                $class->pay_tag_name = '免费体验';
                $class->venue_id = $venueId;
                $class->venue_name = '默认场馆';
                $class->student_limit = 100;
                $class->teacher_id = $sales_id;
                $class->status = 1;
                $classRes = $class->save();
                if (!$classRes) {
                    throw new \Exception("班级信息添加失败");
                }
                $classId = $class->id;

                // 班级时间
                $classTime = new ClubClassTime();
                $classTime->class_id = $classId;
                $classTime->day = 1;
                $classTime->start_time = '09:00:00';
                $classTime->end_time = '10:00:00';
                $classTimeRes = $classTime->save();
                if (!$classTimeRes) {
                    throw new \Exception("班级时间信息添加失败");
                }

                // 添加体验缴费方案
                $payment = new ClubPayment();
                $payment->club_id = $clubId; //俱乐部id
                $payment->name = "免费体验";//缴费方案名称
                $payment->payment_tag = '免费体验';
                $payment->type = 1; //缴费方案所适用的班级
                $payment->tag = 1; //缴费方案类型
                $payment->price = 0;//价格
                $payment->original_price = 300;//原价
                $payment->min_price = 0;//底价
                $payment->course_count = 1;//课时数
                $payment->use_to_student_type = 1;//适用学员
                $payment->private_leave_count = 0;//事假数
                $payment->show_in_app = 0;//是否在App显示
                $payment->limit_to_buy = 0;//是否限购
                $payment->is_free = 1;
                $payment->is_default = 1;
                $payment->status = 1; //有效
                $payres = $payment->save();
                if (!$payres) throw new \Exception('插入失败');

                // 添加活动缴费方案
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

                //添加推广奖励课时记录
                $courseReward = new ClubCourseReward();
                $courseReward->club_id = $clubId;
                $courseReward->num_for_try = 1;
                $courseReward->num_for_buy = 3;
                $courseReward->saveOrFail();

                //添加一个俱乐部积分兑换课时记录
                try {
                    $this->clubService->addCourseExchangeRecord($clubId,$input['name']);
                } catch (\Exception $e) {
                    throw new \Exception($e->getMessage(),$e->getCode());
                }

                if(count($imgData)>0){
                    foreach ($imgData as $item){
                        $clubimg = new ClubImage();
                        $clubimg->club_id = $clubId;
                        $clubimg->file_path = $item['path'];
                        $clubimg->description = $item['description'];
                        $clubimg->sort = $item['sort'];
                        $imgres = $clubimg->save();
                        if (!$imgres) throw new \Exception('插入失败');
                    }
                }

                if(count($imgqrcodeData)>0){
                    foreach($imgqrcodeData as $citem){
                        $clubQimg = new ClubQrcodeImage();
                        $clubQimg->club_id = $clubId;
                        $clubQimg->file_path = $citem['path'];
                        $clubQimg->sort = $citem['sort'];
                        $qimgres = $clubQimg->save();
                        if (!$qimgres) throw new \Exception('插入失败');
                    }
                }

                if(count($videoData)>0){
                    foreach ($videoData as $vitem){
                        $clubvideo = new ClubVideo();
                        $clubvideo->club_id = $clubId;
                        $clubvideo->file_path = $vitem['path'];
                        $clubvideo->sort = $vitem['sort'];
                        $videores = $clubvideo->save();
                        if (!$videores) throw new \Exception('插入失败');
                    }
                }
            });
        } catch (\Exception $e) {
            Log::setGroup('ExceptionRequest')->error('添加俱乐部失败',['msg' => $e->getMessage()]);
            throw new \Exception('添加俱乐部失败');
        }

        return returnMessage('200', '');
    }


    // 3.修改俱乐部
    /*1.3.1】除了账号/密码以为，其他字段均允许修改
     * */
    public function clubedit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|string',
            'name' => 'required|string|between:6,50',
            'provinceId' => 'required|numeric',
            'provinceName' => 'required|string',
            'cityId' => 'nullable|numeric',
            'cityName' => 'nullable|string',
            'districtId' => 'nullable|numeric',
            'districtName' => 'nullable|string',

            'type' => 'required|numeric',
            'mode' => 'required|numeric',

            'phone' => 'required|string',
            'username' => 'required|string',

        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $imgData = isset($input['imgData']) ? $input['imgData'] : array();
        $imgqrcodeData = isset($input['imgqrcodeData']) ? $input['imgqrcodeData'] : array();
        $videoData = isset($input['videoData']) ? $input['videoData'] : array();

        $field = Club::find($input['clubId']);
        if(!empty($field)){
            DB::beginTransaction();
            try{
                    $field->name = $input["name"];
                    $field->province_id = $input["provinceId"];
                    $field->province_name = $input["provinceName"];

                    $field->city_id = isset($input["cityId"]) ? $input['cityId'] : '';
                    $field->city_name = isset($input["cityName"]) ? $input["cityName"] : '';
                    $field->district_id = isset($input["districtId"]) ? $input["districtId"] : '';
                    $field->district_name = isset($input["districtName"]) ? $input["districtName"] : '';
                    $field->type = $input["type"];
                    $field->mode = $input["mode"];
                    $field->updated_at = date("Y-m-d H:i:s");

                    $clubres = $field->update();
                    if (!$clubres) throw new \Exception('更新失败');

                    $user_id = $field->account_id;
                    $user = User::find($user_id);
                    if(!empty($user)){
                        $user->tel = $input['phone'];
                        $user->username = $input['username'];
                        $useres = $user->update();
                        if (!$useres) throw new \Exception('更新失败');
                    }

                if(count($imgData)>0){
                    #删除
                    $res = ClubImage::where('club_id',$input['clubId'])->delete();
//                    if($res){

                        foreach ($imgData as $item){
                            $clubimg = new ClubImage();
                            $clubimg->club_id = $input['clubId'];

                            $clubimg->file_path = $this->getUrl($item['path']);
                            $clubimg->description = $item['description'];
                            $clubimg->sort = $item['sort'];
                            $imgres = $clubimg->save();
                            if (!$imgres) throw new \Exception('插入失败');
//                        }
                    }
                }

                if(count($imgqrcodeData)>0){
                    $res = ClubQrcodeImage::where('club_id',$input['clubId'])->delete();
//                    if($res){
                        $clubQimg = new ClubQrcodeImage();
                        foreach($imgqrcodeData as $citem){
                            $clubQimg->club_id = $input['clubId'];
                            $clubQimg->file_path = $this->getUrl($citem['path']);
                            $clubQimg->sort = $citem['sort'];
                            $qimgres = $clubQimg->save();
                            if (!$qimgres) throw new \Exception('插入失败');
                        }
//                    }
                }

                if(count($videoData)>0){
                    $res = ClubVideo::where('club_id',$input['clubId'])->delete();
//                    if($res){
                        $clubvideo = new ClubVideo();
                        foreach ($videoData as $vitem){
                            $clubvideo->club_id = $input['clubId'];
                            $clubvideo->file_path = $this->getUrl($vitem['path']);
                            $clubvideo->sort = $vitem['sort'];
                            $videores = $clubvideo->save();
                            if (!$videores) throw new \Exception('插入失败');
                        }
//                    }

                }

                DB::commit();
                return returnMessage('200', '');
            } catch (\Exception $e){
                DB::rollback();//事务回滚
                return returnMessage($e->getCode(),$e->getMessage());
            }
        }else{
            return returnMessage('1204', config('error.club.1204'));
        }
    }
    //获取上传的路径（完整url）
    protected function getUrl($url){
        $arr = parse_url($url);
        isset($arr['host']) ? $filePath = $url : $filePath = env('IMG_DOMAIN').$arr['path'];
        return $filePath;
    }
    public function getpath($url){
        return strstr( $url, 'https://cdn.drsports.cn/');
    }
    //4.导出
    /*1.4.2】导出表字段：ID、俱乐部名称、所属区域、属性(1=加盟;2=入驻)、状态(1=正常;0=下架)、品类(1=篮球;2=足球;3=棒球;4=跆拳道;5=拓展;6=航模)。
     * */
    public function  clubexec (Request $request){
        $input = $request->all();

        $clubId = isset($input['clubId']) ? $input['clubId'] : '';
        $name = isset($input['name']) ? $input['name'] : "";
        $provinceId = isset($input['provinceId']) ? $input['provinceId'] : "";
        $cityId = isset($input['cityId']) ? $input['cityId'] : "";
        $districtId = isset($input['districtId']) ? $input['districtId'] : "";

        $mode = isset($input['mode']) ? $input['mode'] : "";
        $type = isset($input['type']) ? $input['type'] : "";
        $status = isset($input['status']) ? $input['status'] : "";

        $modearr = array("0"=>"未知","1"=>"加盟","2"=>"入驻");
        $typearr = array("0"=>"未知","1"=>"篮球","2"=>"足球","3"=>"棒球","4"=>"跆拳道","5"=>"拓展","6"=>"航模");
        $statusarr = array("1"=>"正常","0"=>"下架");


        $club = DB::table('club_club')->select('id','parent_id as parentId','account_id as accountId','name','province_id AS provinceId','province_name AS provinceName','city_id AS cityId','city_name AS cityName','district_id AS districtId','district_name AS districtName','mode','type', 'mobile','status');
        $club->where('id',$clubId);


        if(strlen($name)>0){
            $club->where('name','like','%'.$name.'%');
        }


        if(strlen($provinceId)>0){
            $club->where('province_id',$provinceId);
            if(strlen($cityId)>0){
                $club->where('city_id',$cityId);
                if(strlen($districtId)>0){
                    $club->where('district_id',$districtId);
                }
            }
        }

        if(strlen($mode)>0){
            $club->where('mode',$mode);
        }

        if(strlen($type)>0){
            $club->where('type',$type);
        }

        if(strlen($status)>0){
            $club->where('status',$status);
        }

        $res = $club->where('id',$clubId)->get();

        $result = array();
        $result['total'] = count($res);
        $result['data'] = $res->transform(function ($item,$clubId) {
            if($item->id == $clubId){
                $myself =1;
            }else{
                $myself =0;
            }
            $result = [
                'id'=> $item->id,
                'parentId'=> $item->parentId,
                'accountId'=> $item->accountId,
                'name'=> $item->name,
                'provinceId'=> $item->provinceId,
                'provinceName'=> $item->provinceName,
                'cityId'=> $item->cityId,
                'cityName'=> $item->cityName,
                'districtId'=> $item->districtId,
                'districtName'=> $item->districtName,
                'mode'=> $item->mode,
                'type'=> $item->type,
                'mobile'=> $item->mobile,
                'status'=> $item->status,
                'myself'=> $myself
            ];

            return $result;
        });

        foreach ($result["data"] as $item){
            $pid =$item["id"];

            $res = Club::where("parent_id",$pid)->select('id','parent_id as parentId','account_id as accountId','name','province_id AS provinceId','province_name AS provinceName','city_id AS cityId','city_name AS cityName','district_id AS districtId','district_name AS districtName','mode','type', 'mobile','status')->get()->toArray();
            if(count($res)>0){
                foreach ($res as $reitem) {
                    $reitem["myself"] = 0;
                    $result["data"][] = $reitem;
                }
            }
        }

        $resArray = array();
        if(count($result["data"]) > 0){
            foreach ($result["data"] as $key => $item) {
                $resArray[$key] = array(
                    $item["id"],
                    $item["name"],
                    $item["districtName"],
                );
                array_push($resArray[$key],$modearr[intval($item["mode"])]);
                array_push($resArray[$key],$typearr[intval($item["type"])]);
                array_push($resArray[$key],$statusarr[intval($item["status"])]);
            }
            $array = array('ID', '俱乐部名称', '所属区域', '属性', '状态', '品类');
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
    //5.俱乐部品类
    public function  getclass (Request $request){
        $arr = array(array("Id"=>1,"Name"=>"篮球"),array("Id"=>2,"Name"=>"足球"),array("Id"=>3,"Name"=>"棒球"),array("Id"=>4,"Name"=>"跆拳道"),array("Id"=>5,"Name"=>"拓展"),array("Id"=>6,"Name"=>"航模"));
        return returnMessage('200', '',$arr);
    }

    //6.俱乐部预览
    public function  clubview (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $club_id = $input['clubId'];
        $img = ClubImage::where('club_id',$club_id)->select('file_path as filePath','sort','description')->get()->toArray();
        $video = ClubVideo::where('club_id',$club_id)->select('file_path as filePath','sort')->get()->toArray();
        if(count($img)>0){
            foreach ($img as $key => $val){
                $img[$key] = [
                    'filePath' => $val['filePath'],
                    'sort' => $val['sort'],
                    'description' => $val['description']
                ];
            }
        }
        if(count($video)>0){
            foreach ($video as $key2 => $val2){
                $video[$key2] = [
                    'filePath' => env('IMG_DOMAIN').$val2['filePath'],
                    'sort' => $val2['sort'],
                ];
            }
        }
        $arr = array("img"=>$img,"video"=>$video);
        return returnMessage('200', '',$arr);
    }
    //7.俱乐部详情
    public function  clubitems (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $club_id = $input['clubId'];
        $isexits = Club::find($club_id);
        if(!$isexits){
            return returnMessage('1204', config('error.club.1204'));
        }
        $item = Club::where('id',$club_id)->select('id','parent_id as parentId','account_id as accountId','pre_account as preAccount','name','province_id AS provinceId','pre_account as preAccount','province_name AS provinceName','city_id AS cityId','city_name AS cityName','district_id AS districtId','district_name AS districtName','mode','type', 'mobile','status')->where('is_delete',0)->get()->toArray();
        $img = ClubImage::where('club_id',$club_id)->select("file_path as filePath",'sort','description')->where('is_delete',0)->get()->toArray();
        $video = ClubVideo::where('club_id',$club_id)->select("file_path as filePath",'sort')->where('is_delete',0)->get()->toArray();
        $qrcode_img = ClubQrcodeImage::where('club_id',$club_id)->select("file_path as filePath",'sort')->where('is_delete',0)->get()->toArray();
        if(count($img)>0){
            foreach ($img as $k => $val){
                $img[$k] = [
                    'filePath' => $val['filePath'],
                    'sort' => $val['sort'],
                    'description' => $val['description']
                ];
            }
        }

        if(count($video)>0){
            foreach ($video as $k2 => $val2){
                $video[$k2] = [
                    'filePath' => $val2['filePath'],
                    'sort' => $val2['sort'],
                ];
            }
        }
        if(count($qrcode_img)>0){
            foreach ($qrcode_img as $k3 => $val3){
                $qrcode_img[$k3] = [
                    'filePath' => $val3['filePath'],
                    'sort' => $val3['sort'],
                ];
            }
        }
        $accountid = $item[0]["accountId"];
        if(strlen($accountid)>0){
            $user = ClubUser::where('id',$accountid)->where('is_delete',0)->select('username','account')->first();
            $username = $user["username"];
            $account = $user["account"];
            $item[0]["username"] = $username;
            $length = strlen($item[0]['preAccount']);
            $item[0]["account"] = substr($account,$length);
        }

        $arr = array("item"=>$item,"img"=>$img,"video"=>$video,"qrcodeimg"=>$qrcode_img);
        return returnMessage('200', '',$arr);
    }

    //8.俱乐部密码重置
    public function  clubreset (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
            'passwd'  => 'required',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        if(strlen($input['passwd'])<6 || strlen($input['passwd'])>20){
            return returnMessage('1206', config('error.club.1206'));
        }
        $club_id = $input['clubId'];
        $passwd = $input['passwd'];
        $club = Club::where('id',$club_id)->get();
        if(count($club)>0){
            $club =$club[0];
            $userid = $club->account_id;
            try{
                ClubUser::where('id',$userid)->update(['password'=>md5($passwd)]);
                return returnMessage('200', '');
            }catch (\Exception $e){
                return returnMessage('1202',config('error.club.1202'));
            }
        }else{
            return returnMessage('1204', config('error.club.1204'));
        }
    }
    //9.俱乐部下架
    public function  shelves (Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
            'status'  => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $club_id = $input['clubId'];
        $status = $input['status'];

        // 账号、姓名、手机已存在不允许添加
        $type = 0;
        if($status == 0){
            $type = 1;
        }
        $item = Club::where('id', $club_id)->where('status', $type)->exists();
        if ($item) {
            $res = Club::where('id',$club_id)->update(['status'=>$status]);
            if($res){
                if($status == 1){
                    //上架返回账号密码
                    return returnMessage('200', '');
                }else{
                    return returnMessage('200', '');
                }

            }else{
                return returnMessage('1202',config('error.club.1202'));
            }
        }else{
            return returnMessage('1202', config('error.club.1202'));
        }
    }

    //10.俱乐部概况
    public function clubSurvey(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $club_id = isset($input['clubId']) ? $input['clubId'] : '';
        $arr = array();
        $arr["venueCount"]= $this->venueCount($club_id);
        $arr["classCount"]= $this->classCount($club_id);
        $arr["courseCount"]= $this->courseCount($club_id);
        $arr["stopCourseCount"]= $this->stopCourseCount($club_id);
        $arr["salesCount"]= $this->salesCount($club_id);
        $arr["coachCount"]= $this->coachCount($club_id);

        $arr["officialStudentHaveClassCount"]= $this->officialStudentHaveClassCount($club_id);
        $arr["officialStudentHaveNotClassCount"]= $this->officialStudentHaveNotClassCount($club_id);

        $arr["invalidOfficialStudentCount"]= $this->invalidOfficialStudentCount($club_id);
        $arr["unofficialStudentCount"]= $this->unofficialStudentCount($club_id);

        $arr["zhengShi"]= $this->zhengShi($club_id);
        $arr["feiZhengShi"]= $this->feiZhengShi($club_id);
        $arr["gongHaiKu"]= $this->gongHaiKu($club_id);
        $arr["allStudent"]= $this->allStudent($club_id);
        $arr["payAgain"]= $this->payAgain($club_id);
        return returnMessage('200', '',$arr);
    }
    //
    public function venueCount($club_id){
        $sql = "SELECT count(id) as count from club_venue WHERE is_delete=0 and  club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function classCount($club_id){
        $sql = "SELECT count(id) as count from club_class WHERE  is_delete=0 and club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //问题--课程表无club_id
    public function courseCount($club_id){
        $sql = "SELECT count(id) as count from club_course WHERE is_delete=0 and  club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //问题--课程表无club_id
    public function stopCourseCount($club_id){
        $sql = "SELECT count(id) as count from club_course WHERE is_delete=0 and  status =1 AND club_id= '".$club_id."'";;
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function salesCount($club_id){
        $sql = "SELECT count(id) as count from club_sales WHERE is_delete=0 and  club_id= '".$club_id."'";;
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function coachCount($club_id){
        $sql = "SELECT count(id) as count from club_coach WHERE is_delete=0 and club_id= '".$club_id."'";;
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function officialStudentHaveClassCount($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status=1 and left_course_count >0 and club_id= '".$club_id."'";;
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function officialStudentHaveNotClassCount($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status=1 and left_course_count =0 and club_id= '".$club_id."'";;
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }


    public function invalidOfficialStudentCount($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status='3' AND where_to_public_sea ='1' AND club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function unofficialStudentCount($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status='3' AND where_to_public_sea ='2' AND club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function zhengShi($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status='1' and club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function feiZhengShi($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status='2' and club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function gongHaiKu($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and status='3' AND club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    public function payAgain($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE is_delete=0 and is_pay_again='1' AND club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function allStudent($club_id){
        $sql = "SELECT count(id) as count from club_student WHERE  is_delete=0 and club_id= '".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //11.俱乐部汇总
    public function clubSummary(Request $request){
        $data = $request->all();
        $club_id = isset($data['clubId']) ? $data['clubId'] : '';
        $ClassId = isset($data['classId']) ? $data['classId'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] :  date ( "Y-m-d H:i:s", mktime ( 0, 0, 0, date ( "m" ), 1, date ( "Y" ) ) );
        $endTime = isset($data['endTime']) ? $data['endTime'] :  date ( "Y-m-d H:i:s", mktime ( 23, 59, 59, date ( "m" ), date ( "t" ), date ( "Y" ) ) );


        $type = isset($data['type']) ? $data['type'] : '1';  //按周统计
        $ziYinKeAll = isset($data['ziYinKeAll']) ? $data['ziYinKeAll'] : '0';
        $pay = isset($data['pay']) ? $data['pay'] : '0';
        $new = isset($data['new']) ? $data['new'] : '0';
        $chuQing = isset($data['chuQing']) ? $data['chuQing'] : '0';
        $shiJia = isset($data['shiJia']) ? $data['shiJia'] : '0';
        $binJia = isset($data['binJia']) ? $data['binJia'] : '0';
        $queQin = isset($data['queQin']) ? $data['queQin'] : '0';
        $pass = isset($data['pass']) ? $data['pass'] : '0';
        $keShiShu = isset($data['keShiShu']) ? $data['keShiShu'] : '0';
        $waiJiao = isset($data['waiJiao']) ? $data['waiJiao'] : '0';
        $waiJiaoShouRu = isset($data['waiJiaoShouRu']) ? $data['waiJiaoShouRu'] : '0';
        $waiJiaoZhiChu = isset($data['waiJiaoZhiChu']) ? $data['waiJiaoZhiChu'] : '0';
        $pinJunPrice = isset($data['pinJunPrice']) ? $data['pinJunPrice'] : '0';
        $shouRu = isset($data['shouRu']) ? $data['shouRu'] : '0';

        $fenBiAll = isset($data['fenBiAll']) ? $data['fenBiAll'] : '0';
        $fbChuQin = isset($data['fbChuQin']) ? $data['fbChuQin'] : '0';
        $fbQueQin = isset($data['fbQueQin']) ? $data['fbQueQin'] : '0';
        $fbKeShi = isset($data['fbKeShi']) ? $data['fbKeShi'] : '0';
        $fbWaiJiao = isset($data['fbWaiJiao']) ? $data['fbWaiJiao'] : '0';
        $fbWaiJiaoZhiChu = isset($data['fbWaiJiaoZhiChu']) ? $data['fbWaiJiaoZhiChu'] : '0';
        $fbpay = isset($data['fbpay']) ? $data['fbpay'] : '0';
        $result = array();

        //1条数据
        //  支出 club_coach_cost_by_course          coach_cost+coach_manage_cost   支出
        //收入club_income_snapshot   money
        $data =array();
        $shouru = $this->cshouru($club_id,$ClassId,$startTime,$endTime);
        $zhichu = $this->czhichu($club_id,$ClassId,$startTime,$endTime);
        $data["income"] = $shouru;
        $data["expenditure"] = $zhichu;
        $data["profit"] = $shouru - $zhichu;
        $data["rate"] = $this->clilv($shouru,$zhichu);
        $result["Statistics"] = $data;
        //        $type 分类 1.按周统计 2.按月统计
        $res = getSummer(strtotime($startTime),strtotime($endTime),$type);
        //报表数据
        foreach ($res as $items){
            $items["experienceStudent"] = $this->experienceStudent($club_id,$ClassId,$items["start"],$items["end"]);
            $items["newStudent"] = $this->newStudent($club_id,$ClassId,$items["start"],$items["end"]);
            $result["chart"][] = $items;
        }

        foreach ($res as $items){
            $start = $items["start"];
            $end = $items["end"];

            if($ziYinKeAll == 1){
                $coachNum = $this->coachNum($club_id,$ClassId,$start,$end);
                $coachSr = $this->coachSr($club_id,$ClassId,$start,$end);
                $coachZc = $this->coachZc($club_id,$ClassId,$start,$end);
                $items["subscribeStudent"] = $this->subscribeStudent($club_id,$ClassId,$start,$end);
                $items["expStudent"] = $this->expStudent($club_id,$ClassId,$start,$end);
                $items["paymentStudent"] = $this->paymentStudent($club_id,$ClassId,$start,$end);
                $items["Studentnew"] = $this->Studentnew($club_id,$ClassId,$start,$end);
                $items["Attendance"] = $this->Attendance($club_id,$ClassId,$start,$end);
                $items["Compassionate"] = $this->Compassionate($club_id,$ClassId,$start,$end);
                $items["sick"] = $this->sick($club_id,$ClassId,$start,$end);
                $items["duty"] = $this->duty($club_id,$ClassId,$start,$end);
                $items["pass"] = $this->pass($club_id,$ClassId,$start,$end);
                $items["clubCourse"] = $this->clubCourse($club_id,$ClassId,$start,$end);

                $items["coachNum"] = $coachNum;
                $items["coachZc"] = $coachZc;
                $items["coachSr"] = $coachSr;
                $items["profit"] = $coachSr - $coachZc;
                $items["avgPrice"] = $this->avgPrice($coachSr,$coachNum);

            }else{
                $coachNum = $this->coachNum($club_id,$ClassId,$start,$end);
                $coachSr = $this->coachSr($club_id,$ClassId,$start,$end);
                $coachZc = $this->coachZc($club_id,$ClassId,$start,$end);
                if($pay == 1){
                    $items["subscribeStudent"] = $this->subscribeStudent($club_id,$ClassId,$start,$end);
                    $items["expStudent"] = $this->expStudent($club_id,$ClassId,$start,$end);
                    $items["paymentStudent"] = $this->paymentStudent($club_id,$ClassId,$start,$end);
                }
                if($new == 1){
                    $items["Studentnew"] = $this->Studentnew($club_id,$ClassId,$start,$end);
                }
                if($chuQing == 1){
                    $items["Attendance"] = $this->Attendance($club_id,$ClassId,$start,$end);
                }
                if($shiJia == 1){
                    $items["Compassionate"] = $this->Compassionate($club_id,$ClassId,$start,$end);
                }
                if($binJia == 1){
                    $items["sick"] = $this->sick($club_id,$ClassId,$start,$end);
                }
                if($queQin == 1){
                    $items["duty"] = $this->duty($club_id,$ClassId,$start,$end);
                }

                if($pass == 1){
                    $items["pass"] = $this->pass($club_id,$ClassId,$start,$end);
                }

                if($keShiShu == 1){
                    $items["clubCourse"] = $this->clubCourse($club_id,$ClassId,$start,$end);

                }
                if($waiJiao == 1){
                    $items["coachNum"] = $coachNum;
                }
                if($waiJiaoShouRu == 1){
                    $items["coachSr"] = $coachSr;
                }
                if($waiJiaoZhiChu == 1){
                    $items["coachZc"] = $coachZc;
                }
                if($pinJunPrice == 1){
                    $items["avgPrice"] = $this->avgPrice($coachSr,$coachNum);
                }
                if($shouRu == 1){
                    $items["profit"] = $coachSr - $coachZc;
                }

            }

            if($fenBiAll == 1){
                $fbcoachNum = $this->fbcoachNum($club_id,$ClassId,$start,$end);
                $fbcoachSr = $this->coachSr($club_id,$ClassId,$start,$end);
                $fbcoachZc = $this->fbcoachZc($club_id,$ClassId,$start,$end);

                $items["fbAttendance"] = $this->fbAttendance($club_id,$ClassId,$start,$end);
                $items["fbduty"] = $this->fbduty($club_id,$ClassId,$start,$end);
                $items["fbclubCourse"] = $this->fbclubCourse($club_id,$ClassId,$start,$end);
                $items["fbcoachNum"] = $fbcoachNum;
                $items["fbcoachZc"] = $fbcoachZc;
                $items["fbcoachSr"] = $fbcoachSr;
                $items["fbprofit"] = $fbcoachSr - $fbcoachZc;
            }else{
                $fbcoachNum = $this->fbcoachNum($club_id,$ClassId,$start,$end);
                $fbcoachSr = $this->coachSr($club_id,$ClassId,$start,$end);
                $fbcoachZc = $this->fbcoachZc($club_id,$ClassId,$start,$end);
                if($fbChuQin == 1){
                    $items["fbAttendance"] = $this->fbAttendance($club_id,$ClassId,$start,$end);
                }
                if($fbQueQin == 1){
                    $items["fbduty"] = $this->fbduty($club_id,$ClassId,$start,$end);
                }
                if($fbKeShi == 1){
                    $items["fbclubCourse"] = $this->fbclubCourse($club_id,$ClassId,$start,$end);
                }
                if($fbWaiJiao == 1){
                    $items["fbcoachNum"] = $fbcoachNum;
                }
                if($fbWaiJiaoZhiChu == 1){
                    $items["fbcoachZc"] = $fbcoachZc;
                }

                if($fbpay == 1){
                    $items["fbcoachSr"] = $fbcoachSr;
                    $items["fbprofit"] = $fbcoachSr - $fbcoachZc;
                }
            }
            $result["select"][]=$items;
        }
        $result["start"] =$startTime;
        $result["end"] = $endTime;
        return returnMessage('200', '',$result);
    }
//支出
    public function czhichu($club_id,$ClassId,$startTime,$endTime){
        $sql = "SELECT SUM(coach_cost)+sum(coach_manage_cost) AS count from club_coach_cost_by_course WHERE is_delete=0 and  created_at >='".$startTime."'AND created_at <'".$endTime."'";

        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//收入
    public function cshouru($club_id,$ClassId,$startTime,$endTime){
        $sql = "SELECT SUM(money) as count from club_income_snapshot WHERE  is_delete=0 and created_at >='".$startTime."'AND created_at <'".$endTime."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }
        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

//利率
    public function clilv($shouru,$zhichu){
        $lirong = intval($shouru) - intval($zhichu);
        if($shouru == 0){
            $res = "0.00%";
        }else{
            $res = round(($lirong/$shouru),2)* 100 ."%";
        }
        return $res;
    }

//    2.体验学员
    public function experienceStudent($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_student_subscribe WHERE is_delete=0 and subscribe_status='1' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

//    2.新学员
    public function newStudent($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_class_student WHERE is_delete=0 and enter_class_time >='".$start."'AND enter_class_time <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }



// 预约  club_student_subscribe    体验：subscribe_status=1    缴费：club_student_payment
    public function subscribeStudent($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_student_subscribe WHERE is_delete=0 and  created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }

        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//    club_student_subscribe    体验：subscribe_status=1
    public function expStudent($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_student_subscribe WHERE is_delete=0 and subscribe_status='1' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//    缴费：club_student_payment
    public function paymentStudent($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_class_student WHERE  is_delete=0 and enter_class_time >='".$start."'AND enter_class_time <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//新学员
    public function Studentnew($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_class_student WHERE is_delete=0 and  enter_class_time >='".$start."'AND enter_class_time <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//     自营课出勤  club_course_sign   sign_status  1=出勤;2=缺勤;3=事假;4=病假;5=冻结;6=pass;7=autopass',    class_type_id   1=常规班;2=走训班
    public function Attendance($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='1' AND class_type_id in ('1','2')  and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//事假
    public function Compassionate($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='3' AND class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//病假
    public function sick($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='4' AND class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//缺勤
    public function duty($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='2' AND class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//pass
    public function pass($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='6' AND class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }


//课程数    club_course  status=1    `class_type_id` int(11) DEFAULT NULL COMMENT '课程的类型见club_class_type表 1=常规班;2=走训班
    public function clubCourse($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course WHERE is_delete=0 and status='1' AND class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }


//
//教练人次  club_course_coach  status=1    `class_type_id` int(11) DEFAULT NULL COMMENT '这节课程所对应的班级类型 1=常规班;2=走训班
    public function coachNum($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_coach WHERE is_delete=0 and status='1' AND class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//教练支出：  coach_cost+coach_manage_cost
    public function coachZc($club_id,$ClassId,$start,$end){
        $sql = "SELECT SUM(coach_cost)+SUM(coach_manage_cost) AS count from club_coach_cost_by_course WHERE is_delete=0 and  class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //教练收入：
    public function coachSr($club_id,$ClassId,$start,$end){
        $sql = "SELECT SUM(money) as count from club_income_snapshot  WHERE is_delete=0 and class_type_id in ('1','2') and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//    客单价
    public function avgPrice($coachSr,$coachNum){
        if($coachNum == 0){
            $res =0;
        }else{
            $res = $coachSr/$coachNum;
            $res = number_format($res,2);
        }
        return $res;
    }



//     自营课出勤  club_course_sign   sign_status  1=出勤;2=缺勤;3=事假;4=病假;5=冻结;6=pass;7=autopass',    class_type_id   1=常规班;2=走训班
    public function fbAttendance($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='1' AND class_type_id ='3' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//缺勤
    public function fbduty($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_sign WHERE is_delete=0 and sign_status='2' AND class_type_id ='3' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

//课程数    club_course  status=1    `class_type_id` int(11) DEFAULT NULL COMMENT '课程的类型见club_class_type表 1=常规班;2=走训班
    public function fbclubCourse($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course WHERE is_delete=0 and status='1' AND class_type_id ='3' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

//教练人次  club_course_coach  status=1    `class_type_id` int(11) DEFAULT NULL COMMENT '这节课程所对应的班级类型 1=常规班;2=走训班
    public function fbcoachNum($club_id,$ClassId,$start,$end){
        $sql = "SELECT count(id) AS count from club_course_coach WHERE is_delete=0 and status='1' AND class_type_id ='3' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//教练支出：  coach_cost+coach_manage_cost
    public function fbcoachZc($club_id,$ClassId,$start,$end){
        $sql = "SELECT SUM(coach_cost)+SUM(coach_manage_cost) AS count from club_coach_cost_by_course WHERE is_delete=0 and  class_type_id ='3' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //教练收入：
    public function fbcoachSr($club_id,$ClassId,$start,$end){
        $sql = "SELECT SUM(money) as count from club_income_snapshot  WHERE is_delete=0 and status='1' AND class_type_id ='3' and created_at >='".$start."'AND created_at <'".$end."'";
        if(strlen($club_id)>0){
            $sql.="and club_id='".$club_id."'";
        }

        if(strlen($ClassId)>0){
            $sql.="and class_id='".$ClassId."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //俱乐部汇总--导出
    public function clubSummaryexport(Request $request){
        $data = $request->all();
        $club_id = isset($data['clubId']) ? $data['clubId'] : '';
        $ClassId = isset($data['classId']) ? $data['classId'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] :  date ( "Y-m-d H:i:s", mktime ( 0, 0, 0, date ( "m" ), 1, date ( "Y" ) ) );
        $endTime = isset($data['endTime']) ? $data['endTime'] :  date ( "Y-m-d H:i:s", mktime ( 23, 59, 59, date ( "m" ), date ( "t" ), date ( "Y" ) ) );


        $type = isset($data['type']) ? $data['type'] : '1';  //按周统计
        $ziYinKeAll = isset($data['ziYinKeAll']) ? $data['ziYinKeAll'] : '0';
        $pay = isset($data['pay']) ? $data['pay'] : '0';
        $new = isset($data['new']) ? $data['new'] : '0';
        $chuQing = isset($data['chuQing']) ? $data['chuQing'] : '0';
        $shiJia = isset($data['shiJia']) ? $data['shiJia'] : '0';
        $binJia = isset($data['binJia']) ? $data['binJia'] : '0';
        $queQin = isset($data['queQin']) ? $data['queQin'] : '0';
        $pass = isset($data['pass']) ? $data['pass'] : '0';
        $keShiShu = isset($data['keShiShu']) ? $data['keShiShu'] : '0';
        $waiJiao = isset($data['waiJiao']) ? $data['waiJiao'] : '0';
        $waiJiaoShouRu = isset($data['waiJiaoShouRu']) ? $data['waiJiaoShouRu'] : '0';
        $waiJiaoZhiChu = isset($data['waiJiaoZhiChu']) ? $data['waiJiaoZhiChu'] : '0';
        $pinJunPrice = isset($data['pinJunPrice']) ? $data['pinJunPrice'] : '0';
        $shouRu = isset($data['shouRu']) ? $data['shouRu'] : '0';

        $fenBiAll = isset($data['fenBiAll']) ? $data['fenBiAll'] : '0';
        $fbChuQin = isset($data['fbChuQin']) ? $data['fbChuQin'] : '0';
        $fbQueQin = isset($data['fbQueQin']) ? $data['fbQueQin'] : '0';
        $fbKeShi = isset($data['fbKeShi']) ? $data['fbKeShi'] : '0';
        $fbWaiJiao = isset($data['fbWaiJiao']) ? $data['fbWaiJiao'] : '0';
        $fbWaiJiaoZhiChu = isset($data['fbWaiJiaoZhiChu']) ? $data['fbWaiJiaoZhiChu'] : '0';
        $fbpay = isset($data['fbpay']) ? $data['fbpay'] : '0';
        $result = array();
        //        $type 分类 1.按周统计 2.按月统计
        $res = getSummer(strtotime($startTime),strtotime($endTime),$type);

        foreach ($res as $items){
            $start = $items["start"];
            $end = $items["end"];

            $coachNum = $this->coachNum($club_id,$ClassId,$start,$end);
            $coachSr = $this->coachSr($club_id,$ClassId,$start,$end);
            $coachZc = $this->coachZc($club_id,$ClassId,$start,$end);
            $items["subscribeStudent"] = $this->subscribeStudent($club_id,$ClassId,$start,$end);
            $items["expStudent"] = $this->expStudent($club_id,$ClassId,$start,$end);
            $items["paymentStudent"] = $this->paymentStudent($club_id,$ClassId,$start,$end);
            $items["Studentnew"] = $this->Studentnew($club_id,$ClassId,$start,$end);
            $items["Attendance"] = $this->Attendance($club_id,$ClassId,$start,$end);
            $items["Compassionate"] = $this->Compassionate($club_id,$ClassId,$start,$end);
            $items["sick"] = $this->sick($club_id,$ClassId,$start,$end);
            $items["duty"] = $this->duty($club_id,$ClassId,$start,$end);
            $items["pass"] = $this->pass($club_id,$ClassId,$start,$end);
            $items["clubCourse"] = $this->clubCourse($club_id,$ClassId,$start,$end);

            $items["coachNum"] = $coachNum;
            $items["coachZc"] = $coachZc;
            $items["coachSr"] = $coachSr;
            $items["profit"] = $coachSr - $coachZc;
            $items["avgPrice"] = $this->avgPrice($coachSr,$coachNum);

            $fbcoachNum = $this->fbcoachNum($club_id,$ClassId,$start,$end);
            $fbcoachSr = $this->coachSr($club_id,$ClassId,$start,$end);
            $fbcoachZc = $this->fbcoachZc($club_id,$ClassId,$start,$end);

            $items["fbAttendance"] = $this->fbAttendance($club_id,$ClassId,$start,$end);
            $items["fbduty"] = $this->fbduty($club_id,$ClassId,$start,$end);
            $items["fbclubCourse"] = $this->fbclubCourse($club_id,$ClassId,$start,$end);
            $items["fbcoachNum"] = $fbcoachNum;
            $items["fbcoachZc"] = $fbcoachZc;
            $items["fbcoachSr"] = $fbcoachSr;
            $items["fbprofit"] = $fbcoachSr - $fbcoachZc;
            $result[]=$items;
        }

        $resArray = array();
        if(count($result) > 0){
            $resArray = $result;
            $array = array('开始时间', '结束时间', '预约-自营课', '体验-自营课', '缴费-自营课', '新学员-自营课',
                '出勤-自营课','事假-自营课','病假-自营课','缺勤-自营课','pass-自营课','课时数-自营课','外教数-自营课','外教收入-自营课','外教支出-自营课','平均价格-自营课','盈利-自营课',
                '封闭营出勤','封闭营缺勤','封闭营课时数','封闭营外教数','封闭营支出','封闭营收入','封闭利润');
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

    /**
     * 首页俱乐部统计
     * @return array
     * @date 2018/10/12
     * @author edit jesse
     */
    public function clubStatistics(){
        $start_time = date("Y-m-d 00:00:00");  //当天开始时间
        $end_time = date("Y-m-d 23:59:59"); //当天结束时间
        $club_id = $this->user->club_id;
        if ($this->user->is_admin == 1) {
            $type = 0;
        } else {
            $type = ClubRole::where('id',$this->user->role_id)->value('type');
            $type = $type == 2 ? $type : 1;
        }
        $arr = [];
        if ($type === 0 || $type === 1) {
            $saleid = 0;
            $arr["tuiGuang"] = $this->tuiGuang(1,$club_id,$start_time,$end_time);
            $arr["yuYue"] = $this->yuYue(1,$club_id,$start_time,$end_time);
            $arr["tiYan"] = $this->tiYan(1,$club_id,$start_time,$end_time);
            $arr["fuFei"] = $this->fuFei(1,$club_id,$start_time,$end_time);
            $arr["xuFei"] = $this->xuFei(1,$club_id,$start_time,$end_time);
            $arr["shiXiao"] = $this->shiXiao(1,$club_id,$start_time,$end_time);
            $arr["totalMoney"] = $this->totalMoney(1,$club_id,$start_time,$end_time);
            $arr["totalNum"] = $this->totalNum(1,$club_id,$start_time,$end_time);
        } else {
            $sale = ClubSales::where('user_id',$this->user->id)->first();
            $saleid = $sale->id;
            //销售员
            $arr["tuiGuang"] = $this->tuiGuang(2,$saleid,$start_time,$end_time);
            $arr["yuYue"] = $this->yuYue(2,$saleid,$start_time,$end_time);
            $arr["tiYan"] = $this->tiYan(2,$saleid,$start_time,$end_time);
            $arr["fuFei"] = $this->fuFei(2,$saleid,$start_time,$end_time);
            $arr["xuFei"] = $this->xuFei(2,$saleid,$start_time,$end_time);
            $arr["shiXiao"] = $this->shiXiao(2,$saleid,$start_time,$end_time);
            $arr["totalMoney"] = $this->totalMoney(2,$saleid,$start_time,$end_time);
            $arr["totalNum"] = $this->totalNum(2,$saleid,$start_time,$end_time);
        }
        $arr["shuRu"] = $this->shuRu($club_id,$start_time,$end_time);
        $arr["zhiChu"] = $this->zhiChu($club_id,$start_time,$end_time);
        $arr["sales"] = $this->sales($club_id,$start_time,$end_time);
        $arr["orderBy"] = $this->orderBySales($club_id,$saleid,$start_time,$end_time);
        $arr["type"] = $type;
        return returnMessage('200', '',$arr);
    }



    public function tuiGuang($type,$value,$start,$end){
        if($type ==1){
            $sql = "SELECT count(id) AS count from club_student WHERE is_delete=0 and club_id ='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }else{
            $sql = "SELECT count(id) AS count from club_student WHERE is_delete=0 and sales_id ='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function yuYue($type,$value,$start,$end){
        if($type == 1){
            $sql = "SELECT count(id) AS count from club_student_subscribe WHERE  is_delete=0 and club_id ='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }else{
            $sql = "SELECT count(id) AS count from club_student_subscribe WHERE  is_delete=0 and sales_id ='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }

        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function tiYan($type,$value,$start,$end){
        if($type ==1){
            $sql = "SELECT count(id) AS count from club_student_subscribe WHERE is_delete=0 and club_id='".$value."' and ex_status = 1 and updated_at >='".$start."' AND updated_at <'".$end."'";
        }else{
            $sql ="SELECT count(id) AS count FROM club_student_subscribe WHERE is_delete=0 and sales_id='".$value."' and ex_status = 1 and updated_at >='".$start."'AND updated_at <'".$end."'";
        }
       $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function fuFei($type,$value,$start,$end){
        if($type == 1){
            $sql = "SELECT count(id) AS count from club_student_payment WHERE pay_fee > 0 
                      AND is_delete=0 and club_id ='".$value."' 
                      AND payment_date >='".$start."'AND payment_date <'".$end."'";
        }else{
            $sql = "SELECT count(id) AS count from club_student_payment WHERE pay_fee > 0 
                      AND is_delete=0 and sales_id ='".$value."' 
                      AND payment_date >='".$start."'AND payment_date <'".$end."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function xuFei($type,$value,$start,$end){
        if($type == 1){
            $sql = "SELECT count(id) AS count from club_student_payment WHERE pay_fee > 0 
                      AND is_delete=0 and club_id ='".$value."' 
                      AND is_pay_again ='1' and payment_date >='".$start."'AND payment_date <'".$end."'";
        }else{
            $sql = "SELECT count(id) AS count from club_student_payment WHERE pay_fee > 0 
                      AND is_delete=0 and sales_id ='".$value."' 
                      AND is_pay_again ='1' and payment_date >='".$start."'AND payment_date <'".$end."'";
        }

        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function shiXiao($type,$value,$start,$end){
        if($type ==1 ){
            $sql = "SELECT count(id) AS count from club_student 
                      WHERE is_delete=0 and club_id ='".$value."' AND status='3' 
                      and updated_at >='".$start."'AND updated_at <'".$end."'";
        }else{
            $sql = "SELECT count(id) AS count from club_student 
                      WHERE is_delete=0 and sales_id ='".$value."' AND status='3' 
                      and updated_at >='".$start."'AND updated_at <'".$end."'";
        }

        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function shuRu($club_id,$start,$end){
        $sql = "SELECT SUM(coach_cost)+sum(coach_manage_cost) AS count 
                  from club_coach_cost_by_course  
                  WHERE is_delete=0 and club_id='".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function zhiChu($club_id,$start,$end){
        $sql = "SELECT SUM(money) as count 
                  from club_income_snapshot  
                  WHERE is_delete=0 and club_id='".$club_id."'";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function sales($club_id,$start,$end){
        $sql = "SELECT sales_id, sum(pay_fee) AS payMoney,count(id) AS count FROM club_student_payment WHERE is_delete=0 and club_id='".$club_id."' and created_at >='".$start."'AND created_at <'".$end."' group by sales_id ORDER BY payMoney DESC LIMIT 3";
        $res = DB::select($sql);
        if(count($res)>0){
            foreach ($res as $items){
                $saleid = $items->sales_id;
                $sale = ClubSales::where('id',$saleid)->select('sales_dept_id','sales_name')->first();
                if($sale){
                    $items->salesName = $sale->sales_name;
                    $sales_dept_id = $sale->sales_dept_id;
                    $parment = ClubDepartment::where('id',$sales_dept_id)->select('principal_id','name')->first();
                    if($parment){
                        $items->department = $parment->name;
                        $principal_id = $parment->principal_id;
                        $principal = ClubUser::where('id',$principal_id)->select('username')->first();
                        if($principal){
                            $items->principalName = $principal->username;
                        }

                    }

                }


            }
        }
        return $res;
    }


    public function totalMoney($type,$value,$start,$end){
        if($type ==1){
            $sql = "SELECT SUM(pay_fee) AS count from club_student_payment  WHERE is_delete=0 and club_id='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }else{
            $sql = "SELECT SUM(pay_fee) AS count from club_student_payment  WHERE is_delete=0 and sales_id='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }

        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function totalNum($type,$value,$start,$end){
        if($type ==1){
            $sql = "SELECT COUNT(pay_fee) AS count from club_student_payment  WHERE is_delete=0 and club_id='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }else{
            $sql = "SELECT COUNT(pay_fee) AS count from club_student_payment  WHERE is_delete=0 and sales_id='".$value."' and created_at >='".$start."'AND created_at <'".$end."'";
        }
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function orderBySales($club_id,$sales_id,$start,$end){
        $order = 1;
        $sql = "SELECT sales_id, sum(pay_fee) AS payMoney,count(id) AS count FROM club_student_payment WHERE is_delete=0 and club_id='".$club_id."' and created_at >='".$start."'AND created_at <'".$end."' group by sales_id ORDER BY payMoney DESC";
        $res = DB::select($sql);
        if(count($res)>0){
            foreach ($res as $key =>$item){
                if($sales_id == $item->sales_id){
                    $order = $key+1;
                }
            }
        }
        return $order;
    }

}
