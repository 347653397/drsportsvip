<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/21
 * Time: 11:39
 */

namespace App\Console\Commands;


use App\Model\ClubCoach\ClubCoach;
use App\Model\ClubCoachCostSnapshot\ClubCoachCostSnapshot;
use App\Model\ClubCourseCoach\ClubCourseCoach;
use Illuminate\Console\Command;

class CoachLastMonthSalary extends Command
{
    protected $signature = 'CoachLastMonthSalary';

    protected $description = 'Coach snapshot last month';

    /**
     * 定时生成教练上个月数据
     */
    public function handle()
    {
        $nowDate = date('Y-m',time());
        $lastDate = date('Y-m',strtotime("$nowDate -1 month"));
        $lastYear = date('Y',strtotime($lastDate));
        $lastMonth = date('m',strtotime($lastDate));
        $date = $lastYear . '-' . $lastMonth;

        $clubId = ClubCoach::distinct('club_id')->select('club_id')->get();

        foreach ($clubId as $clubVal)
        {
            $coach = ClubCoach::where('club_id', $clubVal->club_id)
                ->where('status', 1)
                ->where('is_delete', 0)
                ->with('reward')
                ->get();

            foreach ($coach as $coachVal)
            {
                //常规课时
                $ruleTime = $this->course($coachVal->id, $clubVal->club_id, $date);
                //加班费用
                $otPenalty = $this->getOtPenalty($coachVal->course_time, $ruleTime, $coachVal->ot_price);
                //惩奖
                $reward = $this->getReward($coachVal->reward, $lastYear, $lastMonth, $clubVal->club_id);
                $costSnapshot = new ClubCoachCostSnapshot();
                $costSnapshot->club_id = $clubVal->club_id;
                $costSnapshot->coach_id = $coachVal->id;
                $costSnapshot->year = $lastYear;
                $costSnapshot->month = $lastMonth;
                $costSnapshot->manage_cost = $this->getManagePrice($coachVal->id, $clubVal->club_id, $date);
                $costSnapshot->fixed_course_count = $coachVal->course_time;
                $costSnapshot->real_course_count = $ruleTime;
                $costSnapshot->ot_salary = $otPenalty;
                $costSnapshot->basic_salary = $coachVal->basic_salary;
                $costSnapshot->reward_penalty = $reward;
                $costSnapshot->final_salary = $coachVal->basic_salary+$otPenalty+$reward;
                $costSnapshot->save();
            }
        }
    }

    //获取对应月份课程
    protected function course($id, $clubId, $date)
    {
        return ClubCourseCoach::where('club_id',$clubId)
            ->where('coach_id',$id)
            ->where('status',1)
            ->where('is_delete',0)
            ->whereHas('course',function ($query) use($date,$clubId) {
                $query->where('day','like',$date.'%')->where('status',1)->where('club_id',$clubId);
        })->count();
    }

    //获取加班费
    protected function getOtPenalty($course, $ruleCourse, $otPrice)
    {
        if($course < $ruleCourse){
            $price = ($ruleCourse-$course)*$otPrice;
        }
        else{
            $price = 0;
        }

        return $price;
    }

    //获取对应年月的奖罚
    protected function getReward($data, $year, $month, $clubId)
    {
        $reward = 0;
        foreach ($data as $val){
            if($val->year == $year && $val->month == $month && $val->club_id = $clubId){
                $reward = $val->reward_penalty;
            }
        }
        return $reward;
    }

    //累加管理费
    public function getManagePrice($id, $clubId, $date)
    {
        $managePrice  = ClubCourseCoach::where('club_id',$clubId)
            ->where('coach_id',$id)
            ->where('status',1)
            ->where('is_delete',0)
            ->whereHas('course',function ($query) use($date,$clubId) {
                $query->where('day','like',$date.'%')->where('status',1)->where('club_id',$clubId);
        })->sum('manage_cost');

        return $managePrice;
    }
}