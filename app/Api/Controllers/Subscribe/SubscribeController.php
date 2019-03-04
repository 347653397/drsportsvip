<?php

namespace App\Api\Controllers\Subscribe;

use App\Model\ClubChannel\ClubChannel;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourse\ClubCourseOperation;
use App\Model\ClubCourseSign\ClubCourseSign;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentSubscribe\ClubStudentSubscribe;

use App\Services\Common\CommonService;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Tymon\JWTAuth\Facades\JWTAuth;


class SubscribeController extends Controller
{
    private $user;
    private $sale_ids = [];

    public function __construct()
    {
        try {
            //获取该用户
            $this->user = JWTAuth::parseToken()->authenticate();
            //获取该登录账号 满足条件下的所有销售id
            $CommonService = new CommonService();
            $this->sale_ids = $CommonService->getAllSalesIdsByUserId($this->user->id);
        } catch (\Exception $exception) {

        }
    }

    //(1)获取所有来源渠道  顶级渠道
    public function allChannel()
    {
        $data = \DB::table('club_channel')
            ->where(['is_delete' => 0])
            ->get(['id', 'channel_name as channelName']);

        return returnMessage('200', '获取成功', $data);
    }

    //(2)获取所有销售员
    public function allSales()
    {
        $data = \DB::table('club_sales')
            ->whereIn('id', $this->sale_ids)
            ->where(['status' => 1, 'is_delete' => 0])
            ->get(['id', 'sales_name as salesName']);

        return returnMessage('200', '获取成功', $data);
    }

    //(3)预约列表
    public function subscribeList(Request $request)
    {
        $data = $request->all();
        $validate = Validator::make($data, [
            'searchWord' => 'nullable|string',
            'channelId' => 'nullable|numeric',
            'salesId' => 'nullable|numeric',
            'subscribeStatus' => 'nullable|numeric|in:0,1,2,3',
            'type' => 'nullable|numeric|in:1,2',
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1901', $validate->errors()->first());
        }

        $search_word = isset($data['searchWord']) ? $data['searchWord'] : ''; //编号、账号、中文名(模糊搜索)
        $channel_id = isset($data['channelId']) ? $data['channelId'] : ''; //渠道来源id
        $sales_id = isset($data['salesId']) ? $data['salesId'] : ''; //销售id
        $subscribe_status = isset($data['subscribeStatus']) ? $data['subscribeStatus'] : ''; //出勤状态
        $type = $data['type'] ?? ''; //预约来源

        $pageSize = isset($data['pageSize']) ? $data['pageSize'] : 20; //每页显示数量
        $currentPage = isset($data['currentPage']) ? $data['currentPage'] : 1; //页码

        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $model = \DB::table('club_student_subscribe as a')
            ->leftJoin('club_student as b', 'b.id', '=', 'a.student_id')
            ->leftJoin('club_class as c', 'c.id', '=', 'a.class_id')
            ->leftJoin('club_course as d', 'd.id', '=', 'a.course_id')
            ->leftJoin('club_sales as f', 'f.id', '=', 'a.sales_id')
            ->leftJoin('club_channel as e', 'e.id', '=', 'a.channel_id')
            ->select('a.id', 'b.name', 'b.age', 'b.guarder_mobile', 'a.type',
                'c.name as className', 'd.day as courseDate', 'f.sales_name', 'e.channel_name',
                'a.subscribe_status', 'a.created_at')
            ->where(function ($query) use ($search_word) {
                if (!empty($search_word)) {
                    $query->where('a.id', $search_word)->orwhere('b.name', 'like', '%' . $search_word . '%')
                        ->orwhere('b.guarder_mobile', $search_word);
                }
            })
            ->where(function ($query) use ($channel_id) {
                if (!empty($channel_id)) {
                    $query->where('a.channel_id', $channel_id);
                }
            })->where(function ($query) use ($type) {
                if (!empty($type)) {
                    $query->where('a.type', $type);
                }
            })->where(function ($query) use ($sales_id) {
                if (!empty($sales_id)) {
                    $query->where('a.sales_id', $sales_id);
                }
            })->where(function ($query) use ($subscribe_status) {
                if (strlen($subscribe_status) > 0) {
                    $query->where('a.subscribe_status', $subscribe_status);
                }
            })
            ->whereIn('a.sales_id', $this->sale_ids)
            ->where(['a.club_id' => $this->user->club_id, 'a.is_delete' => 0])
            ->paginate($pageSize);

        $ClubStudentSubscribe = new ClubStudentSubscribe();
        $result['total'] = $model->total();

        $result['data'] = $model->transform(function ($item) use ($ClubStudentSubscribe) {
            $result = [
                'id' => $item->id,
                'guarderMobile' => $item->guarder_mobile,//后台预约才有手机号
                'name' => $item->name,
                'age' => $item->age,
                'className' => $item->className,
                'courseDate' => $item->courseDate,
                'saleName' => $item->sales_name,
                'channel' => $item->channel_name,
                'subscribeStatus' => $ClubStudentSubscribe->getSubscribeStatusName($item->subscribe_status),
                'type' => $ClubStudentSubscribe->getSubscribeSourceName($item->type),
                'createTime' => date('Y-m-d H:i:s', strtotime($item->created_at))
            ];

            return $result;
        });

        return returnMessage('200', '请求成功', $result);
    }


    //(4)获取该账号 俱乐部 对应的 所有场馆
    public function allVenue()
    {
        $data = \DB::table('club_venue')
            ->where(['status' => 1, 'is_delete' => 0, 'club_id' => $this->user->club_id])
            ->get(['id', 'name']);

        return returnMessage('200', '获取成功', $data);
    }


    //(5)根据场馆获取关联所有的班级
    public function getClassByVenue(Request $request)
    {
        $venue_id = $request->post('venueId');
        if (!$venue_id) return returnMessage('1901', '参数不对');

        $data = \DB::table('club_class')
            ->where(['status' => 1, 'venue_id' => $venue_id, 'is_delete' => 0])
            ->get(['id', 'name']);

        return returnMessage('200', '获取成功', $data);
    }

    /**
     * 预约管理搜索
     * @param Request $request
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function subscribeManage(Request $request)
    {
        $data = $request->all();
        $validate = \Validator::make($data, [
            'searchWord' => 'nullable|string',
            'classId' => 'nullable|numeric',
            'channelId' => 'nullable|numeric',
            'salesId' => 'nullable|numeric',
            'pageSize' => 'required|numeric',
            'currentPage' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1901', $validate->errors()->first());
        }
        $currentPage = isset($data['currentPage']) ? (int)$data['currentPage'] : 1; //页码
        Paginator::currentPageResolver(function () use ($currentPage) {
            return $currentPage;
        });

        $arr = [
            'clubId' => $this->user->club_id,
            'salesId' => $this->sale_ids
        ];

        //var_dump($arr);

        $model = ClubStudent::select(
            ['id', 'name', 'sales_id', 'main_class_name', 'channel_id', 'left_course_count']
        )->where(function ($query) use ($data) {
            foreach ($data as $key => $val) {
                if ($val) {
                    switch ($key) {
                        case "searchWord":
                            $query->where("id", $val)->orWhere("name", "like", "%" . $val . "%");
                            break;
                        case "classId":
                            $query->where("main_class_id", $val);
                            break;
                        case "channelId":
                            $query->where('channel_id', $val);
                            break;
                        case "salesId":
                            $query->where('sales_id', $val);
                            break;
                    }
                }
            }
        })->where(['status' => 2, 'club_id' => $this->user->club_id, 'is_delete' => 0])
        ->where('left_course_count','>',0)->where('ex_status', '<>', 2)
        ->whereIn('sales_id', $this->sale_ids)
        /*->whereHas('studentSubscribe', function ($query) {
            $query->where('ex_status', 0);
        })*/
        ->orderBy('created_at', 'desc')
        ->paginate($data['pageSize'] ?? 20);
        $result['total'] = $model->total();
        $result['data'] = $model->transform(function ($item) {
            $result = [
                'id' => $item->id,
                'name' => $item->name,
                'salesName' => ClubSales::whereKey($item->sales_id)->value('sales_name'),
                'mainClassName' => $item->main_class_name,
                'channelName' => ClubChannel::whereKey($item->channel_id)->value('channel_name'),
                'leftCourseCount' => $item->left_course_count
            ];
            return $result;
        });
        return returnMessage('200', '请求成功', $result);
    }

    //(7)获取该学员所在俱乐部对应的常规班级
    public function subscribeClass(Request $request)
    {
        $student_id = $request->post('studentId');
        if (!$student_id) return returnMessage('1901', '参数不对');

        $student = ClubStudent::find($student_id);
        $club_id = $student->club_id;
        if (!$club_id) return returnMessage('1901', '该记录不存在');

        $data = \DB::table('club_class')
            ->where(['status' => 1, 'type' => 1, 'is_delete' => 0, 'club_id' => $club_id])
            ->get(['id', 'name']);

        return returnMessage('200', '获取成功', $data);
    }

    //(8)获取班级对应的课程
    public function subscribeCourse(Request $request)
    {
        $class_id = $request->post('classId');
        if (!$class_id) return returnMessage('1901', '参数不对');

        $data = ClubCourse::where(['class_id' => $class_id, 'is_delete' => 0])
            ->where('day', '>=', date('Y-m-d'))
            ->where(['status' => 1])
            ->orderBy('day', 'asc')
            ->get(['id', 'day','start_time as startTime']);


        $data = $data->toArray();

        $time = strtotime(date('Y-m-d H:i:s',strtotime('+2 hour')));

        $result = [];
        foreach ($data as $key=>$value) {

            $temp = strtotime($value['day'] . $value['startTime']);

            if ($temp >= $time){
                unset($value['startTime']);
                $result[] = $value;
            }
        }
        unset($value);

        $data = array_slice($result,0,8);

        return returnMessage('200', '获取成功', $data);
    }

    //(9)添加预约
    public function addSubscribe(Request $request)
    {
        $data = $request->all();

        $validate = \Validator::make($data, [
            'studentId' => 'required|numeric',
            'classId' => 'required|numeric',
            'courseId' => 'required|numeric'
        ]);

        if ($validate->fails()) {
            return returnMessage('1901', $validate->errors()->first());
        }

        $ClubStudent = ClubStudent::find($data['studentId']);
        if (!$ClubStudent) {
            return returnMessage('1901', '该记录不存在');
        } else if ($ClubStudent->ex_status != 0) {
            return returnMessage('1901', '该学员不可再预约');
        }

        $class = ClubClass::find($data['classId']);
        if (empty($class)) {
            return returnMessage('1406', config('error.class.1406'));
        }

        $course = ClubCourse::find($data['courseId']);
        if (empty($class)) {
            return returnMessage('1415', config('error.class.1415'));
        }

        try {
            \DB::transaction(function () use ($data, $ClubStudent, $class) {
                $sign_id = ClubCourseSign::insertGetId([
                    'club_id' => $ClubStudent->club_id,
                    'class_id' => $data['classId'],
                    'course_id' => $data['courseId'],
                    'student_id' => $ClubStudent->id,
                    'is_subscribe' => 1,
                    'class_type_id' => $class->type,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                if (!$sign_id) throw new \Exception('插入失败');

                $result = ClubStudentSubscribe::insert([
                    'student_id' => $ClubStudent->id,
                    'sales_id' => $ClubStudent->sales_id,
                    'course_id' => $data['courseId'],
                    'class_id' => $data['classId'],
                    'club_id' => $ClubStudent->club_id,
                    'channel_id' => $ClubStudent->channel_id,
                    'sales_id' => $ClubStudent->sales_id,
                    'type' => 2,
                    'sign_id' => $sign_id,
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                if (!$result) throw new \Exception('插入失败');

                $res = \DB::table('club_student')
                    ->where('id', $data['studentId'])
                    ->update(['ex_status' => 1]);
                if (!$res) throw new \Exception('更新失败');
            });

            return returnMessage('200', '操作成功');

        } catch (\Exception $exception) {
            return returnMessage('1901', $exception->getMessage());
        }
    }

}
