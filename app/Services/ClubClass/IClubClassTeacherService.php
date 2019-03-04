<?php

namespace App\Services\ClubClass;


interface IClubClassTeacherService
{
    // 班主任报告汇总
    public function all();

    // 班主任报告列表
    public function lists($postData);
}
