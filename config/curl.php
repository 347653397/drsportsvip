<?php

/*
 * content:和外部项目通讯的公用curl配置
 *
 * author:wangchu.song
 *
 * date: 2018-9-19
 *
 */

return [


    // 接收俱乐部后台提交的测验审核

    'dr_exam' => env('HTTPS_PREFIX','http://') . env('APP_ADMIN_INNER_DOMAIN','admin.drsportsvip.com/') . 'exam/acceptClubExam',

];
