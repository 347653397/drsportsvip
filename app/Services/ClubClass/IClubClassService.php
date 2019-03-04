<?php

namespace App\Services\ClubClass;


interface IClubClassService
{
    // 班级列表
    public function lists($postData);

    // 添加班级
    public function create($postData);

    // 修改班级
    public function update($postData);

    // 删除班级
    public function delete($postData);

    // 班级详情
    public function detail($postData);

    // 班级概况汇总
    public function classCeneralSituationAll($postData);

    // 班级概况
    public function classCeneralSituation($postData);

    // 学员列表
    public function clueStudentLists($postData);

    // 课程列表
    public function courseLists($postData);
}
