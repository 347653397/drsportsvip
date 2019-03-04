<?php
namespace App\Api\Controllers\System;

use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubSystem\ClubExams;
use App\Model\ClubSystem\ClubExamsItems;
use App\Model\ClubSystem\ClubExamsItemsLevel;
use App\Model\ClubSystem\ClubExamsGeneralLevel;
use App\Model\ClubSystem\ClubExamsItemsStudent;
use App\Model\ClubSystem\ClubExamsStudent;

use App\Model\ClubSystem\ClubMessage;
use App\Model\ClubSystem\ClubMessageApp;
use App\Model\ClubSystem\ClubMessageTemplate;
use App\Model\ClubStudent\ClubChannel;
use App\Model\ClubSystem\ClubOperationLog;
use App\Model\ClubCoachManageCost\ClubCoachManageCost;


use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use Tymon\JWTAuth\Facades\JWTAuth;
use Exception;
use App\Facades\Util\Common;
use App\Facades\Util\Log;


class SystemController extends Controller
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

//    1.测验列表搜索
    public function examsList(Request $request)
    {
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
        $status = isset($data['status']) ? $data['status'] : '';  //1=有效;0=失效
        $startTime = isset($data['startTime']) ? $data['startTime'] : '';
        $endTime = isset($data['endTime']) ? $data['endTime'] : '';

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $exams = DB::table('club_exams')->where('is_delete', 0);

        if (strlen($search) > 0) {
            $exams->where(function ($query) use ($search) {
                $query->where('exam_name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });
        }

        if (strlen($status) > 0) {
            if ($status != 'false') {
                $exams->where('status', '1');
            }
        }
        if (!empty($startTime)) {
            $exams->where('exams_date', '>=', $startTime);
        }
        if (!empty($endTime)) {
            $exams->where('exams_date', '<=', $endTime);
        }
        $exams->where('club_id', $this->user->club_id);

        $exams = $exams->orderBy('id', 'desc')->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $exams);
    }


    // 2.测验添加
    public function examsadd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examName' => 'required|string',
            'status' => 'required|numeric',
            'examsDate' => 'required|date',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = new ClubExams();
        $exams->exam_name = $input['examName'];
        $exams->club_id = $this->user->club_id;
        if ($input['status'] == 1) {
            // 生效
            $exams->effective = $input['status'];
            $exams->status = 2;
        } else {
            $exams->effective = $input['status'];
            $exams->status = 0;
        }

        $exams->exams_date = $input['examsDate'];
        $exams->save();
        return returnMessage('200', '');
    }

    // 3.修改信息显示
    public function examseditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examId' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubExams::find($input['examId']);
        return returnMessage('200', '', $exams);
    }

    // 4.修改修改
    public function examsedit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examId' => 'required|numeric',
            'examName' => 'required|string',
            'status' => 'required|numeric',
            'examsDate' => 'required|date',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubExams::find($input['examId']);

        if (!empty($exams)) {
            $exams->exam_name = $input['examName'];

            if ($input['status'] == 1) {
                // 生效
                $exams->effective = $input['status'];
                $exams->status = 2;
            } else {
                $exams->effective = $input['status'];
                $exams->status = 0;
            }
            $exams->exams_date = $input['examsDate'];
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

    //5.测验删除
    public function examsdel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examsId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $exams = ClubExams::find($input['examsId']);
        if (!empty($exams)) {
            // 判断是否有关联的学员，如果有，则不允许删除
            $count = DB::table('club_exams_student AS Student')
                ->join('club_student AS cstudent','cstudent.id','=','Student.student_id')
                ->select(
                    'cstudent.id'
                )
                ->where('Student.exam_id',$input['examsId'])->where('Student.is_delete',0)->count();
            if ($count > 0){
                return returnMessage('3006', config('error.sys.3006'));
            }else{

                $exams->is_delete = 1 ;
                $exams->update();
                return returnMessage('200', '');

            }
        } else {
            return returnMessage('200', '修改失败');
        }
    }

// 6.测验操作提交
    public function examscheck(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examsId' => 'required|numeric',
            'status' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }


        $exams = ClubExams::find($input['examsId']);
        if (!empty($exams)) {
            $exams->status = $input['status'];
            $exams->effective = 1;
            $exams->status = 2;
            $exams->update();
            $club = Club::find($input['user']['club_id']);
            $client = new Client();
            //$url = 'http://admin.drsportsvip.com/exam/acceptClubExam';
            $url = config('curl.dr_exam');

            $formData = [
                'json' => [
                    'clubId' => $input['user']['club_id'],
                    'clubName' => $club->name,
                    'clubExamId' => $input['examsId'],
                    'examName' => $exams->exam_name,
                ]
            ];
            try {
                $info = $client->post($url, $formData);
                $info = json_decode($info->getBody()->getContents(), true);
            } catch (\Exception $e) {
                return returnMessage('400', '提交失败');;
            }
            return $info;
        } else {
            return returnMessage('400', '提交失败');
        }

    }


//6.1测验详情
    public function examsitemslist(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examsId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $res = array();
        $res['data'] = ClubExams::where('id', $input['examsId'])->first();
        $res['items'] = ClubExamsItems::where('exam_id', $input['examsId'])->get()->toArray();
        $res['level'] = ClubExamsItemsLevel::where('exam_id', $input['examsId'])->where('is_delete', 0)->get();
        $res['general'] = ClubExamsGeneralLevel::where('exam_id', $input['examsId'])->where('is_delete', 0)->get();

        return returnMessage('200', '', $res);
    }

//    7.测验管理添加项目
    public function examsitemsadd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'itemName' => 'required|string',
            'examId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsitem = new ClubExamsItems();
        $examsitem->item_name = $input['itemName'];
        $examsitem->exam_id = $input['examId'];
        $examsitem->save();
        return returnMessage('200', '');
    }

//    8.测验管理修改项目
    public function examsitemseditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'itemsId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsitem = ClubExamsItems::where("id", $input['itemsId'])->get();
        return returnMessage('200', '', $examsitem);
    }

//    9.测验管理修改项目
    public function examsitemsedit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'itemsId' => 'required|numeric',
            'itemName' => 'required|string',

        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsitem = ClubExamsItems::find($input['itemsId']);
        if (!empty($examsitem)) {
            $examsitem->item_name = $input['itemName'];
            $examsitem->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//    10.测验管理删除项目
    public function examsitemsdel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'itemsId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubExamsItems::find($input['itemsId']);
        if (!empty($exams)) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }


//    ------------------测验管理等级
//    11.测验等级管理添加项目
    public function examsleveladd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'levelName' => 'required|string',
            'examId' => 'required|numeric',
            'levelScore' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsitem = new ClubExamsItemsLevel();
        $examsitem->level_name = $input['levelName'];
        $examsitem->exam_id = $input['examId'];
        $examsitem->level_score = $input['levelScore'];
        $examsitem->save();
        return returnMessage('200', '');
    }

//    12.测验管理修改项目
    public function examsleveleditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'levelId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examslevel = ClubExamsItemsLevel::where("id", $input['levelId'])->get();
        return returnMessage('200', '', $examslevel);
    }

//    13.测验管理修改项目
    public function examsleveledit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'levelId' => 'required|numeric',
            'levelName' => 'required|string',
            'levelScore' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examslevel = ClubExamsItemsLevel::find($input['levelId']);
        if (!empty($examslevel)) {
            $examslevel->level_score = $input['levelScore'];
            $examslevel->level_name = $input['levelName'];
            $examslevel->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//    14.测验管理删除项目
    public function examsleveldel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'levelId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $exams = ClubExamsItemsLevel::find($input['levelId']);
        if (!empty($exams)) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }


//    ---------测验管理添加综合
//    15.测验等级管理添加项目
    public function examsgeneraladd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'levelName' => 'required|string',
            'examId' => 'required|numeric',
            'levellowScore' => 'required|numeric',
            'levelhighScore' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsgeneral = new ClubExamsGeneralLevel();
        $examsgeneral->level_name = $input['levelName'];
        $examsgeneral->exam_id = $input['examId'];
        $examsgeneral->level_low_score = $input['levellowScore'];
        $examsgeneral->level_high_score = $input['levelhighScore'];
        $examsgeneral->save();
        return returnMessage('200', '');
    }

//    16.测验管理修改项目
    public function examsgeneraleditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'generalId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examslevel = ClubExamsGeneralLevel::where("id", $input['generalId'])->get();
        return returnMessage('200', '', $examslevel);
    }

//    17.测验管理修改项目
    public function examsgeneraledit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'generalId' => 'required|numeric',
            'levelName' => 'required|string',
            'levellowScore' => 'required|numeric',
            'levelhighScore' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsgeneral = ClubExamsGeneralLevel::find($input['generalId']);
        if (!empty($examsgeneral)) {
            $examsgeneral->level_name = $input['levelName'];
            $examsgeneral->level_low_score = $input['levellowScore'];
            $examsgeneral->level_high_score = $input['levelhighScore'];
            $examsgeneral->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//    18.测验管理删除项目
    public function examsgeneraldel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'generalId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubExamsGeneralLevel::find($input['generalId']);
        if (!empty($exams)) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//19.添加学员-学员搜索
    public function addstudentsearch(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $search = isset($data['search']) ? $data['search'] : '';
        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $exams = DB::table('club_student')->where('is_delete', 0);
        $exams2 = DB::table('club_student')->where('is_delete', 0);

        if (strlen($search) > 0) {
            $exams->where(function ($query) use ($search) {
                $query->where('name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });

            $exams2->where(function ($query) use ($search) {
                $query->where('name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });
        }
        $res = $exams->select('id', 'name', 'main_class_name AS className')->where('status', 1)->where("club_id", $this->user->club_id)->paginate($pagePerNum);
        $mycount = $exams2->select('id', 'name', 'main_class_name AS className')->where('status', 1)->where("club_id", $this->user->club_id)->count();

        $sale = DB::table('club_exams_student AS Student')
            ->join('club_student AS cstudent', 'cstudent.id', '=', 'Student.student_id')
            ->select(
                'Student.id AS exsid', 'cstudent.name'
            )
            ->where('cstudent.status', 1)->where('Student.is_delete', 0);

        if (strlen($search) > 0) {
            $sale->where(function ($query) use ($search) {
                $query->where('cstudent.name'  , 'like','%'.$search.'%')
                    ->orwhere('cstudent.id', $search);
            });
        }
        $student = $sale->where('cstudent.club_id', $this->user->club_id)->get()->toArray();

        $result = array();
        $result['total'] = $mycount;
        $result['data'] = $res->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'name' => $item->name,
                'className' => $item->className
            ];

            return $result;
        });

        $result["exams"] = $student;
        return returnMessage('200', '请求成功', $result);
    }

//20.添加学员--测验
    public function addstudentexams(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examId' => 'required|numeric',
            'studentIds' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $examsgeneral = new ClubExamsStudent();
        foreach ($input['studentIds'] as $studentId) {
            $res = $examsgeneral->where('student_id', $studentId)->get();
            if (!$res) {
                $examsgeneral->student_id = $studentId;
                $examsgeneral->exam_id = $input['examId'];
                $examsgeneral->save();
            }
        }
        return returnMessage('200', '');
    }

//23指定测验学员列表--测验
    public function studentexamslist(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'examId' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $pagePerNum = isset($data['pagePerNum']) ? $data['pagePerNum'] : 10; //一页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页数
        $examId = isset($data['examId']) ? $data['examId'] : '';

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $sale = DB::table('club_exams_student AS Student')
            ->join('club_student AS cstudent', 'cstudent.id', '=', 'Student.student_id')
            ->select(
                'cstudent.*', 'Student.id as sid'
            )
            ->where('Student.exam_id', $examId)->where('Student.is_delete', 0);
        $sale = $sale->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $sale);
    }

//21.删除学员--测验
    public function delstudentexams(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examId' => 'required|numeric',
            'studentId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubExamsStudent::where('exam_id', $input['examId'])->where('student_id', $input['studentId'])->get();
        if (count($exams) > 0) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

    //21.1获取当前测验不生效学员
    public function getnoeffectexams(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'examId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $examId = isset($data['examId']) ? $data['examId'] : '';
        $sale = DB::table('club_exams_student AS Student')
            ->join('club_student AS cstudent', 'cstudent.id', '=', 'Student.student_id')
            ->select(
                'Student.id AS exsid', 'cstudent.name'
            )
            ->where('Student.status', 0)
            ->where('Student.exam_id', $examId)->where('Student.is_delete', 0);

        $student = $sale->get();
        return returnMessage('200', '请求成功', $student);
    }

//22.添加成绩
    public function addstudentcore(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'datas' => 'nullable|array',
            'remark' => 'nullable|string',
            'examStudentId' => 'required|numeric',
            'examId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $datas = $input['datas'];
        $remark = $input['remark'] ? $input['remark'] : null;
        $resu = 1;
        $total = 0;
        $leveltotal = 0;
        foreach ($datas as $val) {
            $total += ClubExamsItemsLevel::where('id', $val)->value('level_score');
        }
        $general = ClubExamsGeneralLevel::where('exam_id', $input['examId'])->where('level_low_score', '<=', $total)->where('level_high_score', '>=', $total)->first();
        if (count($general) < 1) {
            return returnMessage('404', '实际打分总和与综合等级分数区间不符');
        }
        $examStudent = new ClubExamsStudent();
        $examStudent->exam_general_id = $general->id;
        $examStudent->exam_general_level = $general->level_name;
        $examStudent->exam_general_score = $total;
        $examStudent->student_id = $input['examStudentId'];
        $examStudent->exam_id = $input['examId'];
        $examStudent->remark = $remark;
        $examStudent->save();
        $examStudentId = $examStudent->id;
        //key 为测验项目  val 为测验等级
        foreach ($datas as $key => $val) {
//            $itemsStudent = ClubExamsItemsStudent::where('exam_student_id',$input['examStudentId'])->where('exam_items_id',$key)->first();
//            if(!$itemsStudent){
            $newItemStudent = new ClubExamsItemsStudent();
            $newItemStudent->exam_student_id = $examStudentId;
            $newItemStudent->exam_items_id = $key;
            $newItemStudent->exam_items_level_id = $val;
            $newItemStudent->exam_items_level_score = $score = ClubExamsItemsLevel::where('id', $val)->value('level_score');
            $newItemStudent->save();
//            }else{
//                $itemsStudent->exam_student_id = $input['examStudentId'];
//                $itemsStudent->exam_items_id = $key;
//                $itemsStudent->exam_items_level_id = $val;
//                $itemsStudent->exam_items_level_score = $score = ClubExamsItemsLevel::where('id',$val)->value('level_score');
//                $itemsStudent->save();
//            }

        }


        return returnMessage('200', '添加成功');

    }

//22.1添加成绩-确认添加
    public function updatestudentcore(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examIds' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $examIds = $input['examIds'];
        $examarr = explode(',', $examIds);
        foreach ($examarr as $id) {
            $examsgeneral = ClubExamsStudent::find($id);
            if (!empty($examsgeneral)) {
                $examsgeneral->status = 1;
                $examsgeneral->update();
            }
        }
    }

//   24. 查看学员详情---查看成绩
    public function showstudentitems(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'studentId' => 'required|numeric',
            'examId' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $student_id = isset($data['studentId']) ? $data['studentId'] : '';

        $sale = DB::table('club_exam_items_student AS Student')
            ->join('club_exams_items AS items', 'items.id', '=', 'Student.exam_items_id')
            ->select(
                'Student.*', 'items.item_name'
            )
            ->where('Student.exam_student_id', $student_id);
        $sale = $sale->get();
        $total = ClubExamsStudent::where('student_id', $student_id)->get();
        $res = array();
        $res["items"] = $sale;
        $res["total"] = $total;

        return returnMessage('200', '请求成功', $res);

    }

//测验详情
    public function showstudentexams(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'studentId' => 'required|numeric',
            'examId' => 'required|numeric',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $student_id = isset($data['studentId']) ? $data['studentId'] : '';
        $eaxm_studentid = ClubExamsStudent::where('student_id', $student_id)->where('exam_id', $data['examId'])->value('id');


        $sale = DB::table('club_exam_items_student AS Student')
            ->join('club_exams_items AS items', 'items.id', '=', 'Student.exam_items_id')
            ->join('club_exams AS exams', 'exams.id', '=', 'items.exam_id')
            ->select(
                'Student.*', 'items.item_name', 'exams.exam_name'
            )
            ->where('Student.exam_student_id', $eaxm_studentid)
            ->where('items.exam_id', $data['examId'])->where('Student.is_delete', 0);
        $sale = $sale->get();

        $total = ClubExamsStudent::where('student_id', $student_id)->where('exam_id', $data['examId'])->where('is_delete', 0)->first();
        $level = ClubExamsItemsLevel::where('exam_id', $data['examId'])->where('is_delete', 0)->get();
        $res = array();
        $res["items"] = $sale;
        $res["total"] = $total;
        $res["level"] = $level;


        return returnMessage('200', '请求成功', $res);

    }


//    25.查看学员详情删除
    public function showstudentdel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'examStudentId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubExamsStudent::find($input['examStudentId']);
        if (count($exams) > 0) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }

    }


//######################学员来源#####################

    /**
     * 来源列表
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function channellist()
    {
        $result = ClubChannel::where('is_delete', 0)
            ->whereIn('club_id', [0, $this->user->club_id])->get()->toArray();
        $tree = array();
        foreach ($result as $item) {
            $tree[$item['id']] = $item;
        }
        $res = $this->generateTree($tree);
        return returnMessage('200', '', $res);
    }

    function generateTree($items)
    {
        $tree = array();
        foreach ($items as $item) {
            if (isset($items[$item['parent_id']])) {
                $items[$item['parent_id']]['son'][] = &$items[$item['id']];
            } else {
                $tree[] = &$items[$item['id']];
            }
        }
        return $tree;
    }

//27.获取子来源
    public function getsonchannel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'channelId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $res = ClubChannel::where("parent_id", $input["channelId"])->where("is_delete", 0)->get();
        return returnMessage('200', '', $res);
    }

    /**
     * 添加来源
     * @param Request $request
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function channeladd(Request $request)
    {
        if (empty($this->user->club_id) || $this->user->club_id < 0) {
            return returnMessage('1204', config('error.common.1001'));
        }
        $input = $request->all();
        $validate = Validator::make($input, [
            'channelName' => 'required|string',
            'parentId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        if (ClubChannel::where('channel_name', $input['channelName'])->exists()) {
            return returnMessage('3005', config('error.sys.3005'));
        }
        $channel = new ClubChannel();
        $channel->channel_name = $input['channelName'];
        $channel->parent_id = $input['parentId'];
        $channel->remark = $input['remark'];
        $channel->club_id = $this->user->club_id;
        $channel->save();
        return returnMessage('200', '');
    }

    /**
     * 来源修改显示
     * @param Request $request
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function channeleditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'channelId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $examslevel = ClubChannel::where("id", $input['channelId'])
            ->whereIn('club_id', [0, $this->user->club_id])
            ->first();
        return returnMessage('200', '修改数据', $examslevel);
    }

    /**
     * 修改来源
     * @param Request $request
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function channeledit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'channelId' => 'required|numeric',
            'channelName' => 'required|string',
            'parentId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        if (ClubChannel::where('channel_name', $input['channelName'])->exists()) {
            return returnMessage('3005', config('error.sys.3005'));
        }
        $examslevel = ClubChannel::where("id", $input['channelId'])
            ->whereIn('club_id', [0, $this->user->club_id])->first();
        if (!empty($examslevel)) {
            $examslevel->channel_name = $input['channelName'];
            $examslevel->remark = $input['remark'];
            if (!in_array($input['channelId'], [1, 2, 3]) && empty($examslevel->club_id)) {
                $examslevel->club_id = $this->user->club_id;
            }
            $examslevel->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

    /**
     * 删除来源
     * @param Request $request
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function channeldel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'channelId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $exams = ClubChannel::where("id", $input['channelId'])
            ->whereIn('club_id', [0, $this->user->club_id])->first();
        if (count($exams) > 0) {
            $exams->is_delete = 1;
            $exams->updated_at = date('Y-m-d H:i:s');
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }



#########################日志管理###########################
//32.日志搜索6.日志管理   club_operation_log
    public function logsearch(Request $request)
    {
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
        $opUser = isset($data['opUser']) ? $data['opUser'] : '';
        $opObject = isset($data['opObject']) ? $data['opObject'] : '';
        $startTime = isset($data['startTime']) ? $data['startTime'] : '';
        $endTime = isset($data['endTime']) ? $data['endTime'] : '';

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $exams = DB::table('club_operation_log')->where("is_delete", 0);

        if (strlen($opUser) > 0) {
            $exams->where('operation_user_name', 'like', '%' . $opUser . '%');
        }

        if (strlen($opObject) > 0) {
            $exams->where('operation_object', $opObject);
        }

        if (!empty($startTime)) {
            $exams->where('created_at', '>=', $startTime);
        }
        if (!empty($endTime)) {
            $exams->where('created_at', '<=', $endTime);
        }

        $exams = $exams->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $exams);
    }


//#########################教练管理费####################################
//33.教练费用搜索显示  club_coach_manage_cost表---    待确定是否需要coach_id
    public function coachlist(Request $request)
    {
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
        $year = isset($data['year']) ? $data['year'] : '';

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $exams = DB::table('club_coach_manage_cost')->where("club_id", $this->user->club_id)->where('is_delete', 0);
        $exams2 = DB::table('club_coach_manage_cost')->where("club_id", $this->user->club_id)->where('is_delete', 0);

        if (strlen($year) > 0) {
            $exams->where('year', $year);
            $exams2->where('year', $year);
        }

        $exams = $exams->paginate($pagePerNum);
        $mycount = $exams->count();

        $result = array();
        $result['total'] = $mycount;
        $data = $exams->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'club_id' => $item->club_id,
                'year' => $item->year,
                'month' => $item->month,
                'cost' => $item->cost,
                'operation_user_id' => $item->operation_user_id,
                'operation_user_name' => $item->operation_user_name,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
                'deleted_at' => $item->deleted_at,
            ];
            $topmonty = [];
            $secmonty = [];
            $tempArr = ['cost' => $item->cost, 'operation_user_name' => $item->operation_user_name,
                'updated_at' => $item->updated_at];
            $month = strlen($item->month) === 1 ? '0'.$item->month : $item->month;
            if (strcasecmp($item->year.'-'.$month, date('Y-m')) === 0
                && !empty($item->cost)) {
                $topmonty = $tempArr;
            }
            if (strcasecmp($item->year.'-'.$month, date('Y-m', strtotime('-1 month'))) === 0) {
                $secmonty = $tempArr;
            }
            return [$result, $topmonty, $secmonty];
        });

        $monty = [];
        if (count($data) > 0) {
            foreach ($data as $value) {
                $result['data'][] = $value[0];
                if (count($value[1]) > 0) {
                    $monty = $value[1];
                } elseif (count($value[2]) > 0 && count($monty) == 0) {
                    $monty = $value[2];
                }
            }
        }
        $club_id = $this->user->club_id;
        $year = intval(date('Y'));
        $month = intval(date('m'));
        $cost = ClubCoachManageCost::where("club_id", $club_id)->where("year", $year)->where("month",'<=',$month)->orderBy("month", 'desc')->pluck('cost');
        $dataarr = ClubCoachManageCost::where("club_id", $club_id)->orderBy("updated_at", 'desc')->select('updated_at','operation_user_name')->first();
        if($dataarr){
            $dataarr["cost"] = $cost[0];

        }
        $result['monty'] = $monty;
        return returnMessage('200', '请求成功', $result);
    }

//33.1教练费用添加
    public function coachadd(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'cost' => 'required|numeric',
            'examsDate' => 'required|date',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $examsDate = isset($input['examsDate']) ? $input['examsDate'] : '';
        $year = date('Y', strtotime($examsDate));
        $month = date('m', strtotime($examsDate));
        $club_id = $this->user->club_id;
        $channel = new ClubCoachManageCost();
        $res = $channel->where("year", $year)->where("month", $month)->where("club_id", $club_id)->where('is_delete', 0)->get();
        if (count($res) == 0) {
            $channel->club_id = $club_id;
            $channel->year = $year;
            $channel->month = $month;
            $channel->cost = $input['cost'];
            $channel->operation_user_id = $this->user->id;
            $channel->operation_user_name = $this->user->username;
            $channel->save();
        } else {
            return returnMessage('3002', config('error.sys.3002'));
        }

        return returnMessage('200', '');
    }

//34.教练费用修改
    public function coachedit(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'clubId' => 'required|numeric',
            'cost' => 'required|string',
            'examsDate' => 'required|date',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $examsDate = isset($input['examsDate']) ? $input['examsDate'] : '';
        $year = date('Y', strtotime($examsDate));
        $month = date('m', strtotime($examsDate));

        $examsres = ClubCoachManageCost::where("year", $year)->where("month", $month)->where("club_id", $input['clubId'])->where('is_delete', 0)->get();
        if (count($examsres) > 0) {
            $exams = ClubCoachManageCost::find($examsres[0]->id);
            $exams->cost = $input['cost'];
            $exams->club_id = $input['clubId'];
            $exams->year = $year;
            $exams->month = $month;
            $exams->operation_user_id = $this->user->id;
            $exams->operation_user_name = $this->user->username;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//33.2.教练费用删除
    public function coachdel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'coachId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $exams = ClubCoachManageCost::find($input['coachId']);
        if (count($exams) > 0) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }


############################短信################################
//35.1.短信发送列表club_message        club_message_template
    public function messagelist(Request $request)
    {
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
        $status = isset($data['status']) ? $data['status'] : '';  //0=未审核;1=审核通过;2=提交审核;-1=未通过审核'

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $exams = DB::table('club_message')->where('is_delete', 0);

        if (strlen($search) > 0) {
            $exams->where(function ($query) use ($search) {
                $query->where('operation_user_name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });

        }

        if (strlen($status) > 0) {
            $exams->where('status', $status);
        }

        $exams = $exams->where("club_id", $this->user->club_id)->orderBy('id','desc')->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $exams);
    }

    //35.1.1获取所有俱乐部
    public function getallclub(Request $request)
    {
        $exams = DB::table('club_club')->select('id', 'name')->where('is_delete', 0)->get();
        return returnMessage('200', '请求成功', $exams);
    }

    //35.1.1获取所有场馆
    public function getallvenue(Request $request)
    {
        $club_id = $this->user->club_id;
        $exams = DB::table('club_venue')->select('id', 'name')->where('club_id', $club_id)->where('status',1)->where('is_delete', 0)->get();
        return returnMessage('200', '请求成功', $exams);
    }

//35.1.1获取俱乐部下场馆
    public function getvenue(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'clubIds' => 'required|string',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $clubIds = isset($data['clubIds']) ? $data['clubIds'] : "";
        $arr = explode(',', $clubIds);
        $ids = join("','", $arr);
        $sql = "SELECT id,name FROM club_venue WHERE club_id in ('" . $ids . "') and status='1' and is_delete=0";
        $res = DB::select($sql);
        return returnMessage('200', '请求成功', $res);
    }

//35.1.2获取所有场馆下面班级
    public function getclass(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'venueIds' => 'required|string',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $venueIds = isset($data['venueIds']) ? $data['venueIds'] : "";
        $arr = explode(',', $venueIds);
        $ids = join("','", $arr);
        $sql = "SELECT id,name as class_name FROM club_class WHERE venue_id in ('" . $ids . "') and status='1' and is_delete=0";
        $res = DB::select($sql);
        return returnMessage('200', '请求成功', $res);
    }

//35.1.3获取班级下的学员
    public function getstudent(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'classIds' => 'required|string',
        ]);

        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $classIdsIds = isset($data['classIds']) ? $data['classIds'] : "";
        $over = isset($data['over']) ? (int)$data['over'] : 0;
        $freezeStatus = isset($data['freezeStatus']) ? (int)$data['freezeStatus'] : 0;
        $studentStatus = isset($data['studentStatus']) ? (int)$data['studentStatus'] : 0;
        /*
        代码优化 未完成 jesse
        $classIdArr = explode(',', $classIdsIds);
        $student = ClubClassStudent::select('id', 'className', 'student.name', 'student.id')->where([['is_delete', 0],['status', 1]])
            ->whereIn('id', $classIdArr)
            ->whereHas('student', function ($query) use ($over, $freezeStatus, $studentStatus){
                $query->where([['is_delete', 0], ['student_status', '<>', 3]]);
                $query->whereHas('student', function ($query1) use ($over, $freezeStatus, $studentStatus){
                    if ($over == 1) {
                        $query1->where('left_course_count', 0);
                    } elseif ($over == 2) {
                        $query1->whereBetween('left_course_count', [1, 3]);
                    } else {
                        $query1->where('left_course_coun', 4);
                    }
                    if ($freezeStatus == 2) {
                        $query1->where('is_freeze', 1);
                    } elseif ($freezeStatus == 1) {
                        $query1->where('is_freeze', 0);
                    }
                    if ($studentStatus == 1) {
                        $query1->where('status', 1);
                    } else {
                        $query1->where('status', '<>', 1);
                    }
                });
            })->toSql();
        echo $student;
        die;
        */
        $arr = explode(',', $classIdsIds);
        $result = array();
        foreach ($arr as $item) {
            $res = ClubClass::where('id', $item)
                ->select('id as classId', 'name as className')
                ->where('status', 1)->where('is_delete', 0)->get()->toArray();
            if (count($res) > 0) {
                $result[] = $res[0];
            }

        }
        $lastres = array();
        foreach ($result as $class) {
            $sql = "SELECT stu.id,stu.name 
                      FROM club_class_student as cstu 
                      left JOIN  club_student as stu on cstu.student_id = stu.id 
                      WHERE cstu.class_id = " . $class["classId"] . " 
                      and cstu.is_delete=0";
            if ($over > 0) {
                if ($over == 1) {
                    $sql .= " and stu.left_course_count = 0";
                } elseif ($over == 2) {
                    $sql .= " and stu.left_course_count between 1 and 3";
                } else {
                    $sql .= " and stu.left_course_count >=4 ";
                }
            }
            if ($freezeStatus > 0) {
                if($freezeStatus == 2){
                    $sql .= " and stu.is_freeze =1";
                }
                if($freezeStatus == 1){
                    $sql .= " and stu.is_freeze = 0";
                }
            }
            if ($studentStatus > 0) {
                if ($studentStatus == 1) {
                    $sql .= " and stu.status = " . $studentStatus;
                } else {
                    $sql .= " and stu.status = 2";
                }
            } else {
                $sql .= " and stu.status in (1,2)";;
            }
            $student = DB::select($sql);
            $class["students"] = $student;
            $lastres[] = $class;
        }
        return returnMessage('200', '请求成功', $lastres);
    }


//35.1.2短信发送模板列表
    public function messageTemplate(Request $request)
    {
        $template = ClubMessageTemplate::where('is_delete', 0)->get()->toArray();
        return returnMessage('200', '请求成功', $template);
    }

    //35.2.添加短信
    public function messageadd(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'templateType' => 'required|numeric',
            'content' => 'required|string',
            'venueIds' => 'required|string',
            'classIds' => 'required|string',
            'studentIds' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $message_template_type = isset($data['templateType']) ? $data['templateType'] : '';
        $content = isset($data['content']) ? $data['content'] : '';
        $club_ids = isset($data['clubIds']) ? $data['clubIds'] : '';
        $venue_ids = isset($data['venueIds']) ? $data['venueIds'] : '';
        $class_ids = isset($data['classIds']) ? $data['classIds'] : '';
        $send_student_ids = isset($data['studentIds']) ? $data['studentIds'] : '';
        $select_student_freeze_status = isset($data['freezeStatus']) ? (int)$data['freezeStatus'] : 0;
        $select_student_status = isset($data['studentStatus']) ? (int)$data['studentStatus'] : 0;
        $select_left_count = isset($data['over']) ? (int)$data['over'] : 0;
        $type = isset($data['type']) ? $data['type'] : '';

        $exams = new ClubMessage();
        $exams->message_template_type = $message_template_type;
        $exams->venue_ids = $venue_ids;
        $exams->club_id = $this->user->club_id;
        $exams->class_ids = $class_ids;
        $exams->select_student_freeze_status = $select_student_freeze_status;
        $exams->select_student_status = $select_student_status;
        $exams->content = $content;
        $exams->send_student_ids = $send_student_ids;
        $exams->send_person_count = count(explode(',',$send_student_ids));
        $exams->select_left_count = $select_left_count;
        $exams->content = $content;
        if (strlen($type) > 0) {
            $exams->type = $type;
        }
        $exams->operation_user_id = $this->user->id;
        $exams->operation_user_name = $this->user->username;

        $exams->status = 0;
        $exams->save();
        return returnMessage('200', '');
    }

//35.3.修改短信显示
    public function messageeditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'messageId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubMessage::where('id', $input['messageId'])->get()->toArray();
        $student = $exams[0]['send_student_ids'];
        $arr = explode(',', $student);
        if (count($arr) > 0) {
            $ids = join("','", $arr);
            $sql = "SELECT id,name FROM club_student WHERE id in ('" . $ids . "')";
            $res = DB::select($sql);
            $exams[]['Student'] = $res;
        } else {
            $exams[]['Student'] = array();
        }

        return returnMessage('200', '', $exams);
    }

//35.4.修改短信
    public function messageedit(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'messageId' => 'required|numeric',
            'templateType' => 'required|numeric',
            'content' => 'required|string',
            'venueIds' => 'required|string',
            'classIds' => 'required|string',
            'studentIds' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $message_template_type = isset($data['templateType']) ? $data['templateType'] : '';
        $content = isset($data['content']) ? $data['content'] : '';
        $club_ids = isset($data['clubIds']) ? $data['clubIds'] : '';
        $venue_ids = isset($data['venueIds']) ? $data['venueIds'] : '';
        $class_ids = isset($data['classIds']) ? $data['classIds'] : '';
        $send_student_ids = isset($data['studentIds']) ? $data['studentIds'] : '';
        $select_student_freeze_status = isset($data['freezeStatus']) ? (int)$data['freezeStatus'] : 0;
        $select_student_status = isset($data['studentStatus']) ? (int)$data['studentStatus'] : 0;
        $select_left_count = isset($data['over']) ? (int)$data['over'] : 0;
        $type = isset($data['type']) ? $data['type'] : '';

        $exams = ClubMessage::find($data['messageId']);
        if (!empty($exams)) {
            $exams->message_template_type = $message_template_type;
            $exams->venue_ids = $venue_ids;
            $exams->club_ids = $club_ids;
            $exams->class_ids = $class_ids;
            $exams->select_student_freeze_status = $select_student_freeze_status;
            $exams->select_student_status = $select_student_status;
            $exams->content = $content;
            $exams->send_student_ids = $send_student_ids;
            $exams->send_person_count = count(explode(',',$send_student_ids));
            $exams->select_left_count = $select_left_count;
            $exams->content = $content;
            if (strlen($type) > 0) {
                $exams->type = $type;
            }
            $exams->operation_user_id = $this->user->id;
            $exams->operation_user_name = $this->user->username;
            $exams->status = 0;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//35.5.删除短信
    public function messagedel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'messageId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubMessage::find($input['messageId']);
        if (count($exams) > 0) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

    //35.6.短信提交审核
    public function messagecheck(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'messageId' => 'required|numeric',
            'status' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubMessage::with(['club:id,name','smstemplate'])->find($input['messageId']);

        if (empty($exams)) {
            return returnMessage('2801',config('error.sms.2801'));
        }

        $exams->status = $input['status'];
        try {
            $exams->saveOrFail();
        } catch (Exception $e) {
            return returnMessage('2802',config('error.sms.2802'));
        }

        //向动博士推送短信提交记录
        $paramData = [
            'clubId' => $exams->club_id,
            'clubMessageId' => $exams->id,
            'content' => $exams->content,
            'venueIds' => $exams->venue_ids,
            'classIds' => $exams->class_ids,
            'studentIds' => $exams->send_student_ids,
            'clubName' => $exams->club ? $exams->club->name : '',
            'templateCode' => $exams->code
        ];

        Log::setGroup('SmsError')->error('俱乐部推送短信-参数',[$paramData]);
        $res = Common::addClubSmsPush($paramData);

        if ($res['code'] != '200') {
            $arr = [
                'code' => $res['code'],
                'msg' => $res['msg'],
                'paramData' => $paramData
            ];
            Log::setGroup('SmsError')->error('俱乐部推送短信-返回信息',[$arr]);
            return returnMessage('2803',config('error.sms.2803'));
        }
        return returnMessage('200', '推送成功');
    }


########################公告管理############################
//36.1.公告发送列表
    public function noticelist(Request $request)
    {
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
        $status = isset($data['status']) ? $data['status'] : '';  //0=未审核;1=审核通过;2=提交审核;-1=未通过审核'

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });
        $exams = DB::table('club_message_app')->where('is_delete', 0);

        if (strlen($search) > 0) {
            $exams->where(function ($query) use ($search) {
                $query->where('operation_user_name'  , 'like','%'.$search.'%')
                    ->orwhere('id', $search);
            });
        }

        if (strlen($status) > 0) {
            $exams->where('status', $status);
        }

        $exams = $exams->where('club_id', $this->user->club_id)->where('is_delete',0)->orderBy('id', 'DESC')->paginate($pagePerNum);
        return returnMessage('200', '请求成功', $exams);
    }

//36.2.添加公告
    public function noticeadd(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'messageTargetType' => 'required|numeric',
            'content' => 'required|string',
            'venueIds' => 'required|string',
            'classIds' => 'required|string',
            'studentIds' => 'required|string',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $messageTargetType = isset($data['messageTargetType']) ? $data['messageTargetType'] : '';
        $numurl = isset($data['numurl']) ? $data['numurl'] : '';
        $content = isset($data['content']) ? $data['content'] : '';

        $venue_ids = isset($data['venueIds']) ? $data['venueIds'] : '';
        $class_ids = isset($data['classIds']) ? $data['classIds'] : '';
        $send_student_ids = isset($data['studentIds']) ? $data['studentIds'] : '';
        $select_student_freeze_status = isset($data['freezeStatus']) ? $data['freezeStatus'] : '1';
        $select_student_status = isset($data['studentStatus']) ? $data['studentStatus'] : '';

        $select_left_count = isset($data['over']) ? $data['over'] : '';
        $type = isset($data['type']) ? $data['type'] : '';

        $exams = new ClubMessageApp();
        if (strlen($messageTargetType) > 0) {
            if ($messageTargetType == '3' || $messageTargetType == '4') {
                if (strlen($numurl) > 0) {
                    $exams->message_target_id = intval($numurl);
                }

            }
            if ($messageTargetType == '5') {
                if (strlen($numurl) > 0) {
                    $exams->message_target_url = $numurl;
                }
            }
        }
        $exams->venue_ids = $venue_ids;
        $exams->class_ids = $class_ids;
        $exams->content = $content;
        $exams->send_student_ids = $send_student_ids;

        $exams->send_person_count = count(explode(',',$send_student_ids));
        $exams->club_id = $this->user->club_id;
        $exams->operation_user_id = $this->user->id;

        $exams->operation_user_name = $this->user->username;
        $exams->message_target_type = $messageTargetType;
        $exams->select_student_freeze_status = $select_student_freeze_status;
        if (strlen($select_student_status) > 0) {
            $exams->select_student_status = $select_student_status;
        }
        if (strlen($select_left_count) > 0) {
            $exams->select_left_count = $select_left_count;
        }
        $exams->status = 0;
        if (strlen($type) > 0) {
            $exams->type = $type;
        }
        $exams->save();
        return returnMessage('200', '');
    }

//36.3.修改公告显示
    public function noticeeditshow(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'messageAppId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubMessageApp::where('id', $input['messageAppId'])->get()->toArray();
        $student = $exams[0]['send_student_ids'];
        $arr = explode(',', $student);
        if (count($arr) > 0) {
            $ids = join("','", $arr);
            $sql = "SELECT id,name FROM club_student WHERE id in ('" . $ids . "')";
            $res = DB::select($sql);
            $exams[]['Student'] = $res;
        } else {
            $exams[]['Student'] = array();
        }
        return returnMessage('200', '', $exams);
    }

//36.4.修改公告
    public function noticeedit(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'id' => 'required|numeric',
            'messageTargetType' => 'required|numeric',
            'content' => 'required|string',
            'venueIds' => 'required|string',
            'classIds' => 'required|string',
            'studentIds' => 'required|string',

        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $messageTargetType = isset($data['messageTargetType']) ? $data['messageTargetType'] : '';
        $numurl = isset($data['numurl']) ? $data['numurl'] : '';
        $content = isset($data['content']) ? $data['content'] : '';

        $venue_ids = isset($data['venueIds']) ? $data['venueIds'] : '';
        $class_ids = isset($data['classIds']) ? $data['classIds'] : '';
        $send_student_ids = isset($data['studentIds']) ? $data['studentIds'] : '';
        $select_student_freeze_status = isset($data['freezeStatus']) ? $data['freezeStatus'] : '1';
        $select_student_status = isset($data['studentStatus']) ? $data['studentStatus'] : '';

        $select_left_count = isset($data['over']) ? $data['over'] : '';
        $type = isset($data['type']) ? $data['type'] : '';

        $exams = ClubMessageApp::find($data['id']);
        if (!empty($exams)) {
            if (strlen($messageTargetType) > 0) {
                if ($messageTargetType == '3' || $messageTargetType == '4') {
                    if (strlen($numurl) > 0) {
                        $exams->message_target_id = intval($numurl);
                    }
                }
                if ($messageTargetType == '5') {
                    if (strlen($numurl) > 0) {
                        $exams->message_target_url = $numurl;
                    }
                }
            }
            $exams->venue_ids = $venue_ids;
            $exams->class_ids = $class_ids;
            $exams->content = $content;
            $exams->message_target_type = $messageTargetType;
            $exams->send_student_ids = $send_student_ids;
            $exams->send_person_count = count(explode(',',$send_student_ids));
            $exams->operation_user_id = $this->user->id;
            $exams->operation_user_name = $this->user->username;

            $exams->select_student_freeze_status = $select_student_freeze_status;
            if (strlen($select_student_status) > 0) {
                $exams->select_student_status = $select_student_status;
            }
            if (strlen($select_left_count) > 0) {
                $exams->select_left_count = $select_left_count;
            }
            $exams->status = 0;
            if (strlen($type) > 0) {
                $exams->type = $type;
            }
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//36.5.删除公告
    public function noticedel(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'messageAppId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $exams = ClubMessageApp::find($input['messageAppId']);
        if (count($exams) > 0) {
            $exams->is_delete = 1;
            $exams->update();
            return returnMessage('200', '');
        } else {
            return returnMessage('200', '修改失败');
        }
    }

//36.6.公告提交审核
    public function noticecheck(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'messageAppId' => 'required|numeric',
            'status' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $exams = ClubMessageApp::find($input['messageAppId']);
        if (!empty($exams)) {
            $exams->status = $input['status'];
            $exams->update();

            $clubName = Club::find($exams->club_id)->value('name');

            $url = env('HTTPS_PREFIX').env('APP_ADMIN_INNER_DOMAIN').'club/acceptClubPushNotice';
            $formData = [
                'clubNoticeId' => $exams->id,
                'clubId' => $exams->club_id,
                'content' => $exams->content,
                'venueIds' => $exams->venue_ids,
                'classIds' => $exams->class_ids,
                'studentIds' => $exams->send_student_ids,
                'leftCount' => $exams->getLeftCountKeyForApp($exams->select_left_count) ?? '',
                'targetUrl' => $exams->message_target_url ?? '',
                'respondType' => $exams->getRespondTypeNameForApp($exams->message_target_type) ?? '',
                'respondId' => $exams->message_target_id ?? '',
                'clubName' => $clubName ?? ''
            ];

            $response = Common::curlPost($url,1,$formData);
            $info = json_decode($response,true);

            if ($info['code'] != '200') {
                return returnMessage($info['code'],$info['msg']);
            }

            return returnMessage('200', '');
        } else {
            return returnMessage('200', '未找到数据');
        }
    }

}
