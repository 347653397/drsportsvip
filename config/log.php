<?php
/**
 * @abstract
 * @name: 日志模块
 * @author: johnzhang
 * @date: 2018-05-03
 * @time: 16:40
 * @copyright 动博士
 */

return [

    'groups' => [
        'sys' => [
            'slug' => 'sys',
            'folder' => '/',

            'name' => '系统',
            'icon' => 'fa-info-circle',
            'classes' => 'bg-red',
            'color' => '#B71C1C',
        ],
        'db' => [
            'slug' => 'db',
            'folder' => 'db',
            'name' => '数据库',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'ExceptionRequest' => [
            'slug' => 'ExceptionRequest',
            'folder' => 'ExceptionRequest',
            'name' => '异常请求信息',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'SmsError' => [
            'slug' => 'SmsError',
            'folder' => 'SmsError',
            'name' => '短信发送失败',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'Subscribe' => [
            'slug' => 'SubscribeError',
            'folder' => 'SubscribeError',
            'name' => '预约失败',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'RecommendError' => [
            'slug' => 'RecommendError',
            'folder' => 'RecommendError',
            'name' => '二维码预约推广',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'StuPaymentError' => [
            'slug' => 'StuPaymentError',
            'folder' => 'StuPaymentError',
            'name' => '学员缴费记录',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'StuSignError' => [
            'slug' => 'StuSignError',
            'folder' => 'StuSignError',
            'name' => '学员签到记录',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'ReserveError' => [
            'slug' => 'ReserveError',
            'folder' => 'ReserveError',
            'name' => '预约异常',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'ProductDataError' => [
            'slug' => 'ProductDataError',
            'folder' => 'ProductDataError',
            'name' => '生成默认数据',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'SubscribeError' => [
            'slug' => 'SubscribeError',
            'folder' => 'SubscribeError',
            'name' => '预约失败',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
        'ProduceCourseSign' => [
            'slug' => 'ProduceCourseSign',
            'folder' => 'ProduceCourseSign',
            'name' => '生成课程签到',
            'icon' => 'fa-thumbs-o-up',
            'classes' => 'bg-green',
            'color' => '#00a65a',
        ],
    ],
    'levels' => [
        /**
         * Font awesome >= 4.3
         * http://fontawesome.io/icons/
         */
        'all'       => [
            'color' => 'primary',
            'icon'  => 'fa fa-fw fa-list',
        ],
        'emergency' => [
            'color' => 'danger',
            'icon'  => 'fa fa-fw fa-bug',
        ],
        'alert' => [
            'color' => 'danger',
            'icon'  => 'fa fa-fw fa-bullhorn',
        ],
        'critical' => [
            'color' => 'danger',
            'icon'  => 'fa fa-fw fa-heartbeat',
        ],
        'error' => [
            'color' => 'danger',
            'icon'  => 'fa fa-fw fa-times-circle',
        ],
        'warning' => [
            'color' => 'warning',
            'icon'  => 'fa fa-fw fa-exclamation-triangle',
        ],
        'notice' => [
            'color' => 'info',
            'icon'  => 'fa fa-fw fa-exclamation-circle',
        ],
        'info' => [
            'color' => 'info',
            'icon'  => 'fa fa-fw fa-info-circle',
        ],
        'debug' => [
            'color' => 'info',
            'icon'  => 'fa fa-fw fa-life-ring',
        ],
    ],
];
