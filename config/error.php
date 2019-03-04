<?php

// 定义错误码
return [
    /*
     * 共通错误提示模块10xx
     */
    'common' => [
        '1001' => '参数有误',
        '1002' => '获取数据失败',
        '1003' => '非法操作',
        '1004' => '数据不存在',
        '1005' => '操作失败',
        '1006' => '无权访问！',
        '1010' => '检测不到此地址',
        '1011' => '数据操作失败',
        '1012' => '上传文件为空',
        '1013' => '默认数据不存在'
    ],

    /*
     * 权限模块 11xx开头
     */
    'permission' => [
        '1101' => '员工账号已存在，不允许添加',
        '1102' => '员工姓名已存在，不允许添加',
        '1103' => '员工手机已存在，不允许添加',
        '1104' => '员工不存在',
        '1105' => '角色已存在',
        '1106' => '角色不存在',
        '1107' => '部门已存在',
        '1108' => '角色不存在',
        '1109' => '添加失败',
        '1110' => '修改失败',
        '1111' => '删除失败',
        '1112' => '部门不存在',
        '1113' => '不可删除',
        '1114' => '请先将部门中的员工转移或删除，再删除角色',
        '1115' => '请先将角色中的员工转移或删除，再删除角色',
        '1116' => '员工姓名已存在，不允许修改',
        '1117' => '已失效员工不可登录',

    ],

    /*
     * 俱乐部模块 12xx开头
     */
    'club' => [
        '1201' => '重置失败',
        '1202' => '操作失败',
        '1203' => '开始时间应该小于结束时间',
        '1204' => '俱乐部不存在',
        '1205' => '前缀已存在',
        '1206' => '密码长度应该在6-20',

    ],

    /*
     * 场馆模块 13xx开头
     */

    'venue' => [
        '1301' => '场馆不存在',
        '1302' => '不存在的场馆',
        '1303' => '状态错误',
        '1304' => '操作失败',
        '1305' => '场馆名称已存在',
        '1306' => '场馆存在学员不能删除',
        '1307' => '获取坐标失败',
        '1308' => '场馆下有班级存在学员，请先转移学员再操作',
    ],

    /*
     * 班级模块 14xx开头
     */

    'class' => [
        '1401' => '班级重名，不能添加',
        '1402' => '班级重名，不能修改',
        '1403' => '班级存在学员，不能删除',
        '1404' => '班级存在课程，不能删除',
        '1405' => '历史课程不能删除',
        '1406' => '班级不存在',
        '1407' => '班主任不存在',
        '1408' => '开始时间必须大于结束时间',
        '1409' => '班级时间不存在',
        '1410' => '图片已删除',
        '1411' => '日期不属于班级上课日期',
        '1412' => '学员签到记录不存在',
        '1413' => '冻结学员只能选择出勤和冻结状态',
        '1414' => '学员已没有可用的课程券',
        '1415' => '课程不存在',
        '1416' => '不可创建晚于当月的课程',
        '1417' => '不存在教练的课程，不能批量创建',
        '1418' => '班级不存在免费体检缴费方案',
        '1419' => '预约学员只能签未出勤、出勤、缺勤',
        '1420' => '该班暂无课程',
        '1421' => '同一场馆下班级不能重复添加',
        '1422' => '当前课程已存在该学员，不可添加外勤',
        '1423' => '课程券不足，无法签到',
        '1424' => '预约记录不存在，无法签到',
        '1425' => '设置其他学员为mvp请先取消当前mvp学员',
        '1426' => '评语不能超过300个字符',
        '1427' => '已存在未失效的教练不可重复添加',
        '1428' => '请选择停课时间',
        '1429' => '最近一年没有课程',
        '1430' => '该班级已关联学员信息，无法删除',
        '1431' => '销售已存在于该班级中',
        '1432' => '已失效的销售不能添加为班主任',
        '1433' => '学员缴费记录班级类型与当前班级类型不匹配',
        '1434' => '班级下有学员存在，请先转移学员再操作'
    ],

    /*
     * 学员模块 16xx开头
     */

    'Student' => [
        '1610' => '学员不存在',
        '1611' => '修改失败',
        '1612' => '添加失败',
        '1613' => '姓名已存在',
        '1614' => '手机号已存在',
        '1615' => '学员已存在',
        '1616' => '审核失败',
        '1617' => '已申请过该学员',
        '1618' => '该验证方式暂未开通',
        '1619' => '证件已经添加，请勿重复添加',
        '1620' => '该学员不可修改证件',
        '1621' => '身份证数据异常，有多个身份证号相同',
        '1622' => '证件添加失败',
        '1623' => '学员信息异常',
        '1624' => '学员预约记录不存在',
        '1625' => '已取消预约，请勿重复操作',
        '1626' => '该学员已出勤，无法取消预约',
        '1627' => '该学员未出勤，无法取消预约',
        '1628' => '取消预约失败',
        '1629' => '缴费方案不存在',
        '1630' => '审核失败',
        '1631' => 'APP注册学员手机号不允许修改',
        '1632' => '不可重复添加',
        '1633' => '移除失败',
        '1634' => '冻结记录不存在',
        '1635' => '学员不存在于此班级中',
        '1636' => 'app账号不存在',
        '1637' => '预约体验过的用户不可重复预约',
        '1638' => '已移除的班级不可添加',
        '1639' => '当前班级为主班级，不能直接移除',
        '1640' => '申请记录不存在',
        '1641' => '销售员不存在',
        '1642' => '学员证件信息未通过验证',
        '1643' => '暂不支持通过护照添加学员',
        '1644' => '学员缴费记录不存在',
        '1645' => '非销售员工不可申请',
        '1646' => '正式学员不能添加非正式专享缴费',
        '1647' => '非正式学员不能添加正式专享缴费',
        '1670' => '退款操作失败',
        '1671' => '该用户非销售员',
        '1672' => '非销售不能操作退款',
        '1673' => '该学员已经绑定过',
        '1674' => '学员绑定失败',
        '1675' => '该学员没有绑定',
        '1676' => '学员身份修改失败',
        '1677' => '操作失败，绑定学员已达上限',
        '1678' => '正式学员不可预约',
        '1679' => '该学员已预约过免费体验',
        '1680' => '俱乐部默认场馆不存在',
        '1681' => '俱乐部默认班级不存在',
        '1682' => '俱乐部默认销售不存在',
        '1683' => '推荐学员销售不存在',
        '1684' => '绑定学员不存在',
        '1685' => '您的手机号暂未注册app账号，请注册后绑定学员后再试',
        '1686' => '非正式学员不可操作冻结'
    ],

    /*
     * 教练模块 17xx开头
     */

    'coach' => [
        '1701' => '请设置当月教练的管理费用',
        '1702' => '请设置选择教练的基本工资',
        '1703' => '请设置选择教练的额定课时'
    ],

    /*
     * 销售模块 18xx开头
     */

    'sales' => [
        '1801' => '没有默认销售',
        '1802' => '请选择时间'
    ],

    /*
     * 预约模块 19xx开头
     */
    'subscribe' => [
        '1901' => '参数有误',
        '1902' => '预约记录不存在',
        '1903' => '预约已取消',
        '1904' => '该课程未出勤，不能取消',
        '1905' => '该课程已出勤，不能取消',
        '1906' => '预约签到记录不存在',
        '1907' => '预约取消失败',
        '1908' => '体验缴费记录不存在',
        '1909' => '没有体验券'
    ],

    /*
     * 课表模块 20xx开头
     */
    'course' => [
        '2001' => '参数有误',
        '2002' => '课程不存在',
        '2003' => '课程已存在',
        '2004' => '创建课程日期不能晚于当前日期'
    ],
    //系统模块
    'sys' => [
        '3001' => '该测验已通过',
        '3002' => '数据已存在',
        '3003' => '俱乐部公告不存在',
        '3004' => '俱乐部公告审核状态修改失败',
        '3005' => '渠道名称已存在',
        '3006' => '已关联学员，不允许删除'
    ],

    'Payment' => [
        '2101' => '缴费方案不存在',
        '2102' => '缴费标签不存在',
        '2103' => '体验缴费不存在',
        '2104' => '缴费方案时长不存在',
        '2105' => '免费体验缴费方案不存在',
        '2106' => '积分兑换课程缴费方案不存在',
        '2107' => '积分兑换课程缴费方案添加失败',
        '2108' => '非正式缴费不能发送合同'
    ],

    'studentPayment' => [
        '2201' => '缴费记录不存在',
        '2202' => '合同编号添加失败',

    ],

    //二维码推广
    'recommend' => [
        '2301' => '奖励课时记录不存在',
        '2302' => '奖励课时修改失败',
        '2303' => '预约奖励记录异常',
        '2304' => '体验奖励课时发放失败',
        '2305' => '追回体验奖励课时失败',
        '2306' => '推荐记录不存在',
        '2307' => '买单奖励记录有误',
        '2308' => '推荐记录状态有误'
    ],

    //学员签到
    'sign' => [
        '2401' => '签到状态修改失败',
        '2402' => '外勤签到失败',
        '2403' => '签到失败',
        '2404' => '学员没有与班级类型匹配的缴费记录，无法签到',
        '2405' => '更改签到课程券不存在'
    ],

    'classMessage' => [
        'date.required' => '时间不能为空!'
    ],

    //积分 26XX
    'score' => [
        '2601' => '积分兑换课程失败'
    ],

    //俱乐部公告 27xx
    'clubMessage' => [
        '2701' => '俱乐部公告不存在',
        '2702' => '公告状态更改失败'
    ],

    //短信 28xx
    'sms' => [
        '2801' => '短信不存在',
        '2802' => '短信审核状态失败',
        '2803' => '短信推送审核失败'
    ],
];