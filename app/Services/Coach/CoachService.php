<?php

namespace App\Services\Coach;

use App\Model\ClubCoach\ClubCoach;
use App\Model\ClubCoachCostSnapshot\ClubCoachCostSnapshot;
use App\Model\ClubCoachRewardPenalty\ClubCoachRewardPenalty;

class CoachService
{
    /**
     * 总基本工资
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getTotBaseSalary($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('basic_salary');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 总额定课时
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getTotCourseTime($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('fixed_course_count');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 总常规课时
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getTotRuleTime($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('real_course_count');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 总惩奖费用
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getTotPenalty($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('reward_penalty');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 总加班费用
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getTotOtPrice($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('ot_salary');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 总当月工资
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getTotMonthPrice($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('final_salary');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 总管理费用
     * @param $clubId
     * @param $year
     * @param $month
     * @return int
     */
    public function getManagePrice($clubId, $year, $month)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('manage_cost');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 常规课时
     * @param $clubId
     * @param $year
     * @param $month
     * @param $coachId
     * @return int
     */
    public function getOneCoachRulePrice($clubId, $year, $month, $coachId)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('coach_id', $coachId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('real_course_count');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 惩奖费用
     * @param $clubId
     * @param $year
     * @param $month
     * @param $coachId
     * @return int
     */
    public function getOneCoachTotPenalty($clubId, $year, $month, $coachId)
    {
        $sum = ClubCoachRewardPenalty::where('club_id', $clubId)
            ->where('coach_id', $coachId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('reward_penalty');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 加班费用
     * @param $clubId
     * @param $year
     * @param $month
     * @param $coachId
     * @return int
     */
    public function getOneCoachTotOtPrice($clubId, $year, $month, $coachId)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('coach_id', $coachId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('ot_salary');

        return !empty($sum) ? $sum : 0;
    }

    /**
     * 当月工资
     * @param $clubId
     * @param $year
     * @param $month
     * @param $coachId
     * @return int
     */
    public function getOneCoachTotMonthPrice($clubId, $year, $month, $coachId)
    {
        $sum = ClubCoachCostSnapshot::where('club_id', $clubId)
            ->where('coach_id', $coachId)
            ->where('year', $year)
            ->where('month', $month)
            ->sum('final_salary');

        return !empty($sum) ? $sum : 0;
    }
}