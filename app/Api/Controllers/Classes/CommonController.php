<?php

namespace App\Api\Controllers\Classes;

use App\Facades\Classes\Classes;
use App\Http\Controllers\Controller;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassTeacher\Teacher;
use App\Model\ClubClassType\ClubClassType;
use App\Model\ClubCoach\ClubCoach;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubPaymentTag\ClubPaymentTag;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubVenue\ClubVenue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommonController extends Controller
{
    // 班级类型select
    public function classTypeSelect(Request $request)
    {
        $result = ClubClassType::all();
        $list = $result->transform(function ($items) {
            $arr['typeId'] = $items->id;
            $arr['typeName'] = $items->name;
            return $arr;
        });
        $data['result'] = $list;
        return returnMessage('200', '', $data);
    }

    // 缴费标签select
    public function paymentSelect(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'type' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $result = ClubPayment::where('club_id', $input['user']['club_id'])
            ->where('is_default', 0)
            ->where('type', $input['type'])
            ->distinct()
            ->pluck('payment_tag')
            ->toArray();
        return returnMessage('200', '', $result);
    }

    // 场馆select
    public function venueSelectList(Request $request)
    {
        $input = $request->all();
        $venues = ClubVenue::where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->get();
        $result = $venues->transform(function ($items) {
            $arr['venueId'] = $items->id;
            $arr['venueName'] = $items->name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    // 场馆下班级select
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

    // 班主任select
    public function headTeacherSelectList(Request $request)
    {
        $input = $request->all();
        $teachers = ClubSales::where('club_id', $input['user']['club_id'])
            ->where('status', 1)
            ->get();
        $result = $teachers->transform(function ($items) {
            $arr['headTeacherId'] = $items->id;
            $arr['headTeacherName'] = $items->sales_name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    // 教练select
    public function coachSelectList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'searchVal' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $searchVal = isset($input['searchVal']) ? $input['searchVal'] : '';

        $coach = ClubCoach::where('club_id', $input['user']['club_id'])
            ->where(function ($query) use ($searchVal) {
                if (!empty($searchVal)) {
                    return $query->where('name', 'like', '%' . $searchVal . '%');
                }
            })
            ->where('is_delete', 0)
            ->get();

        $result = $coach->transform(function ($items) {
            $arr['coachId'] = $items->id;
            $arr['coachName'] = $items->name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }
}