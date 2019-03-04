<?php

namespace App\Api\Controllers\Students;

use App\Facades\Classes\Classes;
use App\Facades\Permission\Permission;
use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubChannel\Channel;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassTeacher\Teacher;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourseTickets\CourseTickets;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubStudentBindApp\ClubStudentBindApp;
use App\Model\ClubVenue\ClubVenue;
use App\Model\UcUserSecret\UcUserSecret;
use App\Model\Venue\Venue;
use App\Services\Common\CommonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class CommonController extends Controller
{
    CONST USER_TOKEN_SALT = 'user_identity!@#';

    CONST APP_TOKEN_SALT = 'single_login!@#';

    /**
     * @var CommonService
     */
    private $commonService;

    /**
     * CommonController constructor.
     * @param CommonService $commonService
     */
    public function __construct(CommonService $commonService)
    {
        $this->commonService = $commonService;
    }

    /**
     * select框-缴费方案
     * @param Request $request
     * @return array
     */
    public function paymentTypeSelect(Request $request)
    {
        $input = $request->all();
        $payments = ClubPayment::where('club_id', $input['user']['club_id'])
            ->where('status', 1)
            ->where('is_delete', 0)
            ->get();
        $result = $payments->transform(function ($items) {
            $arr['paymentTypeId'] = $items->id;
            $arr['paymentTypeName'] = $items->name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * select框-渠道来源
     * @return array
     * @date 2018/10/10
     * @author edit jesse
     */
    public function channelSelectList(Request $request)
    {
        $input = $request->all();
        $channels = Channel::where([['is_delete', 0], ['parent_id', 0]])
            ->whereIn('club_id', [0, $input['user']['club_id']])
            ->get();
        $result = $channels->transform(function ($items) {
            $arr['channelId'] = $items->id;
            $arr['channelName'] = $items->channel_name;
            if ($this->channelDepth($items->id) === true) {
                $arr['child'] = $this->channelChild($items->id);
            }
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }
    // 渠道子集
    public function channelChild($id)
    {
        $children = Channel::where('parent_id', $id)
            ->where('is_delete', 0)
            ->get();
        $list = $children->transform(function ($items) {
            $arr['channelId'] = $items->id;
            $arr['channelName'] = $items->channel_name;
            if ($this->channelDepth($items->id) === true) {
                $arr['child'] = $this->channelChild($items->id);
            }
            return $arr;
        });

        return $list;
    }
    // 渠道深度，是否有子集
    public function channelDepth($id)
    {
        return Channel::where('parent_id', $id)
            ->where('is_delete', 0)
            ->exists();
    }

    /**
     * select框-销售员
     * @param Request $request
     * @return array
     */
    public function sellerSelectList(Request $request)
    {
        $input = $request->all();

        $roleType = Permission::getUserRoleType($input['user']['role_id']);
        $salesId = Permission::getSalesUserId($input['user']['id']);

        $sales = ClubSales::where('club_id', $input['user']['club_id'])
            // 销售只可选择自己
            ->where(function ($query) use ($roleType, $salesId) {
                if ($roleType == 2) {
                    return $query->where('id', $salesId);
                }
            })
            ->where('is_delete', 0)
            ->where('status', 1)
            ->get();
        $result = $sales->transform(function ($items) {
            $arr['sellerId'] = $items->id;
            $arr['sellerName'] = $items->sales_name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * select框-班主任
     * @param Request $request
     * @return array
     */
    public function headTeacherSelectList(Request $request)
    {
        $input = $request->all();
        $teachers = ClubSales::where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->where('status', 1)
            ->get();
        $result = $teachers->transform(function ($items) {
            $arr['headTeacherId'] = $items->id;
            $arr['headTeacherName'] = $items->teacher_name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * select框-场馆
     * @param Request $request
     * @return array
     */
    public function venueSelectList(Request $request)
    {
        $input = $request->all();
        $venues = Venue::where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->where('status', 1)
            ->get();
        $result = $venues->transform(function ($items) {
            $arr['venueId'] = $items->id;
            $arr['venueName'] = $items->name.'('.$items->english_name.')';
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * select框-班级
     * @param Request $request
     * @return array
     */
    public function classSelectList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'venueId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $classes = ClubClass::where('club_id', $input['user']['club_id'])
            ->where('venue_id', $input['venueId'])
            ->where('is_delete', 0)
            ->where('status', 1)
            ->get();
        $result = $classes->transform(function ($items) use ($input) {
            $arr['classId'] = $items->id;
            $arr['className'] = $items->name;
            $arr['list'] = Classes::getClassSelectTime($items->id, $input['user']['club_id']);
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    /**
     * 课程券选择
     * @param Request $request
     * @return array
     */
    public function courseTicketChoose(Request $request)
    {
        $input = $request->all();
        $tickets = CourseTickets::all();
        $result = $tickets->transform(function ($items) {
            $arr['courseTicketId'] = $items->id;
            $arr['courseTicket'] = $items->course_id;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

}