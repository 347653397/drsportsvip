<?php

namespace App\Services\ClubClass;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ClubClass\ClubClass;
use App\Models\ClubStudent\ClubStudent;
use App\Models\ClubClass\ClubClassStudent;
use App\Models\ClubCourse\ClubCourse;
use App\Models\ClubIncom\ClubIncomSnapshot;
use App\Models\ClubIncom\ClubClassVenueCost;


class ClubClassService implements ITopicService
{
  private $clubClass;
  private $clubStudent;
  private $clubClassStudent;
  private $clubCourse;
  private $clubIncomSnapshot;
  private $clubClassVenueCost;
  /**
   * ClubClassService constructor.
   * @param ClubClass $clubClass
   * @param ClubStudent $clubStudent
   * @param ClubClassStudent $clubClassStudent
   * @param ClubCourse $clubCourse
   * @param ClubIncomSnapshot $clubIncomSnapshot
   */
  public function __construct(ClubClass $clubClass,
  ClubStudent $clubStudent,ClubClassStudent $clubClassStudent,
  ClubCourse $clubCourse,ClubIncomSnapshot $clubIncomSnapshot,
  ClubClassVenueCost $clubClassVenueCost) {
      $this->clubClass = $clubClass;
      $this->clubStudent = $clubStudent;
      $this->clubClassStudent = $clubClassStudent;
      $this->clubCourse = $clubCourse;
      $this->clubIncomSnapshot = $clubIncomSnapshot;
      $this->clubClassVenueCost = $clubClassVenueCost;
  }

  // 班级列表
  public function lists($postData){
    $query = $this->clubClass->query()->with(['venue', 'classTime','clubStudent','Payment']);
    if (isset($postData['keyword'])) {
        $query->where('id', '=', $postData['clueNo']);
        $query->orWhere('name', '=', $postData['clueNo']);
    }
    if(isset($postData['status'])){
        $query->where('status', '=', $postData['status']);
    }
    if(isset($postData['venue_id'])){
        $query->where('venue_id', '=', $postData['venueId']);
    }
    if(isset($postData['calss_id'])){
        $query->where('id', '=', $postData['calss_id']);
    }
    $list = $query->paginate($postData['pageSize']);
    $arr = [];
    collect($list->items())->each(function ($item) use (&$arr) {
        // 活动和不活跃人数
        $clubClassStudent = $this->clubClassStudent->selectRaw('is_freeze,count(id) as count')->where([
          ['is_freeze','=',$is_freeze]
        ])->groupBy('status')->get();
        // 活动人数
        $actvieyNumber = $clubClassStudent[0]['count'];
        // 不活动人数
        $inActvieyNumber = $clubClassStudent[1]['count'];
        // 最小和最大年龄
        $clubStudentAge = $this->clubStudent->where([
          ['class_id','=',$class_id],
          ['status','=',1]
        ])->orderBy('age');
        // 最小年龄
        $minAge = $clubStudentAge->first();
        // 最大年龄
        $maxAge = $clubStudentAge->last();

        array_push($arr, [
            'id' => $item->id,
            'className' => $item->name,
            'venueName' => $item->venue->name,
            'classTime' => $item->classTime,
            'actvieyNumber' => $actvieyNumber,
            'inActvieyNumber' => $inActvieyNumber,
            'minAge' => $minAge,
            'maxAge' => $maxAge,
            'studentLimit' => $item->student_limit,
            'showInApp' => $item->show_in_app,
            'paymentTag'  => $item->payment->payment_tag,
        ]);
    });
    return [
        'totalElements' => $list->total(),
        'totalPage' => ceil($list->total() / $postData['pageSize']),
        'content' => $arr
    ];
  }

  // 添加班级
  public function create($postData){
    //$this->clubClass->id = $postData['classId'];
    $this->clubClass->name = $postData['className'];
    $this->clubClass->type = $postData['classType'];
    $this->clubClass->pay_plan_type_id = $postData['payPlanTypeId'];
    $this->clubClass->venue_id = $postData['venueId'];
    $this->clubClass->teacher_id = $postData['teacherId'];
    $this->clubClass->teacher_name = $postData['teacherName'];
    $this->clubClass->student_limit = $postData['studentLimit'];
    $this->clubClass->show_in_app = $postData['showInApp'];
    $this->clubClass->remark = $postData['remark'];
    try {
        $this->clubClass->save();
        $insertedId = $user->id;
        // 批量写入班级时间
        $classTime = json($postData['classTime'],true);
        foreach ($classTime as $key => $value) {
          $classTime[$key]['class_id'] = $insertedId;
        }
        DB::table('club_class_time')->insert([
          $classTime
        ]);
    } catch (Exception $exception) {
        throw new Exception(config('error.clubClass.1801'), 1801);
    }
  }

  // 修改班级
  public function update($postData){
    $type = $postData['classId'];
    // 修改班级
    if($type == 0){
      $this->clubClass->id = $postData['classId'];
      $this->clubClass->name = $postData['className'];
      $this->clubClass->type = $postData['classType'];
      $this->clubClass->pay_plan_type_id = $postData['payPlanTypeId'];
      $this->clubClass->venue_id = $postData['venueId'];
      $this->clubClass->teacher_id = $postData['teacherId'];
      $this->clubClass->teacher_name = $postData['teacherName'];
      $this->clubClass->student_limit = $postData['studentLimit'];
      $this->clubClass->remark = $postData['remark'];
    }
    // 设为失效/生效
    else if($type == 1){
      $this->clubClass->status = $postData['status'];
    }
    // 是否APP端显示
    else if($type == 2){
      $this->clubClass->show_in_app = $postData['showInApp'];
    }
    try {
        $this->clubClass->save();
        if($type == 0){
          // 批量清空班级时间
          $this->$classTime->query()->where('class_id',$postData['classId'])->delete();
          // 批量写入班级时间
          $classTime = json($postData['classTime'],true);
          foreach ($classTime as $key => $value) {
            $classTime[$key]['class_id'] = $postData['classId'];
          }
          DB::table('club_class_time')->insert([
            $classTime
          ]);
        }
    } catch (Exception $exception) {
        throw new Exception(config('error.clubClass.1801'), 1801);
    }
  }

  // 删除班级
  public function delete($postData){
    $clubClass = $this->clubClass->query()->find($postData['classId']);
    if ($clubClass == null) {
        throw new Exception(config('error.clubClass.1502'), 1502);
    }
    try {
        $clubClass->delete();
    } catch (Exception $exception) {
        throw new Exception(config('error.clubClass.1504'), 1504);
    }
  }

  // 班级详情
  public function detail($postData){
    $clubClass = $this->clubClass->query()->with(['classTime'=> function ($query) {
      $query->get(['class_id', 'day', 'start_time', 'end_time']);
    }])->find($postData['classId']);
    if ($clubClass == null) {
        throw new Exception(config('error.clubClass.1502'), 1502);
    }
    return [
        'id' => $clubClass->id,
        'className' => $clubClass->name,
        'classType' => $clubClass->type,
        'payPlanTypeId' => $clubClass->pay_plan_type_id,
        'venueId' => $clubClass->venue_id,
        'teacherId' => $clubClass->teacherId,
        'studentLimit' => $clubClass->studentLimit,
        'classTime' => $clubClass->classTime,
        'remark' => $clubClass->remark,
        'showInapp' => $clubClass->showInApp,
    ];
  }

  // 班级概况汇总
  public function classCeneralSituationAll($postData){
    $clubClass = $this->clubClass->query()->find($postData['classId']);
    if ($clubClass == null) {
        throw new Exception(config('error.clubClass.1502'), 1502);
    }
    // 获取课程和场管信息
    $clubCourse = $this->clubCourse->where([
      ['class_id','=',$classId],
      ['day','<',date("Y-m-d")],
    ])->with(['venueCostSnapshot','coachCostSnapshot','coachCostByCourse'])->get();

    //决开课次数
    $courseCount = $clubCourse->count();
    // 场地费用
    $venueCost = $clubCourse->venueCostSnapshot->sum('venue_cost');
    // 场地分成
    $venueRatingCost = $clubCourse->venueCostSnapshot->sum('venue_rating_cost');
    // 签到总收入
    $incomSnapshot = $this->clubIncomSnapshot->where('class_id',$classId)->sum('money');
    // 结款次数
    $venueCostCount = $this->clubClassVenueCost->where('class_id',$classId)->count();
    // 教练每节课汇总费用
    $coachCostByCourse = $clubCourse->coachCostByCourse->sum('coach_manage_cost');
    // 教练管理费用
    $coachManageCost = $clubCourse->coachCostSnapshot->sum('manage_cost');

    // 盈利
    $profit = $IncomSnapshot - $venueCost - $venueRatingCost - $coachCostSnapshot - $coachCostSnapshot;

    return [
      'courseCount' => $courseCount,
      'profit' => $profit,
      'venueCostCount' => $venueCostCount,
      'incomSnapshot' => $incomSnapshot,
      'venueCost' => $venueCost + $venueRatingCost,
      'coachCostByCourse' => $coachCostByCourse,
      'coachManageCost' => $coachManageCost,
    ];
  }

  // 班级概况
  public function classCeneralSituation($postData){
    $clubClass = $this->clubClass->query()->find($postData['classId']);
    if ($clubClass == null) {
        throw new Exception(config('error.clubClass.1502'), 1502);
    }
    $startTime = $postData['startTime'];
    $endTime = $postData['endTime'];
    // 获取课程和场管信息
    $clubCourse = $this->clubCourse->where('class_id',$classId)->whereBetween([
        'day'=>[date("Y-m-d",$startTime),date("Y-m-d",$endTime)]
      ])
    ->with(['venueCostSnapshot','coachCostSnapshot','coachCostByCourse','clubIncomSnapshot'])->get();
    // 场地费用
    $venueCost = $clubCourse->venueCostSnapshot->sum('venue_cost');
    // 场地分成
    $venueRatingCost = $clubCourse->venueCostSnapshot->sum('venue_rating_cost');
    // 教练每节课汇总费用
    $coachCostByCourse = $clubCourse->coachCostByCourse->sum('coach_manage_cost');
    // 教练管理费用
    $coachManageCost = $clubCourse->coachCostSnapshot->sum('manage_cost');
    // 盈利
    $profit = $IncomSnapshot - $venueCost - $venueRatingCost - $coachCostSnapshot - $coachCostSnapshot;

    $arr = [];
    $clubCourse->each(function ($item, $key) {
      $subCoachCost = $item->coachCostByCourse->coach_manage_cost + $item->coachCostSnapshot->manage_cost;
      $subVenueCost = $item->venueCostSnapshot->venue_cost;
      $subIncomSnapshot = $item->clubIncomSnapshot->sum('money');
      array_push($arr,[
        'startTime' => $item->start_time,
        'coachCost' => $subCoachCost,
        'venueCost' => $subVenueCost ,
        'incomSnapshot' => $subIncomSnapshot,
        'expenditure' =>$subCoachCost + $subVenueCost,
        'income' => $subIncomSnapshot,
        'profit' => $subIncomSnapshot - $subCoachCost - $subVenueCost,
      ]);
    });

    return [
      'profit' => $profit,
      'incomSnapshot' => $clubCourse->clubIncomSnapshot->sum('money'),
      'venueCost' => $venueCost + $venueRatingCost,
      'coachCostByCourse' => $coachCostByCourse,
      'coachManageCost' => $coachManageCost,
      'clubCourse' => $arr,
    ];
  }

  // 学员列表
  public function clueStudentLists($postData){
    $query = $this->clubClassStudent->query()->where('class_id',$postData['classId'])->with(['clubStudent']);

    if(isset($postData['noCourse'])){
        $query->where('left_course_count', '>', 0);
    }
    $list = $query->paginate($postData['pageSize']);

    $clubClassStudent = [];
    $list->each(function ($item, $key) {
      array_push($clubClassStudent,[
        'id' => $item->clubStudent->id,
        'name' => $item->clubStudent->name,
        'age' => $item->clubStudent->age,
        'exStatus' => $item->clubStudent->ex_status,
        'leftCourseCount' => $item->clubStudent->left_course_count,
        'guarder_mobile'  => $item->clubStudent->guarder_mobile,
      ]);
    });
    return [
        'totalElements' => $list->total(),
        'totalPage' => ceil($list->total() / $postData['pageSize']),
        'content' => $clubClassStudent
    ];
  }

  // 课程列表
  public function courseLists($postData){
    
  }
}
