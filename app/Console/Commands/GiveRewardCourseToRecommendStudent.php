<?php
namespace App\Console\Commands;

use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubSales\ClubSales;
use App\Model\Recommend\ClubRecommendReserveRecord;
use Illuminate\Console\Command;
use Carbon\Carbon;
use App\Facades\Util\Log;
use App\Model\ClubStudentPayment\ClubStudentPayment;
use App\Model\Recommend\ClubRecommendRewardRecord;
use App\Model\ClubPayment\ClubPayment;
use Illuminate\Support\Facades\DB;
use Exception;
use App\Model\ClubStudent\ClubStudent;
use App\Facades\ClubStudent\Student;

class GiveRewardCourseToRecommendStudent extends Command
{
    CONST CONDITION_COUNT_FOR_REWARD = 10;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'GiveRewardCourseToRecommendStudent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'give reward course to recommend student';

    /**
     * Create a new command instance.
     *
     * GiveRewardCourseToRecommendStudent constructor
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-start');

        //reward_status 默认0，1：未发放，2：已发放，3：已失效
        $stuPayments = ClubStudentPayment::where('recommend_id','>',0)
            ->where('reward_status',1)
            ->get();

        if ($stuPayments->isEmpty()) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-暂时没有奖励的推荐的用户');
            return;
        }

        $courseTickets = ClubCourseTickets::notDelete()
            ->whereIn('payment_id',collect($stuPayments)->pluck('id'))
            ->where('status',1)
            ->select('payment_id', DB::raw('COUNT(*) as used_count'))
            ->groupBy('payment_id')
            ->havingRaw('used_count >= '.self::CONDITION_COUNT_FOR_REWARD)
            ->get();

        if ($courseTickets->isEmpty()) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-没有满足达到指定签到次数需要发放奖励的记录');
            return;
        }

        $ticketsPaymentIds = collect($courseTickets)->pluck('payment_id');
        $studentPayments = ClubStudentPayment::with(['refund'])->whereIn('id',$ticketsPaymentIds)
            ->get();

        if ($studentPayments->isEmpty()) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-数据有误',['paymentIds' => $ticketsPaymentIds->toArray()]);
            return;
        }

        try {
            DB::transaction(function () use ($studentPayments) {
                collect($studentPayments)->each(function ($item) {
                    //如果有退款的，缴费记录reward_status需要置为3：已失效
                    if (!empty($item->refund)) {
                        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-更新有退款的缴费记录失败',['stuPaymentId' => $item->id]);
                        $item->reward_status = 3;
                        $item->saveOrFail();
                    }

                    try {
                        $this->addBuyRewardToRecommendStudent($item->recommend_id);
                        $item->reward_status = 2;
                        $item->saveOrFail();
                    } catch (Exception $e) {
                        $arr = [
                            'code' => $e->getCode(),
                            'msg' => $e->getMessage()
                        ];
                        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-添加奖励数据有误',[$arr]);
                    }

                });
            });
        } catch (Exception $e) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-赠送买单奖励数据操作错误',['paymentIds' => $ticketsPaymentIds->toArray()]);
            return;
        }

        Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-end',['paymentIds' => $ticketsPaymentIds->toArray()]);
    }

    /**
     * 给推荐用户添加买单奖励记录
     * @param $recommendId
     * @throws Exception
     * @throws \Throwable
     */
    public function addBuyRewardToRecommendStudent($recommendId)
    {
        $reserveRecord = ClubRecommendReserveRecord::find($recommendId);

        if (empty($reserveRecord)) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-推荐奖励记录不存在',['recommend_id' => $recommendId]);
            throw new Exception(config('error.recommend.2307'),'2307');
        }

        if ($reserveRecord->recommend_status == 3) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-推荐奖励记录状态有误',['recommend_id' => $recommendId,'status' => $reserveRecord->recommend_status]);
            throw new Exception(config('error.recommend.2308'),'2308');
        }

        $rewardRecord = ClubRecommendRewardRecord::where('recommend_id',$recommendId)
            ->where('event_type',2)
            ->first();

        if (empty($rewardRecord)) {
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-买单奖励记录不存在',['recommend_id' => $recommendId]);
            throw new Exception(config('error.recommend.2307'),'2307');
        }

        if ($rewardRecord->settle_status == 2) {//已结算，不用处理
            Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-买单奖励已发放',['recommend_id' => $recommendId]);
            return;
        }

        $buyCourseRewardNum = $rewardRecord->reward_course_num; //买单奖励课时数
        if ($buyCourseRewardNum > 0) {
            //获取活动缴费方案
            $activityPayment = ClubPayment::where('club_id',$rewardRecord->club_id)
                ->where('tag',3)
                ->where('is_default', 1)
                ->first();

            if (empty($activityPayment)) {
                Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-该俱乐部下没有活动缴费方案',['clubId' => $rewardRecord->club_id]);
                return;
            }

            $sales = ClubSales::find($reserveRecord->sale_id);

            if (empty($sales)) {
                Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-学员无销售',['recommendId' => $reserveRecord->id]);
                return;
            }

            $student = ClubStudent::notDelete()->find($rewardRecord->stu_id);

            if (empty($student)) {
                Log::setGroup('RecommendError')->error('买单奖励课时给推荐用户-奖励学员不存在',['recommend_stu_id' => $rewardRecord->stu_id]);
                return;
            }

            for ($i=0;$i<$buyCourseRewardNum;$i++) {
                $stuPayment = $this->addStudentPayment($reserveRecord,$rewardRecord,$activityPayment,$sales);
                $this->addStudentsTickets($stuPayment,$activityPayment);
            }

            $this->addCourseCountToStudent($student,$buyCourseRewardNum);
        }

        $reserveRecord->recommend_status = 3;   //更改推荐记录状态

        $rewardRecord->settle_status = 2;   //更改奖励记录状态

        $reserveRecord->saveOrFail();
        $rewardRecord->saveOrFail();

    }

    /**
     * 添加缴费方案（活动缴费）
     * @param ClubRecommendReserveRecord $reserveRecord
     * @param ClubRecommendRewardRecord $rewardRecord
     * @param ClubPayment $activityPayment
     * @param ClubSales $sales
     * @return ClubStudentPayment
     * @throws \Throwable
     */
    public function addStudentPayment(ClubRecommendReserveRecord $reserveRecord,ClubRecommendRewardRecord$rewardRecord,ClubPayment $activityPayment,ClubSales $sales)
    {
        $stuPayment = new ClubStudentPayment();
        $stuPayment->student_id = $rewardRecord->stu_id;
        $stuPayment->club_id = $rewardRecord->club_id;
        $stuPayment->payment_id = $activityPayment->id;
        $stuPayment->payment_name = $activityPayment->name;
        $stuPayment->payment_tag_id = $activityPayment->tag;
        $stuPayment->payment_class_type_id = $activityPayment->type;
        $stuPayment->course_count = $activityPayment->course_count;
        $stuPayment->pay_fee = $activityPayment->price;
        $stuPayment->equipment_issend = 0;
        $stuPayment->payment_date = Carbon::now()->toDateString();
        $stuPayment->channel_type = 4;
        $stuPayment->expire_date = Carbon::now()->addMonth($activityPayment->use_to_date)->toDateString();
        $stuPayment->is_pay_again = 0;
        $stuPayment->sales_id = $reserveRecord->sale_id;
        $stuPayment->sales_dept_id = $sales->sales_dept_id;
        $stuPayment->reserve_record_id = $rewardRecord->id;

        $stuPayment->saveOrFail();

        return $stuPayment;
    }

    /**
     * 添加课程券(活动缴费方案券)
     * @param $stuPayment
     * @param $activityPayment
     * @throws \Throwable
     */
    public function addStudentsTickets($stuPayment,$activityPayment)
    {
        $tickets = new ClubCourseTickets();
        $tickets->payment_id = $stuPayment->id;
        $tickets->club_id = $stuPayment->club_id;
        $tickets->student_id = $stuPayment->student_id;
        $tickets->expired_date = Carbon::now()->addMonth($activityPayment->use_to_date)->toDateString();
        $tickets->status = 2;
        $tickets->unit_price = 0;
        $tickets->reward_type = 2;

        $tickets->saveOrFail();
    }

    /**
     * 给推荐的学员增加课时数
     * @param ClubStudent $clubStudent
     * @param $buyCourseRewardNum
     * @throws Exception
     * @throws \Throwable
     */
    public function addCourseCountToStudent(ClubStudent $clubStudent,$buyCourseRewardNum)
    {
        $clubStudent->left_course_count = $clubStudent->left_course_count + $buyCourseRewardNum;

        if ($clubStudent->status == 3) {//公海库学员则需要将状态变为非正式学员
            $defaultVenue = Student::getDefaultStuData($clubStudent->club_id,1);
            $defaultClass = Student::getDefaultStuData($clubStudent->club_id,2);
            $defaultSales = Student::getDefaultStuData($clubStudent->club_id,3);

            if (empty($defaultVenue) || empty($defaultClass) || empty($defaultSales)) {
                throw new Exception(config('error.common.1013'),'1013');
            }

            $clubStudent->venue_id = $defaultVenue->id;
            $clubStudent->sales_id = $defaultSales->id;
            $clubStudent->sales_name = $defaultSales->sales_name;
            $clubStudent->main_class_id = $defaultClass->id;
            $clubStudent->main_class_name = $defaultClass->name;

            $clubStudent->status = 2;
        }

        $clubStudent->saveOrFail();
    }
}