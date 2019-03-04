<?php

namespace App\Services\ClubClass;

use Exception;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use App\Models\ClubClass\ClubClassTeacher;
use App\Models\ClubVenue\ClubVenue;
use App\Models\ClubClass\ClubClassStudent;
use App\Models\ClubClass\ClubClass;
use App\Models\ClubCourse\ClubCourseSign;
use App\Models\ClubStudent\ClubStudent;

class ClubClassTeacherService implements ITopicService
{
  private $clubVenue;
  private $clubClass;
  private $clubClassTeacher;
  private $clubClassStudent;
  private $clubCourseSign;
  private $clubStudent;
  /**
   * ClubClassTeacherService constructor.
   * @param ClubVenue $clubVenue
   * @param ClubClass $clubClass
   * @param ClubClassTeacher $clubClassTeacher
   * @param ClubClassStudent $clubClassStudent
   * @param ClubCourseSign $clubCourseSign
   * @param ClubStudent $clubStudent
   */
  public function __construct(ClubClass $clubClass,
  ClubVenue $clubVenue,ClubClassTeacher $clubClassTeacher,
  ClubClassStudent $clubClassStudent,ClubCourseSign $clubCourseSign,
  ClubStudent $clubStudent) {
      $this->clubVenue = $clubVenue;
      $this->clubClass = $clubClass;
      $this->clubClassTeacher = $clubClassTeacher;
      $this->clubClassStudent = $clubClassStudent;
      $this->clubCourseSign = $clubCourseSign;
      $this->clubStudent = $clubStudent;
  }

  // 班主任报告汇总
  public function all(){
    // 场馆数量
    $venueCount = $this->clubVenue->count();
    // 班主任数量
    $teacherCount = $this->clubClassTeacher->count();
    // 活跃学员
    $classStudentCount = $this->clubClassStudent->where('is_freeze',0)->count();
    // 上次出勤
    $courseSignCount = $this->clubCourseSign->where('sign_status',1)->count();
    // 学员上限
    $studentLimit = $this->clubClass->sum('student_limit');
    // 可招人数
    $canStudentCount = $studentLimit - $teacherCount;
    // 冻结人数
    $freezeCount = $this->clubClassStudent->where('is_freeze',1)->count();

    $clubStudentAge = $this->clubStudent->where([
      ['class_id','=',$class_id],
      ['status','=',1]
    ])->orderBy('age');

    // 最小年龄
    $minAge = $clubStudentAge->first();
    // 最大年龄
    $maxAge = $clubStudentAge->last();

    return [
        'venueCount' => $venueCount,
        'teacherCount' => $teacherCount,
        'classStudentCount' => $classStudentCount,
        'courseSignCount' => $courseSignCount,
        'studentLimit' => $studentLimit,
        'canStudentCount' => $canStudentCount,
        'freezeCount' => $freezeCount,
        'minAge'  => $minAge,
        'maxAge'  => $maxAge
    ];
  }
  // 班主任报告列表
  public function lists($postData){
    $query = $this->clubClassTeacher->with(['classTime','venue',
    'clubClassStudent'=>function($query){
      $query->groupBy('is_freeze')->count();
    },
    'courseSign'=>function($query){
      $query->clubCourseSign->groupBy('sign_status')->count();
    },
    'clubClass'=>function($query){
      $query->clubClass->find('student_limit');
    },
    'courseSign'=>function($query) use ($postData){
      $query->clubStudent->where([
        ['class_id','=',$postData['classId']],
        ['status','=',1]
      ])->orderBy('age');
    },
    ]);
    $list = $query->paginate($postData['pageSize']);
    $arr = [];
    collect($list->items())->each(function ($item) use (&$arr) {
        array_push($arr, [
            'id' => $item->id,
            'classTime' => $item->classTime,
            'venueName' => $item->venue->name,
            'teacherName' => $item->teacher_name,
            'actvieyNumber' => $item->$clubClassStudent[0],
            'courseSign' => $item->$courseSign,
            'studentLimit' => $item->clubClass->student_limit,
            'canStudentCount' => $item->clubClass->student_limit - $item->$clubClassStudent[0],
            'fullClassRate' => ($item->$clubClassStudent[0]/$item->clubClass->student_limit)*100,
            'freezeCount' => $item->$clubClassStudent[1],
            'minAge'  => $item->courseSign->courseSign[0],
            'maxAge'  => $item->courseSign->courseSign[1],
            'maxAge'  => $item->clubClass->cremark
        ]);
    });
    return [
        'totalElements' => $list->total(),
        'totalPage' => ceil($list->total() / $postData['pageSize']),
        'content' => $arr
    ];
  }
}
