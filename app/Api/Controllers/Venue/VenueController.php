<?php

namespace App\Api\Controllers\Venue;

use App\Facades\Util\Log;
use App\Http\Controllers\Controller;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubStudent\ClubStudent;
use App\Model\Venue\Venue;
use App\Model\Venue\VenueImage;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class VenueController extends Controller
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

    //1.场馆列表
    public function venuelist(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $search = isset($input['search']) ? $input['search'] : "";
        $provinceCode = isset($input['provinceCode']) ? $input['provinceCode'] : "";
        $cityCode = isset($input['cityCode']) ? $input['cityCode'] : "";
        $districtCode = isset($input['districtCode']) ? $input['districtCode'] : "";
        $status = isset($input['hideRomoved']) ? $input['hideRomoved'] : "";

        $pagePerNum = isset($input['pagePerNum']) ? $input['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($input['currentPage']) ? $input['currentPage'] : 1; //页数

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $club_id =$this->user->club_id;
        $venue = DB::table('club_venue');
        $venue->select('id','club_id as clubsId','name as venueName','english_name as venueNameEn','province','city','district','province_id as provinceCode',
            'city_id as cityCode','district_id as districtCode','address as addressDetail','english_address as addressEn','tel','price_in_app as appPrice','show_in_app as isShowApp',
            'be_for_sale as beForSale','percent_profit as percentProfit','description','traffic_info as trafficInfo','remark','status','longitude','latitude')->where('is_delete',0);
        if(strlen($search)>0){
            $venue->where(function ($query) use ($search) {
                $query->where('name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });
        }

        if(strlen($provinceCode)>0){
            $venue->where('province_id',$provinceCode);
            if(strlen($cityCode)>0){
                $venue->where('city_id',$cityCode);
                if(strlen($districtCode)>0){
                    $venue->where('district_id',$districtCode);
                }
            }

        }

        if(strlen($status)>0){
            if($status == 'true'){
                $venue->where('status',1);
            }
        }

        $venue->where('club_id',$club_id)->orderBy('student_count', 'desc');

        $venue = $venue->paginate($pagePerNum);


        $venue2 = DB::table('club_venue');
        $venue2->select('id','club_id as clubsId','name as venueName','english_name as venueNameEn','province','city','district','province_id as provinceCode',
            'city_id as cityCode','district_id as districtCode','address as addressDetail','english_address as addressEn','tel','price_in_app as appPrice','show_in_app as isShowApp',
            'be_for_sale as beForSale','percent_profit as percentProfit','description','traffic_info as trafficInfo','remark','status','longitude','latitude')->where('is_delete',0);
        if(strlen($search)>0){
            $venue2->where(function ($query) use ($search) {
                $query->where('name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });
        }

        if(strlen($provinceCode)>0){
            $venue2->where('province_id',$provinceCode);
            if(strlen($cityCode)>0){
                $venue2->where('city_id',$cityCode);
                if(strlen($districtCode)>0){
                    $venue2->where('district_id',$districtCode);
                }
            }

        }

        if(strlen($status)>0){
            if($status == 'true'){
                $venue2->where('status',1);
            }
        }

        $venue2->where('club_id',$club_id)->orderBy('id', 'desc');

        $mycount = $venue2->count();


        foreach($venue as $key=>$item){
            $item->studentCount= $this->studentCount($item->id);
            $item->averagePrice= $this->averagePrice($item->id);
            $class = ClubClass::where('venue_id', $item->id)->first();
            $course = ClubCourse::where('venue_id', $item->id)->first();
            $item->tips = count($class) || count($course) ? 1 : 0;
        }

        $venue = $venue->toArray();

        $data = $venue['data'];

        $score = [];
        foreach ($data as $key => $value) {
            $score[$key] = $value->studentCount;
        }
        unset($value);
        array_multisort($score, SORT_DESC, $data);

        $result = array();
        $result['total'] = $mycount;

        $result['data'] = $data;
        /*$result['data'] = $venue->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'clubsId' => $item->clubsId,
                'venueName' => $item->venueName,
                'venueNameEn' => $item->venueNameEn,
                'province' => $item->province,
                'city' => $item->city,
                'district' => $item->district,
                'provinceCode' => $item->provinceCode,
                'cityCode' => $item->cityCode,
                'districtCode' => $item->districtCode,
                'addressDetail' => $item->addressDetail,
                'addressEn' => $item->addressEn,
                'tel' => $item->tel,
                'appPrice' => $item->appPrice,
                'isShowApp' => $item->isShowApp,
                'beForSale' => $item->beForSale,
                'percentProfit' => $item->percentProfit,
                'description' => $item->description,
                'trafficInfo' => $item->trafficInfo,
                'remark' => $item->remark,
                'status' => $item->status,
                'longitude' => $item->longitude,
                'latitude' => $item->latitude,
                'studentCount' => $item->studentCount,
                'averagePrice' => $item->averagePrice,
                'tips' => $item->tips,
            ];

            return $result;
        });*/

        $result["studentCount"] =$this->allstudentCount($club_id);
        return returnMessage('200', '',$result);
    }

    function objectToArray($object) {
        //先编码成json字符串，再解码成数组
        return json_decode(json_encode($object), true);
    }


    public function studentCount($venue_id){
        $sql = "SELECT COUNT(a.id) as count 
                  from club_class_student as a 
                  left join club_student as b on a.student_id = b.id
                  WHERE a.venue_id = ".$venue_id ." and a.is_delete =0 and b.status <> 3";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }


    public function averagePrice($venue_id){
        $sql = "SELECT SUM(money) as money, COUNT(*) as count from club_income_snapshot WHERE venue_id = ".$venue_id." and is_delete =0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        $money = $res[0]->money;
        if($count >0){
            $average = $money/$count;
        }else{
            $average= 0;
        }
        return $average;
    }


    public function allstudentCount($club_id){
        $sql = "SELECT count(a.id) as count 
                  FROM club_class_student as a
                  left join club_student as b on a.student_id = b.id
                  WHERE a.club_id =".$club_id ." and a.is_delete =0 and b.status <> 3";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    // 2.场馆添加
    public function venueadd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueName' => 'required|string',
            'province' => 'required|string',
            'provinceCode' => 'required|numeric',
            'city' => 'required|string',
            'cityCode' => 'required|numeric',

            'district' => 'required|string',
            'districtCode' => 'required|numeric',
            'addressDetail' => 'required|string',

            'tel' => 'required|string',
            'appPrice' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venueDesc = isset($input['venueDesc']) ? $input['venueDesc'] : "";
        $remark = isset($input['remark']) ? $input['remark'] : "";
        $venueNameEn = isset($input['venueNameEn']) ? $input['venueNameEn'] : "";
        $addressDetail = isset($input['addressDetail']) ? $input['addressDetail'] : "";
        $addressEn = isset($input['addressEn']) ? $input['addressEn'] : "";

        $club_id = $this->user->club_id ?? 0;
        $venue = new Venue();
        $res = $venue->where("name",$input['venueName'])->get();
        $address = $input['province'].$input['city'].$input['district'].$input['addressDetail'];
        $list = $this->getLatitudeLongitude($address);
        if($list["code"] != 200){
            return returnMessage('1307', config('error.venue.1307'));
        }

        if(count($res)>0){
            return returnMessage('1305', config('error.venue.1305'));
        }else{
            $venue->club_id = $club_id;
            $venue->name = $input['venueName'] ;
            $venue->english_name = $venueNameEn;
            $venue->province = $input['province'];
            $venue->province_id = $input['provinceCode'];
            $venue->city = $input['city'];
            $venue->city_id = $input['cityCode'];

            $venue->district = $input['district'];
            $venue->district_id = $input['districtCode'];
            $venue->address = $addressDetail;
            $venue->english_address = $addressEn;
            $venue->tel = $input['tel'];

            $venue->price_in_app = $input['appPrice'];
            $venue->description = $venueDesc;

            $venue->remark = $remark;
            $venue->status = 1;
            $venue->be_for_sale = 0;
            if($list["code"] == 200) {
                $venue->longitude = isset($list['longitude'])?$list['longitude']:'';
                $venue->latitude = isset($list['latitude'])?$list['latitude']:'';
            }
            $venue->save();
        }

        return returnMessage('200', '');
    }

    public function getLatitudeLongitude($address)
    {
        $header[] = 'Referer: http://lbs.qq.com/webservice_v1/guide-suggestion.html';
        $header[] = 'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.139 Safari/537.36';
        $url ="http://apis.map.qq.com/ws/place/v1/suggestion/?&region=&key=SJRBZ-4TI34-KCJUV-D53O4-H3G7Q-7HFLN&keyword=".$address;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch,CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);

        $output = curl_exec($ch);

        curl_close($ch);

        $result = json_decode($output,true);
        if (!empty($result['data']) && $result['status']==0) {


            $list['address'] = isset($result['data'][0]['address']) ? $result['data'][0]['address'] : '';
            $list['latitude'] = isset($result['data'][0]['location']['lat']) ? $result['data'][0]['location']['lat'] : '';
            $list['longitude'] = isset($result['data'][0]['location']['lng']) ? $result['data'][0]['location']['lng'] : '';
            if (strlen($list['latitude']) > 0 && strlen($list['longitude']) > 0) {
                $list['code'] = 200;
                return $list;
            }
        }
        return returnMessage('1307', config('error.venue.1307'));
    }
    // 3.场馆修改
    public function editVenue(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'venueName' => 'required|string',

            'province' => 'required|string',
            'provinceCode' => 'required|numeric',
            'city' => 'required|string',
            'cityCode' => 'required|numeric',

            'district' => 'required|string',
            'districtCode' => 'required|numeric',
            'addressDetail' => 'required|string',

            'tel' => 'required|string',
            'appPrice' => 'required|string',

        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venueDesc = isset($input['venueDesc']) ? $input['venueDesc'] : "";
        $remark = isset($input['remark']) ? $input['remark'] : "";

        $venueNameEn = isset($input['venueNameEn']) ? $input['venueNameEn'] : "";
        $addressEn = isset($input['addressEn']) ? $input['addressEn'] : "";
        $address = $input['province'].$input['city'].$input['district'].$input['addressDetail'];
        $list = $this->getLatitudeLongitude($address);
        if($list["code"] != 200){
            return returnMessage('1307', config('error.venue.1307'));
        }
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            $venue->name = $input['venueName'] ;
            $venue->english_name = $venueNameEn;
            $venue->province = $input['province'];
            $venue->province_id = $input['provinceCode'];
            $venue->city = $input['city'];
            $venue->city_id = $input['cityCode'];

            $venue->district = $input['district'];
            $venue->district_id = $input['districtCode'];
            $venue->address = $input['addressDetail'];
            $venue->english_address = $addressEn;
            $venue->tel = $input['tel'];

            $venue->price_in_app = $input['appPrice'];
            $venue->description = $venueDesc;

            $venue->remark = $remark;
            if($list["code"] == 200) {
                $venue->longitude = isset($list['longitude'])?$list['longitude']:'';
                $venue->latitude = isset($list['latitude'])?$list['latitude']:'';
            }

            $venue->update();
            return returnMessage('200', '修改成功');
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
    }

    //4.场馆删除
    public function deleteVenue(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue = Venue::find($input['venueId']);
        $count = ClubStudent::where('venue_id',$input['venueId'])->count();
        if($count >0){
            return returnMessage('1306', config('error.venue.1306'));
        }
        if(! empty($venue)){
            try{
                $venue->is_delete=1;
                $venue->update();
                return returnMessage('200','删除成功');
            }catch (\Exception $e){
                return returnMessage('400','删除失败');
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }

    }

    //6.场馆详情
    public function venueDetail(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            $res = Venue::where("id",$input['venueId']) ->select(
                'id','club_id As clubId','name','english_name As englishName','province','city','district','address',
                'english_address As englishAddress','tel','price_in_app As priceInApp','show_in_app As showInApp','be_for_sale As beForSale','percent_profit As percentProfit',
                'description','traffic_info As trafficInfo','remark','status','province_id As provinceCode','city_id As cityCode','district_id As districtCode')->first();
            return returnMessage('200', '详情',$res);
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
    }

    //添加场馆名称验证
    public function modifyVenueName(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubsId' => 'required|numeric',
            'name' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue = Venue::where('club_id',$input['clubsId'])->where('name',$input['name'])->get();
        if(count($venue)>0){
            return returnMessage('1305', config('error.venue.1305'));
        }else{
            return returnMessage('200', '');
        }
    }


    //7.app是否显示
    public function appShow(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'isAppShow'  => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $show_in_app = $input['isAppShow'];
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            // '0=不显示;1=显示'
            $type = 0;
            if($show_in_app == 0){
                $type = 1;
            }
            $item = Venue::where('id', $venue_id)->where('show_in_app', $type)->exists();
            if ($item) {
                try{
                    Venue::where('id',$venue_id)->update(['show_in_app'=>intval($show_in_app)]);
                    return returnMessage('200', '');
                }catch (\Exception $e){
                    return returnMessage('1304',config('error.venue.1304'));
                }
            }else{
                return returnMessage('1303', config('error.venue.1303'));
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
    }

    //8.是否促销
    public function promotions(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'isPromotions'  => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $isPromotions = $input['isPromotions'];
        $venue = Venue::find($venue_id);
        if(! empty($venue)){
            // 是否促销 0=否;1=是
            try{
                $venue->be_for_sale = intval($isPromotions);
                $venue->update();
                return returnMessage('200', '');
            }catch (\Exception $e){
                return returnMessage('1304',config('error.venue.1304'));
            }

        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
    }

    //5.生效/失效
    public function failure(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'isFailure'  => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $status = $input['isFailure'];
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            // 0=失效;1=生效'
            $type = 0;
            if($status == false){
                $type = 1;
            }
            $item = Venue::where('id', $venue_id)->where('status', $type)->exists();
            if ($item) {
                $sql = "SELECT COUNT(class.id) as count 
                          FROM club_class_student as class 
                          LEFT JOIN club_student as stu on class.student_id = stu.id 
                          where class.venue_id ='".$venue_id."' and class.is_delete=0
                          and stu.status <> 3";
                $student = DB::select($sql);
                $count = intval($student[0]->count);
                if($count >0){
                    return returnMessage('1308',config('error.venue.1308'));
                }else{
                    //失效生效场馆需同时操作班级与课程对应 开启事务 jesse 2018/09/29
                    //todo::需要记录日志 jesse
                    DB::beginTransaction();
                    try{
                        Venue::where('id',$venue_id)->update(['status'=>$status]);
                        ClubClass::where('venue_id',$venue_id)->update(['status' => $status]);
                        ClubCourse::where('venue_id',$venue_id)->update(['status' => $status]);
                        DB::commit();
                        return returnMessage('200', '');
                    }catch (\Exception $e){
                        DB::rollBack();
                        return returnMessage('1304',config('error.venue.1304'));
                    }
                }
            }else{
                return returnMessage('1303', config('error.venue.1303'));
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
}


    //9.修改分成比例
    public function modifySiteDivided(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'percentProfit'  => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $percent_profit = $input['percentProfit'];
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            try{
                Venue::where('id',$venue_id)->update(['percent_profit'=>floatval($percent_profit)]);
                return returnMessage('200', '');
            }catch (\Exception $e){
                return returnMessage('1304',config('error.venue.1304'));
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
    }


    //10.修改交通信息
    public function modifyTrafficInfo(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'trafficInfo'  => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $traffic_info = $input['trafficInfo'];
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            try{
                Venue::where('id',$venue_id)->update(['traffic_info'=>$traffic_info]);
                return returnMessage('200', '');
            }catch (\Exception $e){
                return returnMessage('1304',config('error.venue.1304'));
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }

    }
    //10.1修改描述
    public function descriptionedit(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'description'  => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $description = $input['description'];
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            try{
                Venue::where('id',$venue_id)->update(['description'=>$description]);
                return returnMessage('200', '');
            }catch (\Exception $e){
                return returnMessage('1304',config('error.venue.1304'));
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }

    }
    //10.2修改备注
    public function remarkedit(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'remark'  => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venue_id = $input['venueId'];
        $remark = $input['remark'];
        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            try{
                Venue::where('id',$venue_id)->update(['remark'=>$remark]);
                return returnMessage('200', '');
            }catch (\Exception $e){
                return returnMessage('1304',config('error.venue.1304'));
            }
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }

    }
//    11.场馆图片列表
    public function venueImageList(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $venue = Venue::find($input['venueId']);
        if(! empty($venue)){
            $res = VenueImage::where("venue_id",$input['venueId']) ->select(
                'id','file_path As filePath','sort','is_delete As isDelete',
                'is_show As isShow')->where("is_delete",0)->get();
            if(count($res) > 0){
                foreach ($res as $key => $val){
                    $res[$key] = [
                        'id' => $val->id,
                        'filePath' => $val->filePath,
                        'sort' => $val->sort,
                        'isDelete' => $val->isDelete,
                        'isShow' => $val->isShow
                    ];
                   
                }
            }
            return returnMessage('200', '列表',$res);
        }else{
            return returnMessage('1302', config('error.venue.1302'));
        }
    }

    //    运动类型选择
    public function sportTypeSelect(Request $request){

    }


    //    12.场馆图片新增
    public function venueImageAdd(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'filePath' => 'required|string',
            'sort' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $venue = new VenueImage();
        $venue->file_path = env('IMG_DOMAIN').$input['filePath'] ;
        $venue->venue_id = $input['venueId'];
        $venue->is_delete = 0;
        $venue->is_show = 1;
        $venue->sort = $input['sort'];

        $venue->save();
        return returnMessage('200', '');
    }

    //    13.场馆图片删除
    public function venueImageDel(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'imageId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $venue = VenueImage::find($input['imageId']);
        if(! empty($venue)){
            $venue->is_delete = 1 ;
            $venue->update();
            return returnMessage('200', '');
        }else{
            return returnMessage('200', '修改失败');
        }
    }


    /**
     * 场馆快照
     * @param Request $request
     * @return array
     * @date 2018/9/27
     * @author jesse
     */
    public function history(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'startTime'  => 'required|date',
            'endTime'  => 'required|date',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $type = isset($input['type']) ? $input['type'] : "2";
        $venueId = isset($input['venueId']) ? $input['venueId'] : "";
        $startTime = isset($input['startTime']) ? $input['startTime'] : date('Y',time()).'-01-01 00:00:00';
        $endTime = isset($input['endTime']) ? $input['endTime'] :  date("Y-m-d h:i:s");
        //$type 分类 1.按周统计 2.按月统计
        $res = getSummer(strtotime($startTime),strtotime($endTime),$type);
        $result =array();
        foreach ($res as $items){
            $startDetailTime = $items["start"].' '.'00:00:00';
            $endDetailTime = $items["end"].' '.'23:59:59';
            $venueFree = $this->venueFree($venueId, $startDetailTime, $endDetailTime);
            $venueTotal = $this->venueTotal($venueId, $startDetailTime, $endDetailTime);
            $venueActite = $venueTotal - $venueFree;
            $items["venueActite"] = $venueActite;
            $items["venueFree"] = $venueFree;
            $items["venueTotal"] = $venueTotal;
            $result[] = $items;
        }
        return returnMessage('200', '列表',$result);
    }

    public function venueHistory(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'startTime'  => 'required|date',
            'endTime'  => 'required|date',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $type = isset($input['type']) ? $input['type'] : "2";
        $startTime = isset($input['startTime']) ? $input['startTime'] : date('Y',time()).'-01-01 00:00:00';
        $endTime = isset($input['endTime']) ? $input['endTime'] :  date("Y-m-d h:i:s");
        //$type 分类 1.按周统计 2.按月统计
        $res = getSummer(strtotime($startTime),strtotime($endTime),$type);
        $result =array();
        foreach ($res as $items){
            $venueFree = $this->allvenueFree($items["start"],$items["end"]);
            $venueTotal = $this->allvenueTotal($items["start"],$items["end"]);
            $venueActite = $venueTotal - $venueFree;
            $items["venueActite"] = $venueActite;
            $items["venueFree"] = $venueFree;
            $items["venueTotal"] = $venueTotal;
            $result[] = $items;
        }
        return returnMessage('200', '列表',$result);
    }
    //冻结
    public function venueFree($venueId,$start,$end){
        $sql = "SELECT count(*) AS count 
                  FROM club_student 
                  where venue_id=".$venueId." and is_freeze=1 
                  and created_at >='".$start."' and status <> 3 
                  AND created_at <'".$end."' and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //全部
    public function venueTotal($venueId,$start,$end){
        $sql = "SELECT COUNT(id) as count 
                  from club_student 
                  WHERE venue_id=".$venueId." and created_at >='".$start."' 
                  AND created_at <'".$end."' and is_delete=0 and status <> 3";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //冻结
    public function allvenueFree($start,$end){
        $sql = "SELECT count(*) AS count 
                  from club_student 
                  where club_id = ".$this->user->club_id." 
                  and is_freeze= '1' and created_at >='".$start."' 
                  AND created_at <'".$end."' and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //全部
    public function allvenueTotal($start,$end){
        $sql = "SELECT COUNT(id) as count 
                  from club_student 
                  WHERE club_id = ".$this->user->club_id." 
                  and created_at >='".$start."'AND created_at <'".$end."' and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //15.班级列表
    public function classList(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $pagePerNum = isset($input['pagePerNum']) ? $input['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($input['currentPage']) ? $input['currentPage'] : 1; //页数
        $search = isset($input['search']) ? $input['search'] : "";
        $status = isset($input['status']) ? $input['status'] : 1;
        $startTime = isset($input['startTime']) ? $input['startTime'] : '';
        $endTime = isset($input['endTime']) ? $input['endTime'] : '';
        $venueId = isset($input['venueId']) ? $input['venueId'] : '';

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $sale = DB::table('club_class')->select('id','name','show_in_app AS showInApp','student_limit AS studentLimit','pay_tag_name AS payTagName')->where('is_delete',0);

        if(strlen($search)>0){
            $sale->where(function ($query) use ($search) {
                $query->where('name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });
        }
        if(strlen($status)>0 && ($status != 2 )){
            $sale->where('status',$status);
        }
        if(strlen($startTime)>0){
            $sale->where('created_at','>=',$startTime);
        }
        if(strlen($endTime)>0){
            $endTime =  date('Y-m-d H:i:s',strtotime($endTime)+86399);
            $sale->where('created_at','<=',$endTime);
        }

        $sale->where('venue_id',$venueId)->orderBy('id', 'desc');

        $sale = $sale->paginate($pagePerNum);


        $sale2 = DB::table('club_class')
            ->select('id','name','status','show_in_app AS showInApp',
                'student_limit AS studentLimit','pay_tag_name AS payTagName');

        if(strlen($search)>0){
            $sale2->where(function ($query) use ($search) {
                $query->where('name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });

        }
        if(strlen($status)>0 && ($status != 2 )){
            $sale2->where('status',$status);
        }
        if(strlen($startTime)>0){
            $sale2->where('created_at','>=',$startTime);
        }
        if(strlen($endTime)>0){
            $sale2->where('created_at','<=',$endTime);
        }

        $sale2->where('venue_id',$venueId)->orderBy('id', 'desc');

        $resu2 = $sale2->get();

       foreach ($sale as $item){
           $all =$this->classtoallstudent($item->id,$venueId);
           $free = $this->free($item->id,$venueId);
//           -编号  -班级  平均课单价  时间  活跃/冻结  -招生上限  活跃学员年龄   总开课   -APP端展示   -缴费方案标签
           $item->totalclass = $this->totalclass($item->id,$venueId);    //总开课
           $item->free = intval($free);    //冻结

           $item->active = ClubClassStudent::whereHas('student', function ($query) {
               return $query->where([['status', 1], ['is_freeze', 0], ['is_delete', 0]]);
           })->where([['class_id', $item->id], ['is_delete', 0]])->count();   //活跃


           $item->avgprice = $this->avgprice($item->id,$venueId);    //平均课单价
           $item->classtime = $this->getclasstime($item->id);    //课程时间
           $item->age = $this->getage($item->id);
       }

        foreach ($resu2 as $item2){
            $all =$this->classtoallstudent($item2->id,$venueId);
            $item2->statuscount = $all;
            $item2->totalclass = $this->totalclass($item2->id,$venueId);    //总开课
        }

        $result = array();
        $result['total'] = count($resu2);
        $result['data'] = $sale->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'name' => $item->name,
                'showInApp' => $item->showInApp,
                'studentLimit' => $item->studentLimit,
                'payTagName' => $item->payTagName,
                'totalclass' => $item->totalclass,
                'free' => $item->free,
                'active' => $item->active,
                'avgprice' => $item->avgprice,
                'classtime' => $item->classtime,
                'age' => $item->age,
            ];

            return $result;
        });

        $tostudent = 0;
        $youxiaoclass = 0;
        $shixiaoclass = 0;
        $totalClass = 0;
        foreach ($resu2 as $item){
            if($item->status == 0){
                $youxiaoclass += $item->statuscount;
            }elseif ($item->status == 1){
                $shixiaoclass += $item->statuscount;
            }
            $totalClass += $item->totalclass;
        }
        $tostudent = $youxiaoclass+$shixiaoclass;

        $result["count"] =array("totalClass"=>intval($tostudent),"effClass"=>$youxiaoclass,"invClass"=>intval($shixiaoclass),"tClass" =>intval($totalClass));

        return returnMessage('200', '请求成功', $result);
    }

//    班级总数--统计
    public function Classtotal($venueId){
        $sql = "SELECT COUNT(id) AS count from club_class WHERE venue_id= ".$venueId." and is_delete = 0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function getastudentctive($class){
        $sql="SELECT count(cs.student_id) as count FROM club_class_student AS cs LEFT JOIN club_student AS pm ON pm.id = cs.student_id WHERE cs.class_id ='".$class."' AND pm.is_freeze=0 AND pm.`status`=1 and pm.is_delete=0";

        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    public function getage($class){
        $sql = "SELECT MIN(s.age) as minage,MAX(s.age) as maxage FROM club_student_payment AS pm INNER JOIN club_class_student AS cs ON pm.student_id = cs.student_id INNER JOIN club_student AS s ON cs.student_id = s.id where cs.class_id ='".$class."' and pm.is_delete=0";
        $res = DB::select($sql);
        $str = '';
        $minage = $res[0]->minage;
        $maxage = $res[0]->maxage;
        $str = $minage."-".$maxage;
        return $str;
    }

    //    班级有效--统计
    public function effClass($venueId){
        $sql = "SELECT COUNT(id) AS count from club_class WHERE status=1 and venue_id= ".$venueId." and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
    //    班级有效--统计---总的统计
    public function alltotalClass($venueId){
        $time = date('Y-m-d H:i:s',time());
        $sql = "SELECT count(id) as count from club_course WHERE status = '1' and venue_id= '".$venueId."' and day <'".$time."' and is_delete=0" ;
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }


    //    所有失效--统计
    public function allStudent($venueId){
        $sql = "SELECT COUNT(id) AS count from club_student WHERE venue_id= ".$venueId." and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //冻结学员总数
    public function countStudentVenue($venueId){
        $sql = "SELECT count(*) as count FROM club_student_freeze as freeze LEFT JOIN club_student as student on freeze.student_id = student.id WHERE student.venue_id =".$venueId." and freeze.is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

//总开课--班级
    public function totalclass($classid,$venueId){
        $sql = "SELECT count(id) as count from club_course WHERE class_id= ".$classid." and day <= '". date("Y-m-d", time()) . "' and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//所有
    public function classtoallstudent($classid,$venueId){
        $sql = "SELECT COUNT(id) AS count from club_student WHERE main_class_id = ".$classid." and venue_id= ".$venueId." and is_delete =0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//冻结
    public function free($classid,$venueId){
        $sql = "SELECT count(clstu.id) as count FROM club_class_student as clstu LEFT JOIN club_student as student on clstu.student_id = student.id WHERE clstu.class_id = ".$classid." and clstu.venue_id =".$venueId."  and student.is_freeze =1 and clstu.is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }
//平均课单价    //1.课单价 club_income_snapshot
    public function avgprice($classid,$venueId){
        $sql = "SELECT COUNT(id) AS count,SUM(money) as money from club_income_snapshot WHERE class_id= ".$classid." and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        $money = $res[0]->money;
        if($count ==0){
            $price =0;
        }else{
            $price = round($money/$count,2);
        }
        return $price;
    }
//班级上课时间
    public function getclasstime($classid){
        $sql = "SELECT start_time,end_time,day from club_class_time WHERE class_id= ".$classid." and is_delete=0";
        $res = DB::select($sql);
        return $res;
    }
    //16.汇总信息
    public function summaryInfo(Request $request){
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $type = isset($input['type']) ? $input['type'] : "1";
        $venueId = isset($input['venueId']) ? $input['venueId'] : "";
        $startTime = isset($input['startTime']) ? $input['startTime'] : date('Y',time()).'-01-01 00:00:00';
        $endTime = isset($input['endTime']) ? $input['endTime'] : date("Y-m-d h:i:s");

//        $type 分类 1.按周统计 2.按月统计
        $res = getSummer(strtotime($startTime),strtotime($endTime),$type);
        $result = array();
        foreach ($res as $items){
            $income = $this->income($venueId,$items["start"],$items["end"]);
            $jlDisburse = $this->jlDisburse($venueId,$items["start"],$items["end"]);
            $items["income"] = $income;
            $items["jlDisburse"] = $jlDisburse;
            $items["profit"] =intval($income - $jlDisburse);
            $result[] = $items;
        }
        $tincome = $this->income($venueId,$startTime,$endTime);
        $tjlDisburse = $this->jlDisburse($venueId,$startTime,$endTime);
        $retu = array();
        $retu["data"] = $result;
        $retu["total"] =array("income"=>$tincome,"jlDisburse"=>$tjlDisburse,"profit"=>intval($tincome - $tjlDisburse));
        return returnMessage('200', '请求成功', $retu);
    }


    //收入   签到收入  club_income_snapshot   money
    public function income($venueId,$start,$end){
        $sql = "SELECT SUM(money) as count from club_income_snapshot WHERE venue_id=".$venueId." and created_at >='".$start."'AND created_at <'".$end."' and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }

    //教练支出   club_coach_cost_by_course
    public function jlDisburse($venueId,$start,$end){
        $sql = "SELECT SUM(coach_manage_cost) as count from club_coach_cost_by_course WHERE venue_id= ".$venueId." and  created_at >='".$start."' AND created_at < '".$end."' and is_delete=0";
        $res = DB::select($sql);
        $count = $res[0]->count;
        return $count;
    }







}

