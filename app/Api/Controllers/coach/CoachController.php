<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/27
 * Time: 9:35
 */

namespace App\Api\Controllers\Coach;

use App\Facades\Coach\Coach;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubCoach\ClubCoach;
use App\Model\ClubCoachCostSnapshot\ClubCoachCostSnapshot;
use App\Model\ClubCoachImage\ClubCoachImage;
use App\Model\ClubCoachRewardPenalty\ClubCoachRewardPenalty;
use App\Model\ClubCoachVideo\ClubCoachVideo;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourseCoach\ClubCourseCoach;
use App\Model\ClubUser\ClubUser;

use App\Http\Controllers\Controller;
use App\Model\ClubVenue\ClubVenue;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;


class CoachController extends Controller
{
    /**
     * @var ClubUser
     */
    protected $user;

    /**
     * CoachController constructor.
     * @param ClubUser $clubUser
     */
    public function __construct(ClubUser $clubUser)
    {
        $this->user = $clubUser;
    }

    /**
     * 获取教练列表与筛选
     * @param Request $request
     * @return array
     */
    public function getCoachList(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'search' => 'nullable|string',
            'status' => 'nullable|numeric',
            'type' => 'nullable|numeric',
            'year' => 'nullable|numeric',
            'month' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('101', '非法操作');
        }

        $clubId = $data['user']['club_id'];
        $search = isset($data['search']) ? $data['search'] : "";
        $status = isset($data['status'])? (int)$data['status'] : 0;
        $type = isset($data['type']) ? $data['type'] : 0;
        $year = isset($data['year']) ? $data['year'] : 0;
        $month = isset($data['month']) ? $data['month'] : 0;
        $date = $year.'-'.$month;
        $pagePerNum = $data['pagePerNum'];
        $currentPage = $data['currentPage'];

        $coach = ClubCoach::with('reward', 'user', 'course', 'cost_snapshot')
            ->where(function ($query) use ($search) {
                if(!empty($search)){
                    return $query->where('id',$search)->orWhere('name', 'like', '%'.$search.'%')->orWhere('tel', $search);
                }
            })
            ->where(function ($query) use ($status) {
                if (!empty($status)) {
                    return $query->where('status', $status == 2 ? 0 : $status);
                }
            })
            ->where(function ($query) use ($type) {
                if (!empty($type)) {
                    return $query->where('type', $type);
                }
            })
            ->where('club_id', $clubId)
            ->where('is_delete',0)
            ->whereBetween('created_at',
                [date('Y-m-01 00:00:00', strtotime($date)),
                    date('Y-m-d 23:59:59', strtotime($date.'+ 1 month - 1 day'))])
            ->orderBy('id','desc')
            ->paginate($pagePerNum, ['*'], 'currentPage', $currentPage);

        // 总计数据
        $list['validPerson'] = $coach->total();
        $list['totBaseSalary'] = Coach::getTotBaseSalary($clubId, $year, $month);
        $list['ManagePrice'] = Coach::getManagePrice($clubId, $year, $month);
        $list['totCourseTime'] = Coach::getTotCourseTime($clubId, $year, $month);
        $list['totRuleTime'] = Coach::getTotRuleTime($clubId, $year, $month);
        $list['totPenalty'] = Coach::getTotPenalty($clubId, $year, $month);
        $list['totOtPrice'] = Coach::getTotOtPrice($clubId, $year, $month);
        $list['totMonthPrice'] = Coach::getTotMonthPrice($clubId, $year, $month);
        if ($list['totMonthPrice'] == 0 || $list['totRuleTime'] == 0) {
            $list['RulePrice'] = 0;
        } else {
            $list['RulePrice'] = $list['totMonthPrice']/$list['totRuleTime'];
        }

        // 列表数据
        $list['totalNum'] = $coach->total();
        $list['result'] = $coach->transform(function ($items) use ($clubId, $search, $type, $status, $year, $month, $date) {
            $arr['id'] = $items->id;
            $arr['name'] = $items->name;
            $arr['type'] = $items->type == 1 ? '全职' : '兼职';
            $arr['deptName'] = !empty($items->user->dept_name) ? $items->user->dept_name : "";
            $arr['basicSalary'] = !empty($items->basic_salary) ? $items->basic_salary : 0;
            $arr['courseTime'] = !empty($items->course_time) ? $items->course_time : 0;
            $arr['otPrice'] = !empty($items->ot_price) ? $items->ot_price : 0;
            $arr['ruleTime'] = Coach::getOneCoachRulePrice($clubId, $year, $month, $items->id);
            $arr['rewardPenalty'] = Coach::getOneCoachTotPenalty($clubId, $year, $month, $items->id);
            $arr['totOtPenalty'] = Coach::getOneCoachTotOtPrice($clubId, $year, $month, $items->id);
            $arr['monthPrice'] = Coach::getOneCoachTotMonthPrice($clubId, $year, $month, $items->id);
            $arr['tel'] = $items->tel;
            $arr['birthday'] = !empty($items->birthday) ? $items->birthday : "";
            $arr['country'] = !empty($items->country) ? $items->country : "";
            $arr['isEdit'] = $this->isEdit($date);
            $remark = ClubCoachRewardPenalty::where('club_id',$clubId)->where('coach_id', $items->id)->where('year',$year)->where('month',$month)->value('remark');
            $arr['remark'] = !empty($remark) ? $remark : "";
            $arr['status'] = $items->status;
            return $arr;
        });

        return returnMessage('200','',$list);
    }

    /**
     * isEdit
     * @param $date
     * @return bool
     * @date 2018/10/10
     * @author edit jesse
     */
    public function isEdit($date)
    {
        $day = date('d');   //当前日
        $month = date('m'); //当前月
        //判断是否搜索上月
        $lastMonth = date('m', strtotime($date.'-'.'01 + 1 month'));
        $isLastMonth = $month == $lastMonth ? 1 : 0;
        $isLastMoreMonth = $month > $lastMonth && !$isLastMonth ? 1 : 0;
        if ($isLastMoreMonth) {
            return 0;
        }
        if ($day >= 15 && $isLastMonth) {
            return 0;
        }
        return 1;
    }

    //获取对应月份课程
    public function course ($id,$clubId,$date)
    {
        return ClubCourseCoach::where('club_id',$clubId)
            ->where('coach_id',$id)
            ->where('status',1)
            ->where('is_delete',0)
            ->whereHas('course',function ($query) use($date,$clubId){
                $query->where('day','like',$date.'%')->where('status',1)->where('club_id',$clubId);
            })
            ->count();
    }


    //获取类型名称
    public function getTypeName($type){
        $typeName = '';
        if($type == 1){
            $typeName = '全职';
        }elseif($type == 2){
            $typeName = '兼职';
        }
        return $typeName;
    }

    //获取加班费
    public function getOtPenalty($course,$ruleCourse,$otPrice){
        if($course < $ruleCourse){
            $price = ($ruleCourse-$course)*$otPrice;
        }else{
            $price = 0;
        }
        return $price;
    }

    //获取对应年月的奖罚
    public function  getReward($data,$year,$month,$clubId){
        $reward = 0;
        foreach ($data as $val){
            if($val->year == $year && $val->month == $month && $val->club_id = $clubId){
                $reward = $val->reward_penalty;
            }
        }
        return $reward;
    }

    //累加管理费
    public function getManagePrice($id,$clubId,$date){

        $managePrice  = ClubCourseCoach::where('club_id',$clubId)
            ->where('coach_id',$id)
            ->where('status',1)
            ->where('is_delete',0)
            ->whereHas('course',function ($query) use($date,$clubId){
                $query->where('day','like',$date.'%')
                    ->where('status',1)
                    ->where('club_id',$clubId);
            })->sum('manage_cost');
        return $managePrice;
    }


    /**
     * 查看教练信息
     */
    public function getCoach(Request $request){

        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'Numeric|required',
            'year'=> 'required|Numeric',
            'month' => 'required|Numeric'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }

         $id = isset($data['id']) ? $data['id'] : '';
         $clubId = $data['user']['club_id'];
         $year = $data['year'];
         $month = $data['month'];
         $date = $year.'-'.$month;
//         $final_salary = 0;

         $result['totCourse'] = $this->course($id,$clubId,$date); //执勤次数
         $coach = ClubCoach::where('id',$id)->with('user')->first();

         $result['totSalary'] = $this->totalPrice($id,$year,$month,$date,$clubId);//总金额
         $result['result'] = [
             'name'=> $coach->name,
             'country' => $coach->country,
             'id' => $coach->id,
             'salary' => $coach->basic_salary,
             'deptName' => count($coach->user) ? $coach->user->dept_name : '',
             'otPrice' => $coach->ot_price,
             'birthday' => $coach->birthday,
             'courseTime' => $coach->course_time,
             'tel' => $coach->tel,
             'type' => $coach->type,
             'description' => $coach->description,
             'englishName' => count($coach->user) ? $coach->user->english_name : '',
             'wxAccount' => $coach->wx_account,
         ];

        $clubModelImage = new ClubCoachImage();
        $clubModelVideo = new ClubCoachVideo();
        $image = getUploadKey($id,$clubModelImage,'coach_id');
        $video = getUploadKey($id,$clubModelVideo,'coach_id');
        $result['images'] = $image;
        $result['video'] = $video;

        return returnMessage('200','请求成功',$result);

    }
    //当月教练总金额
    protected function totalPrice($id,$year,$month,$date,$clubId){
        $coach = ClubCoach::where('id',$id) ->where('is_delete',0)->with('reward','user','course','cost_snapshot')->first();
        $ruleTime = $this->course($coach->id,$clubId,$date);
        $reward = $this->getReward($coach->reward,$year,$month,$clubId);
        $otPenalty = $this->getOtPenalty($coach->course_time,$ruleTime,$coach->ot_price);
        $monthPrice = $coach->basic_salary+$reward+$otPenalty;
        return $monthPrice;
    }

    /**
     * 修改教练信息/英文名/简介/微信
     * @param Request $request
     * @return array
     */
    public function editCoach(Request $request){
            $data = $request->all();
            $validate  = Validator::make($data,[
                'id' => 'Numeric|required',
                'country' => 'nullable|String',
                'birthday' => 'nullable|date',
                'basicSalary'=> 'nullable|Numeric',
                'otPrice' => 'nullable|Numeric',
                'courseTime' => 'nullable|Numeric',
                'englishName' => 'alpha|nullable',
                'description' => 'String|nullable|Max:200',
                'wxAccount' => 'String|nullable'

            ]);
            if($validate->fails()){
                return returnMessage('101','非法操作');
            }
            $id = isset($data['id']) ? $data['id'] : ''; //教练id
            $country = isset($data['country']) ? $data['country'] : null; //国籍
            $basic_salary = isset($data['basicSalary']) ? $data['basicSalary'] : ''; //基本工资
            $birthday = isset($data['birthday']) ? $data['birthday'] : null; //生日
            $ot_price = isset($data['otPrice']) ? $data['otPrice'] : ''; //加班单价
            $course_time = isset($data['courseTime']) ? $data['courseTime'] : ''; //额定课时
            $english_name = isset($data['englishName']) ? $data['englishName'] : ''; //英文名称
            $description = isset($data['description']) ? $data['description'] : ''; //教练简介
            $wx_account = isset($data['wxAccount']) ? $data['wxAccount'] : ''; //微信
            $coach = ClubCoach::find($id);

//            DB::transaction(function ());

            if($country || $basic_salary || $birthday || $ot_price || $course_time){
                try{
                    $coach->country = $country;
                    $coach->basic_salary = $basic_salary;
                    $coach->birthday = $birthday;
                    $coach->ot_price = $ot_price;
                    $coach->course_time = $course_time;
                    $coach->save();

                }catch (\Exception $e){
                    returnMessage('400','修改失败');
                }

            }
            if($english_name){
                try{
                    $user = ClubUser::find($coach->user_id);
                    $user->english_name = $english_name;
                    $user->save();
                }catch (\Exception $e){
                    returnMessage('400','修改失败');
                }

            }
            if($description){
                try{
                    $coach->description = $description;
                    $coach->save();
                }catch (\Exception $e){
                    return returnMessage('400','修改失败');
                }

            }
            if($wx_account){
                try{
                    $coach->wx_account = $wx_account;
                    $coach->save();
                }catch (\Exception $e){
                    return returnMessage('400','修改失败');
                }

            }
            return returnMessage('200','修改成功');
    }

    /**
     * 修改奖惩
     * @param Request $request
     * @return array
     */
    public function editCoachPenalty(Request $request){
        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'Numeric|required',
            'year' => 'Numeric|required',
            'month' => 'Numeric|required',
            'remark' => 'String',
            'rewardPenalty' => 'Numeric'
        ]);
        if($validate->fails()){
            $result = [
                'code' => '101',
                'msg'  => '非法操作'
            ];
            return $result;
        }
        $id = isset($data['id']) ? $data['id'] : '';
        $clubId = $data['user']['club_id'];
        $year= isset($data['year']) ? $data['year'] : '';
        $month = isset($data['month']) ? $data['month'] : '';
        $remark = isset($data['remark']) ? $data['remark'] : '';
        $rewardPenalty = isset($data['rewardPenalty']) ? $data['rewardPenalty'] : '';

        try{
            $reward = ClubCoachRewardPenalty::where('coach_id',$id)->where('club_id',$clubId)->where('year',$year)->where('month',$month)->first();
            if(count($reward)>0){

                $reward->remark = $remark;
                $reward->reward_penalty = $rewardPenalty;
                $reward->save();
            }else{
                $reward = new ClubCoachRewardPenalty();
                $reward->coach_id = $id;
                $reward->club_id = $clubId;
                $reward->year = $year;
                $reward->month = $month;
                $reward->remark = $remark;
                $reward->reward_penalty = $rewardPenalty;
                $reward->save();
            }
        }catch (\Exception $e){
            return returnMessage('400','修改失败',$e);
        }

        return returnMessage('200','修改成功');
    }

    /**
     * 上传图片
     * @param Request $request
     * @return array|mixed
     */
    public function imageUpload(Request $request){
        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'Numeric|required',
            'key' => 'String',
        ]);
        if($validate->fails()){
            $result = [
                'code' => '101',
                'msg'  => '非法操作'
            ];
            return $result;
        }
            $id = $data['id'];
            $key = $data['key'];
            if(strstr($key,',')){
                $key = explode(',',$key);
                for ($i = 0; $i<count($key);$i++ ){
                    $clubCacheImage = new ClubCoachImage();
                    $result = uploadKey($id,$clubCacheImage,'coach_id','file_path',$key[$i]);
                }
            }else{
                $clubCacheImage = new ClubCoachImage();
                $result = uploadKey($id,$clubCacheImage,'coach_id','file_path',$key);
            }

            return $result;
    }

    /**
     * 上传视频
     * @param Request $request
     * @return array|mixed
     */
    public function videoUpload(Request $request){
        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'Numeric|required',
            'key' => 'String',
        ]);
        if($validate->fails()){
            $result = [
                'code' => '101',
                'msg'  => '非法操作'
            ];
            return $result;
        }
        $id = $data['id'];
        $key = $data['key'];
        if(strstr($key,',')){
            $key = explode(',',$key);
            for ($i = 0; $i<count($key);$i++ ) {
                $ClubCoachVideo = new ClubCoachVideo();
                $result = uploadKey($id, $ClubCoachVideo, 'coach_id', 'file_path', $key[$i]);
            }
        }else{
            $ClubCoachVideo = new ClubCoachVideo();
            $result = uploadKey($id, $ClubCoachVideo, 'coach_id', 'file_path', $key);
        }
        return $result;
    }


    /**
     * 删除图片
     */
    public function delImage(Request $request){
        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'Numeric|required',
            'imageId' => 'numeric|required'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $id = isset($data['id']) ? $data['id'] : '';
        $imageId = isset($data['imageId']) ? $data['imageId'] : '';
        try{
            ClubCoachImage::where('id',$imageId)->where('coach_id',$id)->delete();
        }catch (\Exception $e){
            return returnMessage('400','删除失败');
        }
        return returnMessage('200','删除成功');
    }

    /**
     * 删除视频
     */
    public function delVideo(Request $request){
        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'Numeric|required',
            'videoId' => 'numeric|required'
        ]);
        if($validate->fails()){
            return returnMessage('101','非法操作');
        }
        $id = isset($data['id']) ? $data['id'] : '';
        $videoId = isset($data['videoId']) ? $data['videoId'] : '';
        try{
            ClubCoachVideo::where('id',$videoId)->where('coach_id',$id)->delete();
        }catch (\Exception $e){
            return returnMessage('400','删除失败');
        }
        return returnMessage('200','删除成功');
    }

    /**
     * 获取教练课程概况
     */
    public function getCoachCourse(Request $request){
        $data = $request->all();
        $validate  = Validator::make($data,[
            'id' => 'numeric|required',
            'startTime' => 'date|nullable',
            'endTime' => 'date|nullable',
            'month' => 'numeric|nullable',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);


        if($validate->fails()){
            $result = [
                'code' => '101',
                'msg'  => '非法操作'
            ];
            return $result;
        }
        $startTime = isset($data['startTime']) ? $data['startTime'] : '';//开始时间
        $endTime = isset($data['endTime']) ? $data['endTime'] : '';//结束时间

        if($startTime > $endTime){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['user']['club_id']) ? $data['user']['club_id'] : '';
        $id = isset($data['id']) ? $data['id']: ''; //教练id
        $month = isset($data['month']) ? $data['month'] : '';//全部/上月/本月
        if($month == 1){
            $month = '%-';
        }elseif($month == 2){
            $month =  date("Y-m", strtotime("-1 months", strtotime(date("Y-m-d",time()))));//上一月
        }elseif($month == 3){
            $month = date("Y-m",time());
        }

        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $offset = ($currentPage-1)*$pagePerNum;
        $result = [];
        $course = ClubCourseCoach::where('club_id',$clubId)->where('coach_id',$id)->where('is_delete',0)
                         ->whereHas('course',function($query) use($month,$startTime,$endTime){
                                $query->when(!empty($month),function ($query) use ($month){
                                    $query->where('day','like',$month.'%');
                                },function ($query) use ($startTime,$endTime){
                                     $query->where('day','>=',$startTime)->where('day','<=',$endTime);
                                });
                         })->with('course')
                            ->get();
        $result['totNum'] = count($course);
        $course = ClubCourseCoach::where('club_id',$clubId)->where('coach_id',$id)->where('is_delete',0)
            ->whereHas('course',function($query) use($month,$startTime,$endTime){
                $query->when(!empty($month),function ($query) use ($month){
                    $query->where('day','like',$month.'%');
                },function ($query) use ($startTime,$endTime){
                    $query->where('day','>=',$startTime)->where('day','<=',$endTime);
                });
            })->with('course')
            ->orderBy('course_id','desc')
            ->offset($offset)
            ->limit($pagePerNum)
            ->get();

       if(count($course) > 0){
           foreach ($course as $val){
               //$result['coachName'] = $val->course_id.'.'.ClubCoach::where('id',$val->coach_id)->value('name');
               $result['coachName'] = ClubCoach::where('id',$val->coach_id)->value('name');
               $result['result'][] = [
                   'courseTime' => Carbon::parse($val->course->day)->format('Y-m-d').' '.$val->course->start_time.'~'.$val->course->end_time,
                   'courseId' => $val->course_id,
                   'className' => ClubClass::where('id',$val->class_id)->value('name'),
                   'venue' => ClubVenue::where('id',$val->course->venue_id)->value('name'),
               ];
           }
       }else{
           $result['coachName'] = ClubCoach::where('id',$data['id'])->value('name');
       }

       return returnMessage('200','请求成功',$result);
    }

    /**
     * 导出课表
     */
    public function export(Request $request){

        $data = $request->all();
        $res = $this->getGeneral($data);

        if (empty(count($res))){
            return returnMessage('404','暂无数据');
        }
        $cellData[] = ['上课时间','课程号','班级','场馆','签到时间'];
        foreach ($res as $val){
            $cellData[] = [
                Carbon::parse($val->course->day)->format('Y-m-d').' '.$val->course->start_time.'~'.$val->course->end_time,
                $val->course_id,
                ClubClass::where('id',$val->class_id)->value('name'),
                ClubVenue::where('id',$val->course->venue_id)->value('name')
            ];
        }

        $date = '课程概况'.date('Y-m-d',time());
        Excel::create($date,function ($excel) use ($cellData){
            $excel->sheet('course',function ($sheet) use ($cellData){
                $sheet->setWidth(array( 'A' => 20,'B' => 20,'C' => 20,'D' => 20,'E'=> 20));
                $sheet->rows($cellData);
                $sheet->cells("A1:E10000",function($cells){

                    $cells->setValignment('center');
                    $cells->setAlignment('center');
                });
            });
        })->export('xls');
        return returnMessage('200','导出成功',[]);
    }

    /**
     * 班级概况封装结果集
     */
    public function getGeneral($data){

        $validate  = Validator::make($data,[
            'id' => 'numeric|required',
            'startTime' => 'nullable|date',
            'endTime' => 'nullable|date',
            'month' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);


        if($validate->fails()){
            $result = [
                'code' => '101',
                'msg'  => '非法操作'
            ];
            return $result;
        }
        $startTime = isset($data['startTime']) ? $data['startTime'] : '';//开始时间
        $endTime = isset($data['endTime']) ? $data['endTime'] : '';//结束时间

        if($startTime > $endTime){
            return returnMessage('101','非法操作');
        }
        $clubId = isset($data['clubId']) ? $data['clubId'] : '';
        $id = isset($data['id']) ? $data['id']: ''; //教练id
        $month = isset($data['month']) ? $data['month'] : '';//全部/上月/本月
        if($month == 1){
            $month = '%-';
        }elseif($month == 2){
            $month =  date("Y-m-d", strtotime("-1 months", strtotime(date("Y-m-d",time()))));//上一月
        }elseif($month == 3){
            $month = date("Y-m-d",time());
        }

        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $offset = ($currentPage-1)*$pagePerNum;

        $res = ClubCourseCoach::where('club_id',$clubId)->where('coach_id',$id)->where('is_delete',0)
            ->whereHas('course',function($query) use($month,$startTime,$endTime){
                $query->when(!empty($month),function ($query) use ($month){
                    $query->where('day','like',$month.'%');
                },function ($query) use ($startTime,$endTime){
                    $query->where('day','<=',$startTime)->where('day','>=',$endTime);
                });
            })->with('course')
            ->get();

        return $res;
    }
}