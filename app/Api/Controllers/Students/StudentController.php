<?php

namespace App\Api\Controllers\Students;

use App\Facades\ClubStudent\Student;
use App\Facades\Permission\Permission;
use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubChannel\Channel;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Model\ClubCourseTickets\CourseTickets;
use App\Model\ClubExamsGeneralLevel\ClubExamsGeneralLevel;
use App\Model\ClubExamsItems\ClubExamsItems;
use App\Model\ClubExamsItemsLevel\ClubExamsItemsLevel;
use App\Model\ClubExamsItemsStudent\ClubExamsItemsStudent;
use App\Model\ClubExamsStudent\ClubExamsStudent;
use App\Model\ClubIncomeSnapshot\ClubIncomeSnapshot;
use App\Model\ClubIncomSnapshot\ClubIncomSnapshot;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubSalesExamine\ClubSalesExamine;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentFeedback\ClubStudentFeedback;
use App\Model\ClubStudentFreeze\ClubStudentFreeze;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\ClubStudentSnapshot\ClubStudentSnapshot;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;
use App\Model\ClubUser\ClubUser;
use App\Model\ClubVenue\ClubVenue;
use App\Model\ClubStudentSalesHistory\ClubStudentSalesHistory;
use App\Model\Permission\Role;
use App\Model\Permission\User;
use App\Model\UcUserSecret\UcUserSecret;
use App\Services\Common\CommonService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Validation\Rule;
use App\Model\ClubStudentCore\ClubStudentCore;
use App\Model\ClubStudentRefund\ClubStudentRefund;
use App\Facades\Util\Common;

class StudentController extends Controller
{
    /**
     * @var CommonService
     */
    private $commonService;

    /**
     * StudentController constructor.
     * @param CommonService $commonService
     */
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    /**
     * 非正式学员
     * @param Request $request
     * @return array
     */
    public function notFormalStudents(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'search' => 'nullable|string',
            'venueId' => 'nullable|numeric',
            'classId' => 'nullable|numeric',
            'isExperience' => 'nullable|numeric',
            'sellerId' => 'nullable|numeric',
            'channelId' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }
        $search = isset($input['search']) ? $input['search'] : '';
        $venueId = isset($input['venueId']) ? $input['venueId'] : 0;
        $classId = isset($input['classId']) ? $input['classId'] : 0;
        $isExperience = isset($input['isExperience']) ? $input['isExperience'] : 0;
        $sellerId = isset($input['sellerId']) ? $input['sellerId'] : 0;
        $channelId = isset($input['channelId']) ? $input['channelId'] : 0;

        $roleType = Permission::getUserRoleType($input['user']['role_id']);
        $salesId = Permission::getSalesUserId($input['user']['id']);

        // 是否是当前部门负责人
//        $isLeader = Permission::isThisDeptLeader($input['user']['id'], $input['user']['dept_id']);
//
//        // 获取部门下所有销售，包括下面的
//        $departArr = [];
//        $deptArr = Permission::getDepartmentAllId($departArr, $input['user']['dept_id']);
//        $salesArr = ClubSales::where('club_id', $input['user']['club_id'])
//            ->whereIn('sales_dept_id', $deptArr)
//            ->pluck('id')
//            ->toArray();

        $studentList = ClubStudent::where('status', 2)
            // 姓名手机号搜索
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    return $query->where('name', 'like', '%'.$search.'%')->orWhere('guarder_mobile', $search);
                }
            })
            // 场馆搜索
            ->where(function ($query) use ($venueId) {
                if (!empty($venueId) && empty($classId)) {
                    return $query->where('venue_id', $venueId);
                }
            })
            // 场馆班级搜索
            ->where(function ($query) use ($venueId, $classId, $clubId) {
                if (!empty($venueId) && !empty($classId)) {
                    return $query->whereIn('id', ClubClassStudent::where('venue_id', $venueId)
                        ->where('club_id', $clubId)
                        ->where('class_id', $classId)
                        ->distinct()
                        ->pluck('student_id')
                        ->toArray());
                }
            })
            // 体验状态搜索
            ->where(function ($query) use ($isExperience) {
                if ($isExperience == 1) {
                    return $query->where('ex_status', 0);
                }
                if ($isExperience == 2) {
                    return $query->where('ex_status', 1);
                }
                if ($isExperience == 3) {
                    return $query->where('ex_status', 2);
                }
            })
            // 销售员搜索
            ->where(function ($query) use ($sellerId) {
                if (!empty($sellerId)) {
                    return $query->where('sales_id', $sellerId);
                }
            })
            // 渠道来源搜索
            ->where(function ($query) use ($channelId) {
                if (!empty($channelId)) {
                    return $query->where('channel_id', $channelId);
                }
            })
            // 销售只可看到自己名下的学员，销售负责人可看到下面所有销售名下的学员
//            ->where(function ($query) use ($roleType, $salesId, $isLeader, $salesArr) {
//                if ($roleType == 2) {
//                    if ($isLeader == 1) {
//                        return $query->where('sales_id', $salesId);
//                    }
//                    if ($isLeader == 2) {
//                        return $query->whereIn('sales_id', $salesArr);
//                    }
//                }
//            })
            ->where('club_id', $clubId)
            ->orderBy('id', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $studentList->total();
        $list['result'] = $studentList->transform(function ($items) {
            $arr['studentId'] = $items->id;
            $arr['name'] = $items->name;
            $arr['venueName'] = $this->studentVenue($items->venue_id);
            $arr['className'] = $this->studentClass($items->main_class_id);
            $arr['age'] = Common::getAgeByBirthday($items->birthday);
            $arr['isExperience'] = $items->ex_status;
            $arr['seller'] = ClubSales::where('id', $items->sales_id)->value('sales_name');
            $arr['channel'] = Channel::where('id', $items->channel_id)->value('channel_name');
            $arr['phone'] = $items->guarder_mobile;
            $arr['laveCont'] = $items->left_course_count;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    // 获取俱乐部所有学员id
    public function clubAllStudentIds($clubId)
    {
        $venueIds = ClubVenue::where('club_id', $clubId)->pluck('id'); // 场馆id
        $classIds = ClubClass::whereIn('venue_id', $venueIds)->pluck('id'); // 班级id
        $studentIds = ClubClassStudent::whereIn('class_id', $classIds)->pluck('id'); // 学员id
        return array_unique($studentIds->toArray());
    }

    // 获取学员相应的销售员
    public function studentSales($salesId)
    {
        $userId = ClubSales::where('id', $salesId)->value('user_id');
        $username = ClubUser::where('id', $userId)->value('username');
        return $username;
    }

    // 非正式学员 - 新增-检测姓名、手机是否已存在
    public function addStudentCheck(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'nameCh' => 'required|string',
            'phone' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $arr['hasName'] = ClubStudent::where('name', $input['nameCh'])->exists();
        $arr['hasTel'] = ClubStudent::where('guarder_mobile', $input['phone'])->exists();
        return returnMessage('200', '', $arr);
    }

    /**
     * 非正式学员 - 新增学员
     * @param Request $request
     * @return array
     */
    public function addStudent(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'nameCh' => 'required|string',
            'nameEn' => 'nullable|string',
            'guardian' => 'required|numeric',
            'phone' => ['required','regex:/^1[3|4|5|7|8]\d{9}$/'],
            'backupPhone' => ['nullable','regex:/^1[3|4|5|7|8]\d{9}$/'],
            'birthday' => 'required|string',
            'sex' => 'required|numeric',
            'sellerId' => 'required|numeric',
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'channelId' => 'required|numeric',
            'remark' => 'nullable|string',
            'cardType' => 'nullable|numeric',
            'idCard' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $nameEn = isset($input['nameEn']) ? $input['nameEn'] : '';
        $backupPhone = isset($input['backupPhone']) ? $input['backupPhone'] : '';
        $remark = isset($input['remark']) ? $input['remark'] : '';
        $cardType = isset($input['cardType']) ? $input['cardType'] : 0;
        $idCard = isset($input['idCard']) ? $input['idCard'] : '';

        // 手机号和姓名同时存在，不允许添加
        $bool = ClubStudent::where('club_id', $input['user']['club_id'])
            ->where('name', $input['nameCh'])
            ->where('guarder_mobile', $input['phone'])
            ->exists();
        if ($bool === true) {
            return returnMessage('1615', config('error.Student.1615'));
        }

        // 检测学员身份证信息
        if ($cardType == 1 && !empty($idCard)) {
            $checkRes = Common::idCardCheck($idCard, $input['nameCh']);
            if ($checkRes['code'] != '200') {
                return returnMessage('1642', config('error.Student.1642'));
            }
        }

        $student = DB::transaction(function () use($input, $clubId, $cardType, $idCard, $nameEn, $backupPhone, $remark) {
            // 添加学员证件
            $coreId = 0;
            if ($cardType != 0 && !empty($idCard)) {
                $coreId = $this->addStudentCore($input);
            }

            // 添加学员信息
            $studentId = $this->addClubStudent($input, $coreId, $nameEn, $backupPhone, $remark);

            // 同俱乐部同班级只能添加一个学员
            $bool = ClubClassStudent::where('club_id', $clubId)
                ->where('venue_id', $input['venueId'])
                ->where('class_id', $input['classId'])
                ->where('student_id', $studentId)
                ->where('is_delete', 0)
                ->exists();
            if ($bool === true) {
                return true;
            }

            // 添加学员班级
            $this->addClubClassStudent($input, $studentId);

            // 添加历史销售
            $this->startAddStudentSalesHistory($input, $studentId);

            // 添加学员快照
            $this->startModifyStudentSnapshot($studentId, 1, $clubId);

            // 增加学员数
            Club::where('id', $clubId)->increment('student_count');
            ClubVenue::where('id', $input['venueId'])->increment('student_count');
            ClubClass::where('id', $input['classId'])->increment('student_count');
        });
        if ($student === true) {
            return returnMessage('1615', config('error.Student.1615'));
        }

        return returnMessage('200', '');
    }

    /**
     * 添加证件信息
     * @param $input
     * @return mixed
     */
    public function addStudentCore($input)
    {
        $core = ClubStudentCore::where('card_type', $input['cardType'])
            ->where('card_no', $input['idCard'])
            ->first();
        if (!empty($core)) {
            return $core->id;
        }
        $insertData = [
            'chinese_name' => $input['nameCh'],
            'card_type' => $input['cardType'],
            'card_no' => $input['idCard'],
            'created_at' => date('Y-m-d', time()),
            'updated_at' => date('Y-m-d', time())
        ];
        if (!empty($input['nameEn'])) {
            $insertData['english_name'] = $input['nameEn'];
        }
        $coreId = ClubStudentCore::insertGetId($insertData);
        return $coreId;
    }

    /**
     * 添加学员信息
     * @param $input
     * @param $coreId
     * @param $nameEn
     * @param $backupPhone
     * @param $remark
     * @return mixed
     */
    public function addClubStudent($input, $coreId, $nameEn, $backupPhone, $remark)
    {
        $student = ClubStudent::where('name', $input['nameCh'])
            ->where('guarder_mobile', $input['phone'])
            ->where('club_id', $input['user']['club_id'])
            ->first();
        if (empty($student) === false) {
            return $student->id;
        }

        $insertData['club_id'] = $input['user']['club_id'];
        if ($coreId != 0) {
            $insertData['core_id'] = $coreId;
        }
        $insertData['name'] = $input['nameCh'];
        $insertData['english_name'] = $nameEn;
        $insertData['guarder'] = $input['guardian'];
        $insertData['guarder_mobile'] = $input['phone'];
        $insertData['guarder_backup_phone'] = $backupPhone;
        $insertData['birthday'] = $input['birthday'];
        $insertData['status'] = 2;
        $insertData['sex'] = $input['sex'];
        $insertData['sales_id'] = $input['sellerId'];
        $insertData['sales_name'] = ClubSales::where('id', $input['sellerId'])->value('sales_name');
        $insertData['venue_id'] = $input['venueId'];
        $insertData['main_class_id'] = $input['classId'];
        $insertData['main_class_name'] = ClubClass::where('id', $input['classId'])->value('name');
        $insertData['channel_id'] = $input['channelId'];
        $insertData['channel_name'] = Channel::where('id', $input['channelId'])->value('channel_name');
        $insertData['age'] = Carbon::parse($input['birthday'])->diffInYears();
        $insertData['remark'] = $remark;
        $insertData['serial_no'] = Common::buildStudentSerialNo(); // 序列号
        $insertData['created_at'] = date('Y-m-d H:i:s', time());
        $insertData['updated_at'] = date('Y-m-d H:i:s', time());
        $studentId = ClubStudent::insertGetId($insertData);
        return $studentId;
    }

    // 添加学员班级信息
    public function addClubClassStudent($input, $studentId)
    {
        $clubClassStudent = new ClubClassStudent();
        $clubClassStudent->club_id = $input['user']['club_id'];
        $clubClassStudent->venue_id = $input['venueId'];
        $clubClassStudent->sales_id = $input['sellerId'];
        $clubClassStudent->class_id = $input['classId'];
        $clubClassStudent->student_id = $studentId;
        $clubClassStudent->student_name = $input['nameCh'];
        $clubClassStudent->is_main_class = 1;
        $clubClassStudent->enter_class_time = Carbon::now()->format('Y-m-d H:i:s');
        $clubClassStudent->save();
    }

    // 添加学员销售
    public function startAddStudentSalesHistory($input, $studentId)
    {
        $salesHistory = new ClubStudentSalesHistory();
        $salesHistory->student_id = $studentId;
        $salesHistory->sales_id = $input['sellerId'];
        $salesHistory->sales_name = ClubSales::where('id', $input['sellerId'])->value('sales_name');
        $salesHistory->operation_userid = $input['user']['id'];
        $salesHistory->operation_username = ClubUser::where('id', $input['user']['id'])->value('username');
        $salesHistory->start_date = date('Y-m-d H:i:s', time());
        $salesHistory->save();
    }
    // 结束学员销售
    public function endAddStudentSalesHistory($studentId)
    {
        ClubStudentSalesHistory::where('student_id', $studentId)
            ->where('end_date', null)
            ->update(['end_date' => date('Y-m-d H:i:s', time())]);
    }

    // 添加学员快照
    public function startModifyStudentSnapshot($studentId, $type, $clubId)
    {
        $snapshot = new ClubStudentSnapshot();
        $snapshot->student_id = $studentId;
        $snapshot->club_id = $clubId;
        $snapshot->start_date = date('Y-m-d', time());
        $snapshot->student_status = $type;
        $snapshot->save();
    }

    // 结束学员快照
    public function endModifyStudentSnapshot($studentId, $type)
    {
        ClubStudentSnapshot::where('student_id', $studentId)
            ->where('student_status', $type)
            ->where('end_date', null)
            ->update(['end_date' => date('Y-m-d', time())]);
    }

    /**
     * 学员失效生效
     * @param Request $request
     * @return array
     */
    public function studentsFailure(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'type' => 'required|numeric',
            'studentId' => 'required|numeric',
            'sellerId' => 'nullable|numeric',
            'venueId' => 'nullable|numeric',
            'classId' => 'nullable|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 学员不存在
        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $clubId = $input['user']['club_id'];
        $studentId = $input['studentId'];
        $salesId = isset($input['sellerId']) ? $input['sellerId'] : 0;
        $venueId = isset($input['venueId']) ? $input['venueId'] : 0;
        $classId = isset($input['classId']) ? $input['classId'] : 0;

        if ($input['type'] == 1) {
            DB::transaction(function () use ($input, $clubId, $salesId, $venueId, $classId, $studentId, $student) {
                // 添加学员班级
                $clubClassStudent = new ClubClassStudent();
                $clubClassStudent->club_id = $input['user']['club_id'];
                $clubClassStudent->venue_id = $input['venueId'];
                $clubClassStudent->sales_id = $input['sellerId'];
                $clubClassStudent->class_id = $input['classId'];
                $clubClassStudent->student_id = $studentId;
                $clubClassStudent->student_name = $student->name;
                $clubClassStudent->is_main_class = 1;
                $clubClassStudent->enter_class_time = Carbon::now()->format('Y-m-d H:i:s');
                $clubClassStudent->save();

                // 添加历史销售
                $this->startAddStudentSalesHistory($input, $studentId);

                // 添加学员快照
                $this->startModifyStudentSnapshot($studentId, 1, $clubId);

                // 更新为非正式学员
                ClubStudent::where('id', $input['studentId'])
                    ->where('club_id', $clubId)
                    ->update(['status' => 2]);
            });
        }

        if ($input['type'] == 2) {
            DB::transaction(function () use ($input, $clubId) {
                // 更新销售结束时间
                $this->endAddStudentSalesHistory($input['studentId']);

                // 更新快照结束时间
                $this->endModifyStudentSnapshot($input['studentId'], $input['type']);

                // 添加学员快照
                $this->startModifyStudentSnapshot($input['studentId'], 3, $clubId);

                // 清除学员课程总数，添加到公海库
                ClubStudent::where('id', $input['studentId'])
                    ->update([
                        'status' => 3,
                        'left_course_count' => 0,
                        'where_to_public_sea' => $input['type'],
                        'why_to_public_sea' => 2
                    ]);

                // 主班级更新为历史主班级
                ClubClassStudent::where('student_id', $input['studentId'])
                    ->where('is_main_class', 1)
                    ->update(['is_main_class' => 0, 'is_history_main_class' => 1]);

                // 更改课程券状态为失效
                CourseTickets::where('student_id', $input['studentId'])
                    ->where('club_id', $clubId)
                    ->update(['status' => 3]);
            });
        }

        return returnMessage('200', '');
    }

    /**
     * excel模板下载
     * @return array
     */
    public function downloadStudentExcel()
    {
        $cellData = [
            ['姓名', '性别', '年龄', '身份证号', '销售员编号', '班级编号', '手机', '渠道编号', '状态']
        ];

        Excel::create('学员导入模板',function($excel) use ($cellData) {
            $excel->sheet('score', function($sheet) use ($cellData) {
                $sheet->rows($cellData);
            });
        })->export('xls');

        return returnMessage('200', '');
    }

    /**
     * 学员excel上传
     * @param Request $request
     * @return array
     */
    public function uploadStudentExcel(Request $request)
    {
        if (!$request->hasFile('studentExport')) {
            return returnMessage('1012', config('error.common.1012'));
        }

        $input = $request->file('studentExport');

        $ext = $input->getClientOriginalExtension();

        $filePrefix = 'student' . date('YmdHis', time());
        $newName = $filePrefix . '.' . $ext;
        $input->storeAs('stuExcel', $newName);

        $list['filePrefix'] = $filePrefix;

        return returnMessage('200', '', $list);
    }

    /**
     * 获取上传学员数据
     * @param Request $request
     * @return array
     */
    public function studentImportData(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'filePrefix' => 'required|string',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $filePath = 'storage/app/stuExcel/'.iconv('UTF-8', 'GBK//IGNORE', $input['filePrefix']).'.xls';
        $excelArr = [];
        Excel::load($filePath, function($reader) use ($input, &$excelArr) {
            $data = $reader->getSheet(0);
            $excelArr = $data->toArray();
        });

        $stuIdArr = [];
        foreach ($excelArr as $key => $value) {
            if ($key == 0) continue;

            $stuIdArr[$key-1]['id'] = $key;
            $stuIdArr[$key-1]['name'] = $value[0];
            $stuIdArr[$key-1]['sex'] = $value[1];
            $stuIdArr[$key-1]['age'] = $value[2];
            $stuIdArr[$key-1]['idCard'] = !empty($value[3]) ? $value[3] : "";
            $stuIdArr[$key-1]['salesId'] = $value[4];
            $stuIdArr[$key-1]['classId'] = $value[5];
            $stuIdArr[$key-1]['mobile'] = $value[6];
            $stuIdArr[$key-1]['channelId'] = $value[7];
            $stuIdArr[$key-1]['status'] = $value[8];
        }

        $start = ($input['currentPage'] - 1) * $input['pagePerNum'];
        $end = $input['currentPage'] * $input['pagePerNum'] - 1;

        $result = [];
        foreach ($stuIdArr as $key => $value) {
            if ($key < $start || $key > $end) continue;

            $result[$key]['id'] = $key+1;
            $result[$key]['name'] = $value['name'];
            $result[$key]['sex'] = $value['sex'];
            $result[$key]['age'] = $value['age'];
            $result[$key]['idCard'] = !empty($value['idCard']) ? $value['idCard'] : "";
            $result[$key]['salesId'] = $value['salesId'];
            $result[$key]['classId'] = $value['classId'];
            $result[$key]['mobile'] = $value['mobile'];
            $result[$key]['channelId'] = $value['channelId'];
            $result[$key]['status'] = $value['status'];
        }

        $list['totalNum'] = count($stuIdArr);
        $list['result'] = $result;

        return returnMessage('200', '', $list);
    }

    /**
     * 非正式学员批量导入
     * @param Request $request
     * @return array
     */
    public function studentsImport(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'filePrefix' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $filePath = 'storage/app/stuExcel/'.iconv('UTF-8', 'GBK//IGNORE', $input['filePrefix']).'.xls';

        $excelArr = [];
        Excel::load($filePath, function($reader) use ($input, &$excelArr) {
            $data = $reader->getSheet(0);
            $excelArr = $data->toArray();
        });

        foreach ($excelArr as $key => $value) {
            if ($key == 0) continue;

            DB::transaction(function () use ($input, $key, $value) {
                if (!empty($value[3])) {
                    $stuCore = new ClubStudentCore();
                    $stuCore->chinese_name = $value[0];
                    $stuCore->card_type = 1;
                    $stuCore->card_no = $value[3];
                    $stuCore->save();
                    $coreId = $stuCore->id;
                }

                $student = new ClubStudent();
                $student->club_id = $input['user']['club_id'];
                if (!empty($value[3])) {
                    $student->core_id = $coreId;
                }
                $student->name = $value[0];
                $student->sex = $value[1]=="男" ? 1 : 2;
                $student->age = $value[2];
                $student->birthday = Common::getBirthdayByAge($value[2]);
                $student->sales_id = $value[4];
                $student->sales_name = ClubSales::where('id', $value[4])->value('sales_name');
                $student->venue_id = ClubClass::where('id', $value[5])->value('venue_id');
                $student->main_class_id = $value[5];
                $student->main_class_name = ClubClass::where('id', $value[5])->value('name');
                $student->guarder_mobile = $value[6];
                $student->channel_id = $value[7];
                $student->channel_name = Channel::where('id', $value[7])->value('channel_name');
                $student->status = 2;
                $student->serial_no = Common::buildStudentSerialNo();
                $student->save();
                $stuId = $student->id;

                $stuClass = new ClubClassStudent();
                $stuClass->club_id = $input['user']['club_id'];
                $stuClass->venue_id = ClubClass::where('id', $value[5])->value('venue_id');
                $stuClass->class_id = $value[5];
                $stuClass->student_id = $stuId;
                $stuClass->student_name = $value[0];
                $stuClass->is_main_class = 1;
                $stuClass->enter_class_time = Carbon::now()->format('Y-m-d');
                $stuClass->save();
            });
        }

        unlink(storage_path('app/stuExcel/'.$input['filePrefix'].'.xls'));

        return returnMessage('200', '');
    }

    /**
     * 正式学员
     * @param Request $request
     * @return array
     */
    public function formalStudents(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'search' => 'nullable|string',
            'venueId' => 'nullable|numeric',
            'classId' => 'nullable|numeric',
            'studentStatus' => 'nullable|numeric',
            'sellerId' => 'nullable|numeric',
            'channelId' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }
        $search = isset($input['search']) ? $input['search'] : '';
        $venueId = isset($input['venueId']) ? $input['venueId'] : 0;
        $classId = isset($input['classId']) ? $input['classId'] : 0;
        $studentStatus = isset($input['studentStatus']) ? $input['studentStatus'] : 0;
        $sellerId = isset($input['sellerId']) ? $input['sellerId'] : 0;
        $channelId = isset($input['channelId']) ? $input['channelId'] : 0;

        $roleType = Permission::getUserRoleType($input['user']['role_id']);
        $salesId = Permission::getSalesUserId($input['user']['id']);

        // 是否是当前部门负责人
//        $isLeader = Permission::isThisDeptLeader($input['user']['id'], $input['user']['dept_id']);
//
//        // 获取部门下所有销售，包括下面的
//        $departArr = [];
//        $deptArr = Permission::getDepartmentAllId($departArr, $input['user']['dept_id']);
//        $salesArr = ClubSales::where('club_id', $input['user']['club_id'])
//            ->whereIn('sales_dept_id', $deptArr)
//            ->pluck('id')
//            ->toArray();

        $studentList = ClubStudent::where('status', 1)
            // 姓名手机号搜索
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    return $query->where('name', 'like', '%'.$search.'%')->orWhere('guarder_mobile', $search);
                }
            })
            // 场馆搜索
            ->where(function ($query) use ($venueId) {
                if (!empty($venueId) && empty($classId)) {
                    return $query->where('venue_id', $venueId);
                }
            })
            // 场馆班级搜索
            ->where(function ($query) use ($venueId, $classId, $clubId) {
                if (!empty($venueId) && !empty($classId)) {
                    return $query->whereIn('id', ClubClassStudent::where('venue_id', $venueId)
                        ->where('club_id', $clubId)
                        ->where('class_id', $classId)
                        ->distinct()
                        ->pluck('student_id')
                        ->toArray());
                }
            })
            // 学员状态搜索
            ->where(function ($query) use ($studentStatus) {
                if (!empty($studentStatus)) {
                    return $query->where('is_pay_again', $studentStatus);
                }
            })
            // 销售员搜索
            ->where(function ($query) use ($sellerId) {
                if (!empty($sellerId)) {
                    return $query->where('sales_id', $sellerId);
                }
            })
            // 渠道来源搜索
            ->where(function ($query) use ($channelId) {
                if (!empty($channelId)) {
                    return $query->where('channel_id', $channelId);
                }
            })
            // 销售只可看到自己名下的学员，销售负责人可看到下面所有销售名下的学员
//            ->where(function ($query) use ($roleType, $salesId, $isLeader, $salesArr) {
//                if ($roleType == 2) {
//                    if ($isLeader == 1) {
//                        return $query->where('sales_id', $salesId);
//                    }
//                    if ($isLeader == 2) {
//                        return $query->whereIn('sales_id', $salesArr);
//                    }
//                }
//            })
            ->where('club_id', $input['user']['club_id'])
            ->orderBy('id', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $studentList->total();
        $list['result'] = $studentList->transform(function ($items) {
            $arr['studentId'] = $items->id;
            $arr['name'] = $items->name;
            $arr['age'] = Common::getAgeByBirthday($items->birthday);
            $arr['venueName'] = $this->studentVenue($items->venue_id);
            $arr['className'] = $this->studentClass($items->main_class_id);
            $arr['seller'] = ClubSales::where('id', $items->sales_id)->value('sales_name');
            $arr['channel'] = Channel::where('id', $items->channel_id)->value('channel_name');
            $arr['phone'] = $items->guarder_mobile;
            $arr['laveCont'] = $items->left_course_count;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    // 获取场馆名称
    public function studentVenue($venueId)
    {
        $name = ClubVenue::where('id', $venueId)->value('name');
        return $name;
    }

    // 获取班级名称
    public function studentClass($classId)
    {
        $name = ClubClass::where('id', $classId)->value('name');
        return $name;
    }

    /**
     * 学员概况
     * @param Request $request
     * @return array
     */
    public function studentDetail(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }
        // 装备发放情况
        $equipment = ClubStudentPayment::where('student_id', $student->id)
            ->where('club_id', $clubId)
            ->value('equipment_issend');
        if ($equipment == 0) {
            $equipment = '未发放';
        }
        if ($equipment == 1) {
            $equipment = '已发放';
        }
        // 学员证件信息
        $core = ClubStudentCore::find($student->core_id);
        // 学员冻结状态
        if ($student->is_freeze == 0) {
            $freeze = '未冻结';
        }
        if ($student->is_freeze == 1) {
            $freeze = '已冻结';
        }

        $list['isNewData'] = false; // 未定
        $list['baseMsg']['studentId'] = $student->id;
        $list['baseMsg']['nameCh'] = $student->name;
        $list['baseMsg']['nameEn'] = $student->english_name;
        $list['baseMsg']['seller'] = ClubSales::where('id', $student->sales_id)->value('sales_name');
        $list['baseMsg']['sellerId'] = $student->sales_id;
        if (empty($core)) {
            $list['baseMsg']['postCardType'] = 0;
            $list['baseMsg']['postCard'] = '';
        }
        if (!empty($core)) {
            $list['baseMsg']['postCardType'] = $core->card_type;
            $list['baseMsg']['postCard'] = $core->card_no;
        }
        $list['baseMsg']['sex'] = $student->sex;
        $list['baseMsg']['age'] = Carbon::parse($student->birthday)->diffInYears();
        $list['baseMsg']['birthday'] = $student->birthday;
        $list['baseMsg']['guardian'] = $student->guarder;
        $list['baseMsg']['serialNumber'] = $student->serial_no;
        $list['baseMsg']['equipmentGrant'] = $equipment;
        $list['baseMsg']['bindAPPList'] = Student::studentBindAppList($input['studentId']);
        $list['baseMsg']['phone'] = $student->guarder_mobile;
        $list['baseMsg']['backupPhone'] = $student->guarder_backup_phone;
        $list['baseMsg']['channelId'] = $student->channel_id;
        $list['baseMsg']['channelName'] = Channel::where('id', $student->channel_id)->value('channel_name');
        $list['baseMsg']['laveCount'] = $student->left_course_count;
        $list['baseMsg']['studentStatus'] = $freeze;
        $list['baseMsg']['joinTime'] = $student->created_at->format('Y-m-d H:i:s');
        $list['baseMsg']['isFreeze'] = $student->is_freeze;
        $list['baseMsg']['remark'] = $student->remark;
        // 上次冻结时间，当学员冻结处于冻结状态才返回
        if ($student->is_freeze == 1) {
            $lastFreezeId = ClubStudentFreeze::where('student_id', $student->id)->max('id') - 1;
            $list['baseMsg']['lastFreezeDate'] = ClubStudentFreeze::where('id', $lastFreezeId)
                ->value('created_at')->format('Y-m-d H:i:s');
        }
        if ($student->status == 3) {
            $list['baseMsg']['isInvalid'] = true;
        } else {
            $list['baseMsg']['isInvalid'] = false;
        }
        // 学员相关统计数据
        $list['statisticsMsg'] = [
            "attendance"                => Student::getStuSignStatusTimes($clubId, $student->id, 1),
            "absencesCount"             => Student::getStuSignStatusTimes($clubId, $student->id, 2),
            "workLeave"                 => Student::getStuSignStatusTimes($clubId, $student->id, 3),
            "sickLeave"                 => Student::getStuSignStatusTimes($clubId, $student->id, 4),
            "mvpCount"                  => Student::getStuSignMvpTimes($clubId, $student->id),
            "payAmount"                 => Student::getStuPaymentAmount($clubId, $student->id),
            "stuSignIncome"             => Student::getStuCourseSignIncome($clubId, $student->id),
            "exCourseCount"             => Student::getStudentCourseCount($clubId, $student->id, 2, 1),
            "exLeftCourseCount"         => Student::getStudentCourseCount($clubId, $student->id, 2, 2),
            "formalCourseCount"         => Student::getStudentCourseCount($clubId, $student->id, 1, 1),
            "formalLeftCourseCount"     => Student::getStudentCourseCount($clubId, $student->id, 1, 2),
            "giveCourseCount"           => Student::getStudentCourseCount($clubId, $student->id, 3, 1),
            "giveLeftCourseCount"       => Student::getStudentCourseCount($clubId, $student->id, 3, 2)
        ];

        return returnMessage('200', '', $list);
    }

    /**
     * 修改学员
     * @param Request $request
     * @return array
     */
    public function modifyStudentMsg(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'nameCh' => 'required|string',
            'nameEn' => 'nullable|string',
            'guardian' => 'required|string',
            'phone' => 'required|string',
            'backupPhone' => 'nullable|string',
            'birthday' => 'required|date:Y-m-d',
            'sex' => 'required|numeric',
            'channelId' => 'required|numeric',
            'remark' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $nameEn = isset($input['nameEn']) ? $input['nameEn'] : "";
        $backupPhone = isset($input['backupPhone']) ? $input['backupPhone'] : "";
        $remark = isset($input['remark']) ? $input['remark'] : "";

        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        // APP注册学员手机号不允许修改
        if ($student->channel_id == 4 && $input['phone'] != $student->guarder_mobile) {
            return returnMessage('1631', config('error.Student.1631'));
        }

        DB::transaction(function () use ($input, $student, $nameEn, $backupPhone, $remark) {
            $student->name = $input['nameCh'];
            $student->english_name = $nameEn;
            $student->guarder = $input['guardian'];
            $student->guarder_mobile = $input['phone'];
            $student->guarder_backup_phone = $backupPhone;
            $student->birthday = $input['birthday'];
            $student->age = Carbon::parse($input['birthday'])->diffInYears();
            $student->sex = $input['sex'];
            $student->channel_id = $input['channelId'];
            $student->channel_name = Channel::where('id', $input['channelId'])->value('channel_name');
            $student->remark = $remark;
            $student->save();
        });

        return returnMessage('200', '');
    }

    /**
     * 冻结/解冻
     * @param Request $request
     * @return array
     */
    public function modifyStudentStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId'     => 'required|numeric',
            'type'          => 'required|numeric',
            'remark'        => 'nullable|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        // 非正式学员不可操作冻结
        if ($student->status != 1) {
            return returnMessage('1686', config('error.Student.1686'));
        }

        $stuClassId = ClubClassStudent::where('club_id', $input['user']['club_id'])
            ->where('student_id', $input['studentId'])
            ->pluck('class_id');

        $stuVenueId = ClubClassStudent::where('club_id', $input['user']['club_id'])
            ->where('student_id', $input['studentId'])
            ->pluck('venue_id');

        // 解冻
        if ($input['type'] == 0) {
            DB::transaction(function () use ($student, $input, $stuVenueId, $stuClassId) {
                // 更新学员冻结字段
                $student->is_freeze = $input['type'];
                $student->freeze_id = 0;
                $student->save();

                // 更新学员解冻时间
                ClubStudentFreeze::where('id', $student->freeze_id)
                    ->update(['freeze_end_date' => Carbon::now()->format('Y-m-d')]);

                // 更新学员数
                Club::where('id', $input['user']['club_id'])->increment('active_student_count');

                foreach ($stuVenueId as $value) {
                    ClubVenue::where('id', $value)->increment('active_student_count');
                }

                foreach ($stuClassId as $value) {
                    ClubClass::where('id', $value)->increment('active_student_count');
                }
            });
            return returnMessage('200', '');
        }
        // 冻结
        DB::transaction(function () use ($student, $input, $stuVenueId, $stuClassId) {
            // 添加冻结记录
            $studentFreeze = new ClubStudentFreeze();
            $studentFreeze->student_id = $input['studentId'];
            $studentFreeze->student_name = $student->name;
            $studentFreeze->freeze_start_date = Carbon::now()->format('Y-m-d');
            $studentFreeze->operation_user_id = $input['user']['id'];
            $studentFreeze->operation_user_name = ClubUser::where('id', $input['user']['id'])->value('username');
            $studentFreeze->freeze_remark = $input['remark'];
            $studentFreeze->save();

            // 更新学员冻结字段
            $student->is_freeze = $input['type'];
            $student->freeze_id = $studentFreeze->id;
            $student->save();

            // 更新学员数
            Club::where('id', $input['user']['club_id'])->decrement('active_student_count');

            foreach ($stuVenueId as $value) {
                ClubVenue::where('id', $value)->decrement('active_student_count');
            }

            foreach ($stuClassId as $value) {
                ClubClass::where('id', $value)->decrement('active_student_count');
            }
        });

        return returnMessage('200', '');
    }

    /**
     * 修改销售员
     * @param Request $request
     * @return array
     */
    public function modifySeller(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'sellerId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }
        DB::transaction(function () use ($input, $student) {
            $student->sales_id = $input['sellerId'];
            $student->sales_name = ClubSales::where('id', $input['sellerId'])->value('sales_name');
            $student->save();
            // 更新上任销售结束时间
            $this->endAddStudentSalesHistory($input['studentId']);
            // 添加现任销售历史记录
            $this->startAddStudentSalesHistory($input, $input['studentId']);
        });
        return returnMessage('200', '');
    }

    /**
     * 正式学员-解除或者绑定App（作废）
     * @param Request $request
     * @return array
     */
    public function studentBindApp1(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'appAccount' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $appAccount = isset($input['appAccount']) ? $input['appAccount'] : '';
        // 绑定
        if(!empty($appAccount)) {
            // 检测app账号是否存在
            $appUser = UcUserSecret::where('mobile', $appAccount)->exists();
            if ($appUser === false) {
                return returnMessage('1636', config('error.Student.1636'));
            }

            $student->bind_app = 1;
            $student->app_account = $appAccount;
            $student->save();
            return returnMessage('200', '');
        }
        // 解绑
        $student->bind_app = 0;
        $student->app_account = '';
        $student->save();
        return returnMessage('200', '');
    }

    /**
     * 获取学员二维码
     * @param Request $request
     * @return array
     */
    public function getStudentQrCode(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $student = ClubStudent::find($input['studentId']);

        // 学员不存在
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        // 二维码已存在就直接返回
        if ($student->qrcode_url) {
            $list['imgUrl'] = env('IMG_DOMAIN') . $student->qrcode_url;
            return returnMessage('200', '', $list);
        }

        $studentName = $student->name;
        $clubId = $student->club_id;
        $salesId = $student->sales_id;
        $studentId = $student->id;

        $imgKey = Common::compoundOnePoster($studentName, $clubId, $salesId, $studentId, $student->guarder_mobile);

        $student->qrcode_url = $imgKey;
        $student->save();

        $imgUrl = env('IMG_DOMAIN') . $imgKey;

        $list['imgUrl'] = $imgUrl;

        return returnMessage('200', '', $list);
    }

    /**
     * 公海库列表
     * @param Request $request
     * @return array
     */
    public function studentLibrary(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'search' => 'nullable|string',
            'venueId' => 'nullable|numeric',
            'failureReason' => 'nullable|numeric',
            'failureOrigin' => 'nullable|numeric',
            'sellerId' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $search = isset($input['search']) ? $input['search'] : '';
        $venueId = isset($input['venueId']) ? $input['venueId'] : 0;
        $failureReason = isset($input['failureReason']) ? $input['failureReason'] : 0;
        $failureOrigin = isset($input['failureOrigin']) ? $input['failureOrigin'] : 0;
        $sellerId = isset($input['sellerId']) ? $input['sellerId'] : 0;

        $studentList = ClubStudent::where('status', 3)
            // 姓名手机号搜索
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    return $query->where('name', 'like', '%'.$search.'%')->orWhere('guarder_mobile', $search);
                }
            })
            // 场馆搜索
            ->where(function ($query) use ($venueId) {
                if (!empty($venueId) && empty($classId)) {
                    return $query->where('venue_id', $venueId);
                }
            })
            // 失效来源搜索
            ->where(function ($query) use ($failureOrigin) {
                if (!empty($failureOrigin)) {
                    return $query->where('where_to_public_sea', $failureOrigin);
                }
            })
            // 销售员搜索
            ->where(function ($query) use ($sellerId) {
                if (!empty($sellerId)) {
                    return $query->where('sales_id', $sellerId);
                }
            })
            ->where('club_id', $input['user']['club_id'])
            ->orderBy('id', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $studentList->total();
        $list['result'] = $studentList->transform(function ($items) {
            $arr['studentId'] = $items->id;
            $arr['name'] = $items->name;
            $arr['age'] = Common::getAgeByBirthday($items->birthday);
            $arr['historyVenue'] = ClubVenue::where('id', $items->venue_id)->value('name');
            $arr['joinTime'] = $this->intoPublicSeaDays($items->id);
            $arr['historySeller'] = $this->getHistorySales($items->id);
            if ($items->where_to_public_sea == 1) {
                $arr['origin'] = '正式学员';
            }
            if ($items->where_to_public_sea == 2) {
                $arr['origin'] = '非正式学员';
            }
            $arr['phone'] = $items->guarder_mobile;
            $arr['isApplied'] = $items->is_applied;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }
    // 获取历史销售员
    public function getHistorySales($studentId)
    {
        $historyId = ClubStudentSalesHistory::where('student_id', $studentId)->max('id');
        $salesName = ClubStudentSalesHistory::where('id', $historyId)->value('sales_name');
        return $salesName;
    }
    // 进入公海库时间
    public function intoPublicSeaDays($studentId)
    {
        $maxId = ClubStudentSnapshot::where('student_id', $studentId)->max('id');
        $date = ClubStudentSnapshot::where('id', $maxId)->value('created_at');
        return floor((time()-strtotime($date))/86400) + 1;
    }

    /**
     * 申请加入名下
     * @param Request $request
     * @return array
     */
    public function applyJoinUnder(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 非销售员工不可申请
        $role = Role::find($input['user']['role_id']);
        if ($role->type != 2) {
            return returnMessage('1645', config('error.Student.1645'));
        }

        $bool = ClubSalesExamine::where('student_id', $input['studentId'])
            ->where('sales_id', $input['user']['id'])
            ->where('club_id', $clubId)
            ->where('is_over', 0)
            ->exists();
        if ($bool === true) {
            return returnMessage('1617', config('error.Student.1617'));
        }

        DB::transaction(function () use ($input, $clubId) {
            $examine = new ClubSalesExamine();
            $examine->club_id = $clubId;
            $examine->sales_id = $input['user']['id'];
            $examine->student_id = $input['studentId'];
            $examine->venue_id = $input['venueId'];
            $examine->class_id = $input['classId'];
            $examine->status = 1;
            $examine->apply_time = date('Y-m-d', time());
            $examine->save();

            ClubStudent::where('id', $input['studentId'])
                ->update(['is_applied' => 1]);
        });

        return returnMessage('200', '');
    }

    /**
     * 申请列表
     * @param Request $request
     * @return array
     */
    public function studentMyApply(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'search' => 'nullable|string',
            'reviewTypeId' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $search = isset($input['search']) ? $input['search'] : '';
        $reviewTypeId = isset($input['reviewTypeId']) ? $input['reviewTypeId'] : 0;

        $studentList = ClubSalesExamine::with('Student')
            // 姓名手机号搜索
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    return $query->whereIn('student_id', ClubStudent::where('name', 'like', '%'.$search.'%')
                        ->distinct()
                        ->pluck('id')
                        ->toArray())->orWhere('student_id', ClubStudent::where('guarder_mobile', $search)
                        ->value('id'));
                }
            })
            // 审核状态搜索
            ->where(function ($query) use ($reviewTypeId) {
                if (!empty($reviewTypeId)) {
                    return $query->where('status', $reviewTypeId);
                }
            })
            ->where('club_id', $input['user']['club_id'])
            ->where('sales_id', $input['user']['id'])
            ->orderBy('apply_time', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $studentList->total();
        $list['result'] = $studentList->transform(function ($items) use ($input) {
            $arr['id'] = $items->id;
            $arr['studentId'] = $items->student_id;
            $arr['name'] = ClubStudent::where('id', $items->student_id)->value('name');
            $arr['age'] = !empty($items->student->birthday) ? Carbon::parse($items->student->birthday)->diffInYears() : 0;
            $arr['historyVenue'] = !empty($items->student->venue_id) ? ClubVenue::where('id', $items->student->venue_id)->value('name') : "";
            $arr['joinTime'] = $this->intoPublicSeaDays($items->student_id);
            $arr['historySeller'] = $this->getHistorySales($items->student_id);
            if (!isset($items->student->where_to_public_sea) || $items->student->where_to_public_sea == 0) {
                $arr['origin'] = '';
            }
            if ($items->student->where_to_public_sea == 1) {
                $arr['origin'] = '正式学员';
            }
            if ($items->student->where_to_public_sea == 2) {
                $arr['origin'] = '非正式学员';
            }
            $arr['phone'] = $items->student->guarder_mobile;
            $arr['applyUser'] = !empty($items->sales_id) ? ClubUser::where('id', $items->sales_id)->value('username') : '';
            $arr['applyTime'] = $items->apply_time;
            $arr['reviewUser'] = ClubUser::where('id', $items->examine_id)->value('username');
            $arr['reviewTime'] = $items->examine_time;
            if ($items->status == 1) {
                $arr['reviewStatus'] = '审核中';
            }
            if ($items->status == 2) {
                $arr['reviewStatus'] = '已通过';
            }
            if ($items->status == 3) {
                $arr['reviewStatus'] = '已拒绝';
            }
            $arr['status'] = $items->status;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 审核通过或拒绝
     * @param Request $request
     * @return array
     */
    public function throughOrRefuse(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 申请信息
        $apply = ClubSalesExamine::where('id', $input['id'])->first();
        if (empty($apply)) {
            return returnMessage('1640', config('error.Student.1640'));
        }

        // 销售信息
        $sales = ClubSales::where('user_id', $apply->sales_id)
            ->where('club_id', $input['user']['club_id'])
            ->first();
        if (empty($sales)) {
            return returnMessage('1641', config('error.Student.1641'));
        }

        $stuClassId = ClubClassStudent::where('club_id', $input['user']['club_id'])
            ->where('student_id', $apply->student_id)
            ->pluck('class_id');

        $stuVenueId = ClubClassStudent::where('club_id', $input['user']['club_id'])
            ->where('student_id', $apply->student_id)
            ->pluck('venue_id');

        DB::transaction(function () use ($input, $apply, $sales, $stuVenueId, $stuClassId) {
            // 通过申请
            if ($input['type'] == 1) {
                $apply->status = 2;
                $apply->examine_id = $input['user']['id'];
                $apply->examine_time = date('Y-m-d', time());
                $apply->is_over = 1;
                $apply->save();

                // 更新为非正式学员
                $studentData = [
                    'sales_id' => $sales->id,
                    'status' => 2,
                    'is_applied' => 0,
                    'venue_id' => $apply->venue_id,
                    'main_class_id' => $apply->class_id
                ];
                ClubStudent::where('club_id', $input['user']['club_id'])
                    ->where('id', $apply->student_id)
                    ->update($studentData);

                // 添加学员班级
                $stuClass = new ClubClassStudent();
                $stuClass->club_id = $input['user']['club_id'];
                $stuClass->venue_id = $apply->venue_id;
                $stuClass->sales_id = $apply->sales_id;
                $stuClass->class_id = $apply->class_id;
                $stuClass->student_id = $apply->student_id;
                $stuClass->student_name = ClubStudent::where('id', $apply->student_id)->value('name');
                $stuClass->student_status = 2;
                $stuClass->is_main_class = 1;
                $stuClass->enter_class_time = Carbon::now()->format('Y-m-d H:i:s');
                $stuClass->saveOrFail();

                // 添加历史销售
                $salesHistory = new ClubStudentSalesHistory();
                $salesHistory->student_id = $apply->student_id;
                $salesHistory->sales_id = $sales->id;
                $salesHistory->sales_name = ClubSales::where('id', $sales->id)->value('sales_name');
                $salesHistory->operation_userid = $input['user']['id'];
                $salesHistory->operation_username = ClubUser::where('id', $input['user']['id'])->value('username');
                $salesHistory->start_date = date('Y-m-d H:i:s', time());
                $salesHistory->save();

                // 更新活跃学员数
                Club::where('id', $input['user']['club_id'])->increment('active_student_count');

                foreach ($stuVenueId as $value) {
                    ClubVenue::where('id', $value)->increment('active_student_count');
                }

                foreach ($stuClassId as $value) {
                    ClubClass::where('id', $value)->increment('active_student_count');
                }
            }

            // 拒绝申请
            if ($input['type'] == 2) {
                $apply->status = 3;
                $apply->examine_id = $input['user']['id'];
                $apply->examine_time = date('Y-m-d', time());
                $apply->is_over = 1;
                $apply->save();

                ClubStudent::where('club_id', $input['user']['club_id'])
                    ->where('id', $apply->student_id)
                    ->update(['is_applied' => 0]);
            }
        });

        return returnMessage('200', '');
    }

    /**
     * 审核列表
     * @param Request $request
     * @return array
     */
    public function studentReview(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'search' => 'nullable|string',
            'reviewType' => 'nullable|numeric',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $search = isset($input['search']) ? $input['search'] : '';
        $reviewType = isset($input['reviewType']) ? $input['reviewType'] : 0;

        $studentList = ClubSalesExamine::with('Student')
            // 姓名手机号搜索
            ->where(function ($query) use ($search) {
                if (!empty($search)) {
                    return $query->whereIn('student_id', ClubStudent::where('name', 'like', '%'.$search.'%')
                        ->distinct()
                        ->pluck('id')
                        ->toArray())->orWhere('student_id', ClubStudent::where('guarder_mobile', $search)
                        ->value('id'));
                }
            })
            // 审核状态搜索
            ->where(function ($query) use ($reviewType) {
                if (!empty($reviewType)) {
                    return $query->where('status', $reviewType);
                }
            })
            ->where('club_id', $input['user']['club_id'])
            ->orderBy('status', 'desc')
            ->orderBy('examine_time', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $studentList->total();
        $list['result'] = $studentList->transform(function ($items) use ($input) {
            $arr['id'] = $items->id;
            $arr['studentId'] = $items->student_id;
            $arr['name'] = ClubStudent::where('id', $items->student_id)->value('name');
            $arr['age'] = !empty($items->student->birthday) ? Carbon::parse($items->student->birthday)->diffInYears() : 0;
            $arr['historyVenue'] = !empty($items->student->venue_id) ? ClubVenue::where('id', $items->student->venue_id)->value('name') : "";
            $arr['joinTime'] = $this->intoPublicSeaDays($items->student_id);
            $arr['historySeller'] = $this->getHistorySales($items->student_id);
            if (!isset($items->student->where_to_public_sea) || $items->student->where_to_public_sea == 0) {
                $arr['origin'] = '';
            }
            if ($items->student->where_to_public_sea == 1) {
                $arr['origin'] = '正式学员';
            }
            if ($items->student->where_to_public_sea == 2) {
                $arr['origin'] = '非正式学员';
            }
            $arr['phone'] = $items->student->guarder_mobile;
            $arr['applyUser'] = !empty($items->sales_id) ? ClubUser::where('id', $items->sales_id)->value('username') : '';
            $arr['applyTime'] = $items->apply_time;
            if ($items->status == 1) {
                $arr['reviewStatus'] = '审核中';
            }
            if ($items->status == 2) {
                $arr['reviewStatus'] = '已通过';
            }
            if ($items->status == 3) {
                $arr['reviewStatus'] = '已拒绝';
            }
            $arr['status'] = $items->status;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 学员班级列表
     * @param Request $request
     * @return array
     */
    public function classList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
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

        // 班级列表
        $classList = ClubClassStudent::with('class')
            ->where('student_id', $input['studentId'])
            ->where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $classList->total();
        $list['result'] = $classList->transform(function($items) use ($input) {
            $arr['classId'] = $items->class_id;
            $arr['className'] = isset($items->class->name) ? $items->class->name : '';
            if ($items->is_main_class == 1) {
                $arr['isMainClass'] = true;
            }
            if ($items->is_main_class == 0) {
                $arr['isMainClass'] = false;
            }
            $arr['joinClassTime'] = $items->enter_class_time;
            if ($items->is_main_class == 1 && $items->is_history_main_class == 0) {
                $arr['status'] = '当前主班级';
            }
            if ($items->is_main_class == 0 && $items->is_history_main_class == 0) {
                $arr['status'] = '';
            }
            if ($items->is_main_class == 0 && $items->is_history_main_class == 1) {
                $arr['status'] = '历史主班级';
            }
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 学员添加班级
     * @param Request $request
     * @return array
     */
    public function addClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric',
            'isMainClass' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 不可重复添加
        $classStudent = ClubClassStudent::where('student_id', $input['studentId'])
            ->where('venue_id', $input['venueId'])
            ->where('class_id', $input['classId'])
            ->where('is_delete', 0)
            ->first();
        if (empty($classStudent) === false) {
            return returnMessage('1632', config('error.Student.1632'));
        }

        $studentClass = ClubStudent::find($input['studentId']);
        $mainClassId = ClubClassStudent::where('is_main_class', 1)
            ->where('club_id', $input['user']['club_id'])
            ->where('student_id', $input['studentId'])
            ->value('id');
        if (!empty($mainClassId)) {
            $historyMainClass = ClubClassStudent::find($mainClassId);
            DB::transaction(function () use ($input, $studentClass, $historyMainClass) {
                $ClubClassStudent = new ClubClassStudent();
                $ClubClassStudent->club_id = $input['user']['club_id'];
                $ClubClassStudent->venue_id = $input['venueId'];
                $ClubClassStudent->class_id = $input['classId'];
                $ClubClassStudent->student_id = $input['studentId'];
                $ClubClassStudent->student_name = $studentClass->name;
                $ClubClassStudent->is_main_class = $input['isMainClass'];
                $ClubClassStudent->enter_class_time = date('Y-m-d', time());
                $ClubClassStudent->save();
                if ($input['isMainClass'] == 1) {
                    $historyMainClass->is_main_class = 0;
                    $historyMainClass->is_history_main_class = 1;
                    $historyMainClass->save();
                }

                // 更新学员数
                if ($studentClass->status == 1 && $studentClass->is_freeze == 0) {
                    ClubVenue::where('id', $input['venueId'])->increment('student_count');

                    ClubVenue::where('id', $input['venueId'])->increment('active_student_count');

                    ClubClass::where('id', $input['classId'])->increment('student_count');

                    ClubClass::where('id', $input['classId'])->increment('active_student_count');
                } else {
                    ClubVenue::where('id', $input['venueId'])->increment('student_count');

                    ClubClass::where('id', $input['classId'])->increment('student_count');
                }
            });
            return returnMessage('200', '');
        }
        DB::transaction(function () use ($input, $studentClass) {
            $ClubClassStudent = new ClubClassStudent();
            $ClubClassStudent->club_id = $input['user']['club_id'];
            $ClubClassStudent->venue_id = $input['venueId'];
            $ClubClassStudent->class_id = $input['classId'];
            $ClubClassStudent->student_id = $input['studentId'];
            $ClubClassStudent->student_name = $studentClass->name;
            $ClubClassStudent->is_main_class = $input['isMainClass'];
            $ClubClassStudent->enter_class_time = date('Y-m-d', time());
            $ClubClassStudent->save();
        });
        return returnMessage('200', '');
    }

    /**
     * 修改学员进入班级时间
     * @param Request $request
     * @return array
     */
    public function modifyJoinClassTime(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'classId' => 'required|numeric',
            'joinClassTime' => 'required|date'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $classStudent = ClubClassStudent::where('student_id', $input['studentId'])
            ->where('class_id', $input['classId'])
            ->first();
        try {
            $classStudent->enter_class_time = $input['joinClassTime'];
            $classStudent->save();
        } catch (\Exception $e) {
            return returnMessage('1611', config('error.Student.1611'));
        }
        return returnMessage('200', '');
    }

    /**
     * 修改学员主班级
     * @param Request $request
     * @return array
     */
    public function modifyClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'venueId' => 'required|numeric',
            'classId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $classStudent = ClubClassStudent::where('student_id', $input['studentId'])
            ->where('club_id', $input['user']['club_id'])
            ->where('venue_id', $input['venueId'])
            ->where('class_id', $input['classId'])
            ->first();
        // 学员不存在于此班级中
        if (empty($classStudent)) {
            return returnMessage('1635', config('error.Student.1635'));
        }
        $mainClassId = ClubClassStudent::where('is_main_class', 1)
            ->where('student_id', $input['studentId'])
            ->value('id');
        if (!empty($mainClassId)) {
            $historyMainClass = ClubClassStudent::find($mainClassId);
            DB::transaction(function () use ($input, $classStudent, $historyMainClass) {
                $historyMainClass->is_main_class = 0;
                $historyMainClass->is_history_main_class = 1;
                $historyMainClass->save();
                // 设置主班级
                $classStudent->is_main_class = 1;
                $classStudent->is_history_main_class = 0;
                $classStudent->save();
            });
        }
        DB::transaction(function () use ($input, $classStudent) {
            // 设置主班级
            $classStudent->is_main_class = 1;
            $classStudent->is_history_main_class = 0;
            $classStudent->save();
        });
        return returnMessage('200', '');
    }

    /**
     * 移除学员班级
     * @param Request $request
     * @return array
     */
    public function deleteClass(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'classId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $student = ClubStudent::find($input['studentId']);

        $class = ClubClass::find($input['classId']);

        $classStudent = ClubClassStudent::where('student_id', $input['studentId'])
            ->where('club_id', $input['user']['club_id'])
            ->where('class_id', $input['classId'])
            ->first();

        // 当前班级为主班级，不能直接移除
        if ($classStudent->is_main_class == 1) {
            return returnMessage('1639', config('error.Student.1639'));
        }

        // 学员不存在于此班级中
        if (empty($classStudent)) {
            return returnMessage('1635', config('error.Student.1635'));
        }

        DB::transaction(function () use ($classStudent, $student, $class) {
            $classStudent->is_delete = 1;
            $classStudent->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
            $classStudent->save();

            // 更新学员数
            if ($student->status == 1 && $student->is_freeze == 0) {
                ClubVenue::where('id', $class->venue_id)->decrement('student_count');

                ClubVenue::where('id', $class->venue_id)->decrement('active_student_count');

                ClubClass::where('id', $class->id)->decrement('student_count');

                ClubClass::where('id', $class->id)->decrement('active_student_count');
            } else {
                ClubVenue::where('id', $class->venue_id)->decrement('student_count');

                ClubClass::where('id', $class->id)->decrement('student_count');
            }
        });

        return returnMessage('200', '');
    }

    /**
     * 学员冻结记录
     * @param Request $request
     * @return array
     */
    public function freezeRecordList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
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

        $freeze = ClubStudentFreeze::with('operationUser')
            ->where('student_id', $input['studentId'])
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $freeze->total();
        $list['result'] = $freeze->transform(function ($items) {
            $arr['freezeRecordId'] = $items->id;
            $arr['freezeTime'] = $items->freeze_start_date;
            $arr['unfreezeTime'] = $items->freeze_end_date;
            $arr['operator'] = $items->operationUser->username;
            $arr['remark'] = $items->freeze_remark;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 修改冻结备注
     * @param Request $request
     * @return array
     */
    public function freezeModifyRemark(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'freezeRecordId' => 'required|numeric',
            'remark' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $freeze = ClubStudentFreeze::where('student_id', $input['studentId'])
            ->where('id', $input['freezeRecordId'])
            ->first();
        // 冻结记录不存在
        if (empty($freeze) === true) {
            return returnMessage('1634', config('error.Student.1634'));
        }
        try {
            $freeze->freeze_remark = $input['remark'];
            $freeze->save();
        } catch (\Exception $e) {
            return returnMessage('1611', config('error.Student.1611'));
        }
        return returnMessage('200', '');
    }

    /**
     * 测验管理
     * @param Request $request
     * @return array
     */
    public function studentExamsManage(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $clubStudent = ClubStudent::find($input['studentId']);
        if (empty($clubStudent)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $studentExams = ClubExamsStudent::with('exams')
            ->where('student_id', $input['studentId'])
            ->get();

        $result = $studentExams->transform(function ($items) {
            $arr['examsName'] = $items->exams->exam_name;
            $arr['generalLevel'] = $items->exam_general_level;
            $arr['generalLevelScore'] = $items->exam_general_score;
            $arr['examsItems'] = $this->examsItems($items->id);
            $arr['examsComment'] = $items->remark;
            $arr['examsDate'] = $items->created_at->format('Y-m-d');
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * 测验项目
     * @param $examStudentId
     * @return mixed
     */
    public function examsItems($examStudentId)
    {
        $examsItems = ClubExamsItemsStudent::where('exam_student_id', $examStudentId)
            ->get();
        $result = $examsItems->transform(function ($items) {
            $arr['itemName'] = ClubExamsItems::where('id', $items->exam_items_id)
                ->value('item_name');
            $arr['itemLevel'] = ClubExamsItemsLevel::where('id', $items->exam_items_level_id)
                ->value('level_name');
            $arr['itemLevelScore'] = ClubExamsItemsLevel::where('id', $items->exam_items_level_id)
                ->value('level_score');
            return $arr;
        });
        return $result;
    }

    /**
     * 新增/修改证件号码
     * @param Request $request
     * @return array
     */
    public function modifyPostCard(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'studentId' => 'required|numeric',
            'postCardType' => [
                'required',
                Rule::in([1,2])
            ],
            'postCard' => 'required',
            'type' => [
                'required',
                Rule::in([0,1])
            ]
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        if ($input['postCardType'] == 2) {//验证方式，1:身份证 2:护照  (护照不支持验证)
            return returnMessage('1618', config('error.Student.1618'));
        }

        $clubStudent = ClubStudent::find($input['studentId']);

        if (empty($clubStudent)) {//学员不存在
            return returnMessage('1610', config('error.Student.1610'));
        }

        if ($input['type'] == 1 && $clubStudent->is_can_modify == 0) {//学员信息不可修改（修改时）
            return returnMessage('1620', config('error.Student.1620'));
        }

        //验证学员证件(身份证校验暂时先关闭)
        $checkRes = Common::idCardCheck($input['postCard'],$clubStudent->name);

        $postCard = $input['postCard'];

        if ($checkRes['code'] != '200') {
            return returnMessage($checkRes['code'], $checkRes['msg']);
        }

        if ($input['type'] == 0) {//新增
            if (! empty($clubStudent->core_id)) {
                $studentCore = ClubStudentCore::find($clubStudent->core_id);

                if (empty($studentCore)) {
                    return returnMessage('1623', config('error.Student.1623'));
                }

                if (!empty($studentCore->card_no)) {//证件不要重复添加
                    return returnMessage('1619', config('error.Student.1619'));
                }

                try {
                    $studentCore->getConnection()->transaction(function () use ($studentCore,$postCard,$clubStudent) {
                        //修改身份证号
                        $this->modifyStudenIdCard($studentCore,$postCard);
                        //修改用户信息是否可更改状态
                        $this->changeStudentCanMofifyStatus($clubStudent);
                    });
                } catch (\Exception $e) {
                    return returnMessage('1622', config('error.Student.1622'));
                }

                return returnMessage('200', '添加成功');
            }

            //core_id为空时
            //查看该身份证是否存在
            $studentCore2 = ClubStudentCore::where('card_type',1)
                ->where('card_no',$input['postCard'])
                ->get();

            if ($studentCore2->count() > 1) {//当前身份证有多人
                try {
                    $studentCore2[0]->getConnection()->transaction(function () use ($postCard,$clubStudent) {
                        $stuCoreId = $this->addNewStudentCore($clubStudent->name,$clubStudent->english_name,1,$postCard);
                        $this->modifyClubStudentCoreId($clubStudent,$stuCoreId);
                        $this->changeStudentCanMofifyStatus($clubStudent);
                    });
                } catch (\Exception $e) {
                    return returnMessage('1622', config('error.Student.1622'));
                }

            }

            if ($studentCore2->count() == 1) {
                $coreId = $studentCore2[0]->id;
                try {
                    $studentCore2[0]->getConnection()->transaction(function () use ($clubStudent,$coreId,$studentCore2) {
                        $this->modifyClubStudentCoreId($clubStudent,$coreId);
                        $this->changeStudentCanMofifyStatus($clubStudent);
                        if ($studentCore2[0]->chinese_name != $clubStudent->name || $studentCore2[0]->english_name != $clubStudent->english_name) {
                            $this->modifyStudentCoreName($studentCore2[0],$clubStudent->name,$clubStudent->english_name);
                        }
                    });
                } catch (\Exception $e) {
                    return returnMessage('1622', config('error.Student.1622'));
                }

            }

            if ($studentCore2->count() == 0) {
                try {
                    DB::transaction(function () use ($clubStudent,$postCard) {
                        $stuCoreId = $this->addNewStudentCore($clubStudent->name,$clubStudent->english_name,1,$postCard);
                        $this->modifyClubStudentCoreId($clubStudent,$stuCoreId);
                        $this->changeStudentCanMofifyStatus($clubStudent);
                    });
                } catch (\Exception $e) {
                    return returnMessage('1622', config('error.Student.1622'));
                }

            }

            return returnMessage('200','添加成功');

        }

        if ($input['type'] == 1) {//修改
            //todo 修改逻辑暂且未知，老学员数据倒入时无法关联
        }
    }

    /**
     * 历史销售员列表
     * @param Request $request
     * @return array
     */
    public function historySellerList(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'studentId' => 'required|numeric',
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $clubStudent = ClubStudent::find($input['studentId']);

        if (empty($clubStudent)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $list = ClubStudentSalesHistory::where('student_id',$input['studentId'])
            ->paginate($input['pagePerNum'],['*'],'currentPage',$input['currentPage']);


        $historySalesList = [];

        collect($list->items())->each(function ($item) use (&$historySalesList) {
            array_push($historySalesList, [
                'id' => $item->sales_id,
                'sellerName' => $item->sales_name,
                'joinTime' => $item->start_date,
                'removeTime' => $item->end_date,
                'operateUser' => $item->operation_username,
            ]);
        });

        $returnData = [
            'totalNum' => $list->total(),
            'totalPage' => ceil($list->total() / $input['pagePerNum']),
            'result' => $historySalesList
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 预约列表
     * @param Request $request
     * @return array
     */
    public function reserveRecordList(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'studentId' => 'required|numeric',
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $clubStudent = ClubStudent::find($input['studentId']);

        if (empty($clubStudent)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $list = ClubStudentSubscribe::with(['class','sale','course'])
            ->where('student_id',$input['studentId'])
            ->paginate($input['pagePerNum'],['*'],'currentPage',$input['currentPage']);

        $subscribeList = [];

        collect($list->items())->each(function ($item) use (&$subscribeList) {
            array_push($subscribeList, [
                'reserveRecordId' => $item->id,
                'className' => $item->class->name . ' ' .$item->getClassStudentMinAndMaxAge($item->class_id)['age_stage'] . '岁',
                'courseTime' => $item->course->day,
                'seller' => $item->sale->sales_name,
                'attendanceStatus' => $item->getSubscribeStatusName($item->subscribe_status),
                'reserveOrigin' => $item->getSubscribeSourceName($item->type),
                'createTime' => $item->created_at->format('Y-m-d H:i:s'),
            ]);
        });

        $returnData = [
            'totalNum' => $list->total(),
            'totalPage' => ceil($list->total() / $input['pagePerNum']),
            'result' => $subscribeList
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 取消预约
     * @param Request $request
     * @return array
     */
    public function cancelReserve(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'studentId' => 'required|numeric',
            'reserveRecordId' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $stuSubscribe = ClubStudentSubscribe::find($input['reserveRecordId']);

        if (empty($stuSubscribe)) {//预约记录不存在
            return returnMessage('1624', config('error.Student.1624'));
        }

        if ($stuSubscribe->student_id != $input['studentId']) {//非法操作
            return returnMessage('1003', config('error.common.1003'));
        }

        if ($stuSubscribe->subscribe_status == 3) {//已取消
            return returnMessage('1625', config('error.Student.1625'));
        } elseif ($stuSubscribe->subscribe_status == 2) {//未出勤
            return returnMessage('1626', config('error.Student.1626'));
        } elseif ($stuSubscribe->subscribe_status == 1) {//已出勤
            return returnMessage('1627', config('error.Student.1627'));
        }

        try {
            DB::transaction(function () use ($stuSubscribe, $input) {
                // 更新预约状态
                $this->cancleStudentReserve($stuSubscribe);

                // 更新学员状态
                ClubStudent::where('id', $input['studentId'])
                    ->update(['ex_status' => 0]);
            });
        } catch (\Exception $e) {
            return returnMessage('1628', config('error.Student.1628'));
        }

        return returnMessage('200', '取消预约成功');
    }

    /**
     * 签到列表
     * @param Request $request
     * @return array
     */
    public function signNotesList(Request $request)
    {
        $input = $request->all();

        //attendanceType 0:全部 1:出勤 2:病假 3:事假 4:缺勤 5:PASS 6:AutoPass 7:外勤预留 8:冻结
        $validator = Validator::make($input, [
            'studentId' => 'required|numeric',
            'search' => 'nullable',
            'attendanceType' => [
                'nullable',
                Rule::in([0,1,2,3,4,5,6,7,8])
            ],
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $courseKeyWords = isset($input['search']) ? $input['search'] : '';
        $attendanceType = isset($input['attendanceType']) ? $input['attendanceType'] : '';

        $list = ClubCourseSign::with('class')
            ->where('student_id',$input['studentId'])
            ->when($courseKeyWords,function ($query) use ($courseKeyWords) {
                return $query->where('course_id','LIKE',$courseKeyWords.'%');
            })
            ->when($attendanceType,function ($query) use ($attendanceType) {
                return $query->where('sign_status',$attendanceType);
            })
            ->orderBy('sign_date','desc')
            ->paginate($input['pagePerNum'],['*'],'currentPage',$input['currentPage']);

        $signList = [];

        collect($list->items())->each(function ($item) use (&$signList) {
            array_push($signList, [
                'courseId' => $item->course_id,
                'className' => isset($item->class->name) ? $item->class->name : '' . ' ' .$item->getClassStudentMinAndMaxAge($item->class_id)['age_stage'] . '岁',
                'status' => $item->getSignStatusName($item->sign_status),
                'isPay' => $item->getIsPay($item->id),
                'payAmount' => $item->getPayAmount($item->id),
                'signTime' => $item->sign_date,
                'remark' => $item->remark,
            ]);
        });

        $returnData = [
            'totalNum' => $list->total(),
            'totalPage' => ceil($list->total() / $input['pagePerNum']),
            'result' => $signList
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 退款
     * @param Request $request
     * @return array
     */
    public function paymentReimburse(Request $request)
    {
        $input = $request->all();
        $user = JWTAuth::parseToken()->authenticate();
        $userId = $user->id;
        if ($this->judgeUserIsSales($userId) === false) {
            //return returnMessage('1672', config('error.Student.1672'));
        }

        $validator = Validator::make($input, [
            'studentId' => 'required|numeric',
            'paymentId' => 'required|numeric',
            'reimbursePrice' => 'required|numeric',
            'remark' => 'nullable',
            'courseTicketId' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $clubStuExists = ClubStudent::where('id',$input['studentId'])->exists();

        if ($clubStuExists === false) {//学员不存在
            return returnMessage('1610', config('error.Student.1610'));
        }

        $stuPaymentExists = ClubStudentPayment::where('student_id',$input['studentId'])
            ->where('payment_id',$input['paymentId'])
            ->exists();

        if ($stuPaymentExists === false) {//缴费方案不存在
            return returnMessage('1629', config('error.Student.1629'));
        }

        $remark = isset($input['remark']) ? $input['remark'] : '';
        $studentId = $input['studentId'];
        $paymentId = $input['paymentId'];
        $reimbursePrice = $input['reimbursePrice'];
        $courseTicketId = $input['courseTicketId'];

        try {
            DB::transaction(function () use ($userId,$studentId,$paymentId,$reimbursePrice,$courseTicketId,$remark) {
                $this->addStuRefundRecord($userId,$studentId,$paymentId,$reimbursePrice,$courseTicketId,$remark);
                $this->handleCourseTicketsUseless($studentId,$courseTicketId);
            });
        } catch (\Exception $e) {
            return returnMessage('1670', config('error.Student.1670'));
        }

        return returnMessage('200', '操作成功');
    }

    /**
     * 修改俱乐部学员coreId
     * @param ClubStudent $clubStudent
     * @param $stuCoreId 学员coreId
     */
    public function modifyClubStudentCoreId(ClubStudent $clubStudent,$stuCoreId)
    {
        $clubStudent->core_id = $stuCoreId;
        $clubStudent->save();
    }

    /**
     * 添加一条studentCore数据
     * @param $zhName
     * @param $enName
     * @param $cardType
     * @param $idCard
     * @return mixed
     */
    public function addNewStudentCore($zhName,$enName,$cardType,$idCard)
    {
        $studentCore = new ClubStudentCore();
        $studentCore->chinese_name = $zhName;
        $studentCore->english_name = $enName;
        $studentCore->card_type = $cardType;
        $studentCore->card_no = $idCard;
        $studentCore->save();

        return $studentCore->id;
    }

    /**
     * 更改学员是否可以修改信息的状态
     * @param ClubStudent $clubStudent
     */
    public function changeStudentCanMofifyStatus(ClubStudent $clubStudent)
    {
        $clubStudent->is_can_modify = 0;
        $clubStudent->save();
    }

    /**
     * 修改学员身份证
     * @param ClubStudentCore $studentCore
     * @param $postCard
     */
    private function modifyStudenIdCard(ClubStudentCore $studentCore,$postCard)
    {
        $studentCore->card_type = 1;
        $studentCore->card_no = $postCard;
        $studentCore->save();
    }

    /**
     * 修改学员中英文名
     * @param ClubStudentCore $studentCore
     * @param $zhName
     * @param $enName
     */
    private function modifyStudentCoreName(ClubStudentCore $studentCore,$zhName,$enName) {
        $studentCore->chinese_name = $zhName;
        $studentCore->english_name = $enName;
        $studentCore->save();
    }

    /**
     * 取消预约
     * @param ClubStudentSubscribe $clubStudentSubscribe
     */
    public function cancleStudentReserve(ClubStudentSubscribe $clubStudentSubscribe)
    {
        $clubStudentSubscribe->subscribe_status = 3;
        $clubStudentSubscribe->ex_status = 0;
        $clubStudentSubscribe->save();
    }

    /**
     * 增加退款记录
     * @param $userId 用户id
     * @param $studentId 学员ID
     * @param $paymentId 缴费方案id
     * @param $reimbursePrice 退款金额
     * @param $courseTicketId 需要退款的课程券编号
     * @param $remark
     */
    public function addStuRefundRecord($userId,$studentId,$paymentId,$reimbursePrice,$courseTicketId,$remark)
    {
        $studentRefund = new ClubStudentRefund();
        $studentRefund->student_id = $studentId;
        $studentRefund->student_payment_id = $paymentId;
        $studentRefund->refund_course_ids = is_array($courseTicketId) ? $courseTicketId : array_unique(explode(',',$courseTicketId));
        $studentRefund->refund_money = $reimbursePrice;
        $studentRefund->refund_operation_sales_id = $this->getSalesIdFromUserId($userId);
        $studentRefund->refund_date = Carbon::now();
        $studentRefund->remark = $remark;
        $studentRefund->save();
    }

    /**
     * 将课程券失效
     * @param $studentId
     * @param $courseTicketId
     * @return mixed
     */
    public function handleCourseTicketsUseless($studentId,$courseTicketId)
    {
        $courseTicketIds = is_array($courseTicketId) ? $courseTicketId : array_unique(explode(',',$courseTicketId));
        return CourseTickets::where('student_id',$studentId)
            ->whereIn('id',$courseTicketIds)
            ->update(['status' => 4]);
    }

    /**
     * 获取销售用户的销售id
     * @param $userId
     * @return int
     */
    public function getSalesIdFromUserId($userId)
    {
        $sales = ClubSales::where('status',1)
            ->where('user_id',$userId)
            ->first();

        return $sales ? $sales->id : 0;
    }

    /**
     * 判断用户是否是销售
     * @param $userId
     * @return mixed
     */
    public function judgeUserIsSales($userId)
    {
        return ClubSales::where('status',1)
            ->where('user_id',$userId)
            ->exists();
    }

    /**
     * 添加公海库学员备注
     * @param Request $request
     * @return array
     * @throws \Throwable
     */
    public function addStudentFeedback(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric',
            'content' => 'string|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 学员不存在
        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $feedback = new ClubStudentFeedback();
        $feedback->club_id = $input['user']['club_id'];
        $feedback->student_id = $input['studentId'];
        $feedback->tickling_content = $input['content'];
        $feedback->operation_user_id = $input['user']['id'];
        $feedback->create_time = Carbon::now()->format('Y-m-d H:i:s');
        $feedback->saveOrFail();

        return returnMessage('200', '');
    }

    /**
     * 获取公海库学员备注
     * @param Request $request
     * @return array
     */
    public function getStudentFeedback(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'studentId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 学员不存在
        $student = ClubStudent::find($input['studentId']);
        if (empty($student)) {
            return returnMessage('1610', config('error.Student.1610'));
        }

        $feedback = ClubStudentFeedback::where('club_id', $input['user']['club_id'])
            ->where('student_id', $input['studentId'])
            ->orderBy('created_at', 'desc')
            ->limit(3)
            ->get();

        $list['result'] = $feedback->transform(function ($items) {
            $arr['content'] = $items->tickling_content;
            $arr['createTime'] = Carbon::parse($items->created_at)->format('Y-m-d');
            $arr['operateName'] = User::where('id', $items->operation_user_id)->value('username');
            return $arr;
        });

        return returnMessage('200', '', $list);
    }
}