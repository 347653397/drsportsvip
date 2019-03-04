<?php
namespace App\Api\Controllers\Recommend;

use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\Recommend\ClubCourseReward;
use App\Model\Recommend\ClubCourseRewardHistory;
use App\Model\Recommend\ClubRecommendReserveRecord;
use App\Model\Recommend\ClubRecommendRewardRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Model\Recommend\ClubRecommendUser;
use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Exception;

class RecommendController extends Controller
{
    /**
     * 推荐用户列表
     * @param Request $request
     * @return array
     */
    public function recommendList(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];

        $validator = Validator::make($input,[
            'key' => 'nullable|string',
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $key = $input['key'];
        /*$clubId = 1;
        $key = '';
        $input = [
            'pagePerNum' => 10,
            'currentPage' => 1
        ];*/

        $recommendList = ClubRecommendUser::notDelete()
            ->with(['student:id,name,sales_name,created_at'])
            ->where('club_id',$clubId)
            ->when($key,function ($query) use ($key) {
                return $query->where(function ($query) use ($key) {
                    return $query->where('student_name','like',$key.'%')
                        ->orWhere('user_mobile','like',$key.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $arr = [];

        collect($recommendList->items())->each(function ($item) use (&$arr) {
            $arr[] = [
                'stuName' => $item->student ? $item->student->name : '',
                'saleName' => $item->student ? $item->student->sales_name : '',
                'userMobile' => $item->user_mobile,
                'joinClubDate' => $item->student ? Carbon::parse($item->student->created_at)->toDateTimeString() : ''
            ];
        });

        $returnData = [
            'totalElements' => $recommendList->total(),
            'totalPage' => ceil($recommendList->total() / $input['pagePerNum']),
            'content' => $arr
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 推荐预约列表
     * @param Request $request
     * @return array
     */
    public function reserveList(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];

        $validator = Validator::make($input,[
            'key' => 'nullable|string',
            'saleId' => 'nullable|numeric',
            'recommendStatus' => Rule::in([0,1,2,3]),
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $key = $input['key'];
        $saleId = isset($input['saleId']) ? $input['saleId'] : 0;
        $recommendStatus = isset($input['recommendStatus']) ? $input['recommendStatus'] : 0;

        $reserveList = ClubRecommendReserveRecord::notDelete()
            ->with(['student:id,name,sales_name'])
            ->where('club_id',$clubId)
            ->when($saleId > 0,function ($query) use ($saleId) {
                return $query->where('sale_id',$saleId);
            })
            ->when($recommendStatus > 0,function ($query) use ($recommendStatus) {
                return $query->where('recommend_status',$recommendStatus);
            })
            ->when($key,function ($query) use ($key) {
                return $query->where(function ($query) use ($key) {
                    return $query->where('new_stu_name','like',$key.'%')
                        ->orWhere('new_mobile','like',$key.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $arr = [];
        collect($reserveList->items())->each(function ($item) use (&$arr) {
            $arr[] = [
                'stuName' => $item->new_stu_name,
                'age' => $item->new_stu_age,
                'salesName' => $item->student ? $item->student->sales_name : '',
                'userMobile' => $item->new_mobile,
                'recommendStatus' => $item->recommend_status,
                'reserveDate' => Carbon::parse($item->created_at)->toDateTimeString()
            ];
        });

        $returnData = [
            'totalElements' => $reserveList->total(),
            'totalPage' => ceil($reserveList->total() / $input['pagePerNum']),
            'content' => $arr
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 预约奖励记录所有销售
     * @param Request $request
     * @return array
     */
    public function getClubReserveSales(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];

        $allRecordSales = ClubRecommendReserveRecord::notDelete()
            ->where('club_id',$clubId)
            ->with(['sales:id,sales_name'])
            ->select('sale_id')
            ->get();

        $arr = [];
        collect($allRecordSales)->each(function ($item) use (&$arr) {
            if (!array_key_exists($item->sale_id,$arr)) {
                $arr[$item->sale_id] = [
                    'salesId' => $item->sale_id,
                    'salesName' => !empty($item->sales) ? $item->sales->sales_name : ''
                ];
            }
        });

        if (!empty($arr)) {
            $arr = array_values($arr);
        }

        return returnMessage('200','',$arr);
    }

    /**
     * 推广奖励列表
     * @param Request $request
     * @return array
     */
    public function rewardList(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];

        $validator = Validator::make($input,[
            'key' => 'nullable|string',
            'settleStatus' => Rule::in([0,1,2]),
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric',
        ]);

        if ($validator->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $key = $input['key'];
        $settleStatus = isset($input['settleStatus']) ? $input['settleStatus'] : 0;

        $rewardList = ClubRecommendRewardRecord::notDelete()
            ->where('club_id',$clubId)
            ->when($settleStatus > 0,function ($query) use ($settleStatus) {
                return $query->where('settle_status',$settleStatus);
            })
            ->when($key,function ($query) use ($key) {
                return $query->where(function ($query) use ($key) {
                    return $query->where('new_stu_name','like',$key.'%')
                        ->orWhere('stu_name','like',$key.'%')
                        ->orWhere('new_mobile','like',$key.'%')
                        ->orWhere('user_mobile','like',$key.'%');
                });
            })
            ->orderByDesc('created_at')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $arr = [];
        collect($rewardList->items())->each(function ($item) use (&$arr) {
            $arr[] = [
                'recommendAccount' => $item->user_mobile,
                'rewardStudent' => $item->stu_name,
                'recommendedAccount' => $item->new_mobile,
                'recommendedStudent' => $item->new_stu_name,
                'eventType' => $item->event_type,
                'rewardCourseNum' => $item->reward_course_num,
                'settleStatus' => $item->settle_status
            ];
        });

        $returnData = [
            'totalElements' => $rewardList->total(),
            'totalPage' => ceil($rewardList->total() / $input['pagePerNum']),
            'content' => $arr
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 奖励课时设置列表
     * @param Request $request
     * @return array
     */
    public function rewardCourseSettleList(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];

        $rewardCourse = ClubCourseReward::notDelete()
            ->where('club_id',$clubId)
            ->first();

        if (empty($rewardCourse)) return returnMessage('200');

        $returnData = [
            'rewardId' => $rewardCourse->id,
            'tryRewardNum' => $rewardCourse->num_for_try,
            'buyCourseRewardNum' => $rewardCourse->num_for_buy
        ];

        return returnMessage('200','',$returnData);
    }

    /**
     * 奖励课时修改
     * @param Request $request
     * @return array
     */
    public function modifyRewardSettle(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];

        $validator = Validator::make($input,[
            'rewardId' => 'required|numeric',
            'tryRewardNum' => 'required|numeric',
            'buyCourseRewardNum' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $tryRewardNum = $input['tryRewardNum'];
        $buyCourseRewardNum = $input['buyCourseRewardNum'];
        $rewardCourse = ClubCourseReward::notDelete()->find($input['rewardId']);
        $userId = $input['user']['id'];

        if (empty($rewardCourse)) {
            return returnMessage('2301', config('error.recommend.2301'));
        }

        if ($clubId != $rewardCourse->club_id) {
            return returnMessage('1003', config('error.common.1003'));
        }

        try {
            DB::transaction(function () use ($rewardCourse,$tryRewardNum,$buyCourseRewardNum,$userId) {
                $rewardCourseHistory = new ClubCourseRewardHistory();
                $rewardCourseHistory->club_id = $rewardCourse->club_id;
                $rewardCourseHistory->course_reward_id = $rewardCourse->id;
                $rewardCourseHistory->operator_id = $userId;
                $rewardCourseHistory->num_for_try = $tryRewardNum;
                $rewardCourseHistory->num_for_buy = $buyCourseRewardNum;
                $rewardCourseHistory->saveOrFail();

                $rewardCourse->num_for_try = $tryRewardNum;
                $rewardCourse->num_for_buy = $buyCourseRewardNum;
                $rewardCourse->saveOrFail();
            });
        } catch (Exception $e) {
            return returnMessage('2302', config('error.recommend.2302'));
        }

        return returnMessage('200','修改成功');
    }

    /**
     * 奖励课时历史修改记录
     * @param Request $request
     * @return array
     */
    public function rewardModifyHistory(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input,[
            'rewardId' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $rewardCourseHistorys = ClubCourseRewardHistory::where('course_reward_id',$input['rewardId'])
            ->with(['operator'])
            ->orderByDesc('created_at')
            ->get();

        $arr = [];
        collect($rewardCourseHistorys)->each(function ($item) use (&$arr) {
            $arr[] = [
                'tryRewardNum' => $item->num_for_try,
                'buyCourseRewardNum' => $item->num_for_buy,
                'modifyDate' => Carbon::parse($item->created_at)->toDateTimeString(),
                'operator' => $item->operator ? $item->operator->username : ''
            ];
        });

        return returnMessage('200','',$arr);
    }

}