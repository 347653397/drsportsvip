<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// 学员模块/tracy
Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

$api = app('Dingo\Api\Routing\Router');

$api->version('v1', function ($api) {
    //测试
    $api->get('test/helloworld', 'App\Api\Controllers\TestController@test');
    $api->get('test/initQrcodePayments', 'App\Api\Controllers\TestController@initQrcodePayments');
    //$api->post('qrcode/recommendList','App\Api\Controllers\Recommend\RecommendController@recommendList');

    $api->group(['prefix' => 'test', 'middleware' => ['ClubAdmin','ClubUserAuth','Log']], function ($api) {
        $api->get('/test1', 'App\Api\Controllers\TestController@test1');
    });

    $api->group(['prefix' => 'drsports','namespace' => 'App\Api\Controllers\Drsports'],function($api){
        $api->get('getExamBasic','DrsportsController@getExamBasic');
        $api->get('/aggreeClubExam','DrsportsController@aggreeClubExam');
        $api->get('/refuseClubExam','DrsportsController@refuseClubExam');
        $api->get('/getAllClubList','DrsportsController@getAllClubList');
        $api->get('/getClubName','DrsportsController@getClubName');
        $api->get('/getAllClubArea','DrsportsController@getAllClubArea');
        $api->get('/venueDetail','DrsportsController@venueDetail');
        $api->get('/venueLists','DrsportsController@venueLists');
        $api->get('/classDetail','DrsportsController@classDetail');
        $api->get('/incrClassTransNum','DrsportsController@incrClassTransNum');
        $api->get('/getAllRigion','DrsportsController@getAllRigion');
        $api->get('/getNearCourses','DrsportsController@getNearCourses');
        $api->get('/getAllSportsType','DrsportsController@getAllSportsType');
        $api->get('/getAllCourseType','DrsportsController@getAllCourseType');
        $api->get('/myReservationList','DrsportsController@myReservationList');
        $api->get('/myCourseStatics','DrsportsController@myCourseStatics');
        $api->get('/myMvpList','DrsportsController@myMvpList');
        $api->get('/selectCourseTime','DrsportsController@selectCourseTime');
        $api->get('/reservationDetail','DrsportsController@reservationDetail');
        $api->get('/reserveConfirm','DrsportsController@reserveConfirm');
        $api->get('/confirmStudentInfo','DrsportsController@confirmStudentInfo');
        $api->get('/getClassBasicAndCoachComments','DrsportsController@getClassBasicAndCoachComments');
        $api->get('/bindStudentBySeriesNum','DrsportsController@bindStudentBySeriesNum');
        $api->get('/getBindStudents','DrsportsController@getBindStudents');
        $api->get('/myCourseConsultant','DrsportsController@myCourseConsultant');
        $api->get('/getClassBasicInfo','DrsportsController@getClassBasicInfo');
        $api->get('/getMyCourseTables','DrsportsController@getMyCourseTables');
        $api->get('/classBanner','DrsportsController@classBanner');
        $api->get('/venuesBanner','DrsportsController@venuesBanner');
        $api->get('/getClassBasicForOrder','DrsportsController@getClassBasicForOrder');
        $api->get('/findClubByStuSeriesNum','DrsportsController@findClubByStuSeriesNum');
        $api->get('/getClassListByIds','DrsportsController@getClassListByIds');
        $api->get('/getStudentBasic','DrsportsController@getStudentBasic');
        $api->get('/changeStuStatus','DrsportsController@changeStuStatus');
        $api->get('/getAllClassListForES','DrsportsController@getAllClassListForES');
        $api->get('/getVenuesClassNumAndStuNum','DrsportsController@getVenuesClassNumAndStuNum');
        $api->get('/getStudentIdCardInfo','DrsportsController@getStudentIdCardInfo');
        $api->get('/fullFillStudentCore','DrsportsController@fullFillStudentCore');
        $api->get('/getPaymentByPayRecordId','DrsportsController@getPaymentByPayRecordId');
        $api->post('/getPaymentsByPayRecordIds','DrsportsController@getPaymentsByPayRecordIds');
        $api->get('/getMyExamLists','DrsportsController@getMyExamLists');
        $api->get('/getPaymentByPlanId','DrsportsController@getPaymentByPlanId');
        $api->get('/getBindOfficalStudents','DrsportsController@getBindOfficalStudents');
        $api->get('/getBindNotOfficalStudents','DrsportsController@getBindNotOfficalStudents');
        $api->post('/getHasBindStudentsUsersCount','DrsportsController@getHasBindStudentsUsersCount');
        $api->post('/getUserBindStudents','DrsportsController@getUserBindStudents');
        $api->post('/getUsersBindOneStudent','DrsportsController@getUsersBindOneStudent');
        $api->get('/getClubBindStudentsStatics','DrsportsController@getClubBindStudentsStatics');
        $api->post('/getClubsByClubIds','DrsportsController@getClubsByClubIds');
        $api->post('/venuesByClubIds','DrsportsController@venuesByClubIds');
        $api->post('/classesByVenueIds','DrsportsController@classesByVenueIds');
        $api->post('/findStudentsByClassPrams','DrsportsController@findStudentsByClassPrams');
        $api->get('/getBindUserMobileForStudentsByStuType','DrsportsController@getBindUserMobileForStudentsByStuType');
        $api->get('/contractCompleteNotice','DrsportsController@contractCompleteNotice');
        $api->post('/getBindUserMobileForStudentsByStuIds','DrsportsController@getBindUserMobileForStudentsByStuIds');
        $api->post('/getAddressNameByCodes','DrsportsController@getAddressNameByCodes');
        $api->post('/changeClubNoticeStatus','DrsportsController@changeClubNoticeStatus');
        $api->get('/confirmStudentInfoForBuy','DrsportsController@confirmStudentInfoForBuy');
        $api->get('/reserveDetail','DrsportsController@reserveDetail');
        $api->post('/cancelReserve','DrsportsController@cancelReserve');
        $api->get('/getSalesIdByStudentId','DrsportsController@getSalesIdByStudentId');
        $api->get('/myCourseStaticsForV1','DrsportsController@myCourseStaticsForV1');
        $api->get('/rewardDetails','DrsportsController@rewardDetails');
        $api->post('/getUnBindClubPayments','DrsportsController@getUnBindClubPayments');
        $api->get('/getBindStudentClubIds','DrsportsController@getBindStudentClubIds');
        $api->post('/getPaymentByPlanIds','DrsportsController@getPaymentByPlanIds');
        $api->get('/getBindStudentByAppUserMobileAndClubId','DrsportsController@getBindStudentByAppUserMobileAndClubId');
        $api->get('/findVenueExistsForApp','DrsportsController@findVenueExistsForApp');
        $api->get('/findClassExistsForApp','DrsportsController@findClassExistsForApp');

        $api->post('/exchangeCourse','ScoreController@exchangeCourse');
        $api->get('/addOneScoreCoursePayment','ScoreController@addOneScoreCoursePayment');
    });

   //获取七牛token
    $api->group(['prefix' => 'common','middleware'=> 'ClubAdmin'],function($api){
        $api->post('/getqiniutoken', 'App\Api\Controllers\Common\CommonController@getQiNiuToken');
    });
    $api->group([ 'prefix' => 'test' , 'middleware' => ['ClubAdmin','Log']],function($api){
        $api->post('/helloworld', 'App\Api\Controllers\TestController@test');
        $api->post('/helloworld2', 'App\Api\Controllers\TestController@test2');
    });


    //学员缴费
    $api->group(['prefix' => 'student','middleware' => ['ClubAdmin','Log']],function ($api){
        $api->post('/payrecord','App\Api\Controllers\Students\StudentPayController@payRecord');//学员缴费记录列表
        $api->post('/editGrant','App\Api\Controllers\Students\StudentPayController@editGrant');//学员缴费装备发放修改
        $api->post('/addpay','App\Api\Controllers\Students\StudentPayController@addPay');//学员添加缴费记录
        $api->post('/editpay','App\Api\Controllers\Students\StudentPayController@editPay');//学员修改缴费记录
        $api->post('/delpay','App\Api\Controllers\Students\StudentPayController@delPay');//删除学员缴费记录
        $api->post('/paydetails','App\Api\Controllers\Students\StudentPayController@payDetails');//学员缴费详情 --未完成
        $api->post('/editdetails','App\Api\Controllers\Students\StudentPayController@editDetails');//修改学员缴费详情（销售员，实付款，过期时间）
        $api->post('/refund','App\Api\Controllers\Students\StudentPayController@refund');//学员退款
        $api->post('/studenttickets','App\Api\Controllers\Students\StudentPayController@studentTickets');//课程券
        $api->post('/ticket','App\Api\Controllers\Students\StudentPayController@ticket');//checkBox 课程券
        $api->post('/getPaymentSelect','App\Api\Controllers\Students\StudentPayController@getPaymentSelect');
        $api->post('/sendContract','App\Api\Controllers\Students\StudentPayController@sendContract');//发送合同
    });

    //学员反馈
    $api->group(['prefix' => 'student', 'middleware' => ['ClubAdmin','Log']],function($api){
        $api->post('/studentFeedBackReason','App\Api\Controllers\Students\StudentFeedbackController@getFeedbackReason'); // 反馈原因 checkBox
        $api->post('/studentFeedbackList','App\Api\Controllers\Students\StudentFeedbackController@getFeedback');//学员反馈列表
        $api->post('/addFeedback','App\Api\Controllers\Students\StudentFeedbackController@addFeedback');//添加学员反馈
    });

    //学员备注
    $api->group(['prefix' => 'student','middleware' => ['ClubAdmin','Log']],function($api){
        $api->post('/studentRemark','App\Api\Controllers\Students\StudentRemarkController@getStudentRemark');//获取学员备注列表
        $api->post('/modifyStudentRemark','App\Api\Controllers\Students\StudentRemarkController@addRemark');//增加学员备注
    });

    //登录模块
    $api->group(['prefix' => 'login'],function($api){
        $api->get('/getcode','App\Api\Controllers\Login\LoginController@getCode'); //获取验证码
        $api->post('/login','App\Api\Controllers\Login\LoginController@login'); //登录
        $api->post('/getcodetoken','App\Api\Controllers\Login\LoginController@getCodeToken');//获取key
        $api->post('/getuserinfo','App\Api\Controllers\Login\LoginController@getUserInfo'); //获取用户信息
    });
    $api->group(['prefix' => 'login','middleware' => ['ClubAdmin']],function ($api){
        $api->post('/editpass','App\Api\Controllers\Login\LoginController@editPass');//修改密码
        $api->post('/logout','App\Api\Controllers\Login\LoginController@logout'); //用户退出
    });
    //教练模块
    $api->get('/coach/export','App\Api\Controllers\Coach\CoachController@export');//教练课程导出

    $api->group(['prefix' => 'coach','middleware' => ['ClubAdmin','Log']],function($api){
        $api->post('/getcoachlist','App\Api\Controllers\Coach\CoachController@getCoachList'); //获取教练列表和筛选
        $api->post('/getcoach','App\Api\Controllers\Coach\CoachController@getCoach'); //获取教练信息
        $api->post('/editcoach','App\Api\Controllers\Coach\CoachController@editCoach');//修改教练信息/英文名/简介/微信
        $api->post('/editcoachpenalty','App\Api\Controllers\Coach\CoachController@editCoachPenalty');//修改教练奖惩
        $api->post('/imageupload','App\Api\Controllers\Coach\CoachController@imageUpload');//图片上传
        $api->post('/videoupload','App\Api\Controllers\Coach\CoachController@videoUpload');//视频上传
        $api->post('/getcoachcourse','App\Api\Controllers\Coach\CoachController@getCoachCourse');//教练课程信息
        $api->post('/delimage','App\Api\Controllers\Coach\CoachController@delImage');//删除图片
        $api->post('/delvideo','App\Api\Controllers\Coach\CoachController@delVideo');//删除视频
    });
    //缴费模块导出
    $api->group(['prefix' => 'pay','namespace' => 'App\Api\Controllers\Pay'],function ($api){
        $api->get('/paymentallexport','PayController@paymentAllExport');//缴费总览导出
        $api->get('/paymentrecordexport','PayController@paymentRecordExport');//某一时间段的缴费记录导出
        $api->get('/refundrecordexport','PayController@refundRecordExport');//某一时间段的退款记录导出
    });

    //缴费模块
    $api->group(['prefix' => 'pay','middleware' => ['ClubAdmin','Log']],function ($api){
        $api->post('/getpayment','App\Api\Controllers\Pay\PayController@getPayment'); //获取缴费列表和筛选
        $api->post('/addpayment','App\Api\Controllers\Pay\PayController@addPayment');//添加缴费方案
        $api->post('/editpayment','App\Api\Controllers\Pay\PayController@editPayment');//修改缴费方案
        $api->post('/editstatus','App\Api\Controllers\Pay\PayController@editStatus');//修改状态
        $api->post('/delpayment','App\Api\Controllers\Pay\PayController@delPayment');//删除缴费方案
        $api->post('/getpaymentall','App\Api\Controllers\Pay\PayController@paymentSituation'); //缴费总览
        $api->post('/getpaymentrecord','App\Api\Controllers\Pay\PayController@getPaymentRecord');//某一时间段的缴费总览
        $api->post('/getrefundrecord','App\Api\Controllers\Pay\PayController@getRefundRecord');//某一时间段的退款记录
        $api->post('/getstudent','App\Api\Controllers\Pay\PayController@getStudent');//学员select
        $api->post('/getPaymentTag','App\Api\Controllers\Pay\PayController@getPaymentTag'); //获取缴费标签
    });

    // 权限模块/tracy
    $api->group(['prefix' => 'permission', 'namespace' => 'App\Api\Controllers\Permission', 'middleware' =>['ClubAdmin','Log']], function ($api) {
        // 员工管理
        $api->post('/addUser', 'UserController@addUser');
        $api->post('/modifyUser', 'UserController@modifyUser');
        $api->post('/userDetail', 'UserController@userDetail');
        $api->post('/modifyUserStatus', 'UserController@modifyUserStatus');
        $api->post('/deleteUser', 'UserController@deleteUser');
        $api->post('/userList', 'UserController@userList');
        $api->post('/checkAccount', 'UserController@checkAccount');
        // 角色管理
        $api->post('/addRole', 'RoleController@addRole');
        $api->post('/modifyRole', 'RoleController@modifyRole');
        $api->post('/modifyRoleStatus', 'RoleController@modifyRoleStatus');
        $api->post('/deleteRole', 'RoleController@deleteRole');
        $api->post('/roleList', 'RoleController@roleList');
        $api->post('/modifyRolePermission', 'RoleController@modifyRolePermission');
        $api->post('/permissionCheckbox', 'RoleController@permissionCheckbox');
        $api->post('/getUserPermission', 'RoleController@getUserPermission');
        // 部门管理
        $api->post('/addDepartment', 'DepartmentController@addDepartment');
        $api->post('/modifyDepartment', 'DepartmentController@modifyDepartment');
        $api->post('/deleteDepartment', 'DepartmentController@deleteDepartment');
        $api->post('/modifyLeader', 'DepartmentController@modifyLeader');
        $api->post('/departmentList', 'DepartmentController@departmentList');
        $api->post('/getDepartmentLeader', 'DepartmentController@getDepartmentLeader');
        // select选择框
        $api->post('/userSelect', 'CommonController@userSelect');
        $api->post('/departmentUserSelect', 'CommonController@departmentUserSelect');
        $api->post('/roleSelect', 'CommonController@roleSelect');
        $api->post('/deptSelect', 'DepartmentController@deptSelect');
        $api->post('/addUserRoleSelect', 'CommonController@addUserRoleSelect');
    });

    // 学员模块-tracy
    $api->group(['prefix' => 'student', 'namespace' => 'App\Api\Controllers\Students', 'middleware' =>['ClubAdmin','Log']], function ($api) {
        // Student
        $api->post('/notFormalStudents', 'StudentController@notFormalStudents');
        $api->post('/formalStudents', 'StudentController@formalStudents');
        $api->post('/studentsFailure', 'StudentController@studentsFailure');
        $api->post('/addStudentCheck', 'StudentController@addStudentCheck');
        $api->post('/addStudent', 'StudentController@addStudent');
        $api->post('/modifyStudentStatus', 'StudentController@modifyStudentStatus');
        $api->post('/modifyStudentMsg', 'StudentController@modifyStudentMsg');
        $api->post('/modifySeller', 'StudentController@modifySeller');
        $api->post('/studentDetail', 'StudentController@studentDetail');
        $api->post('/studentMyApply', 'StudentController@studentMyApply');
        $api->post('/studentLibrary', 'StudentController@studentLibrary');
        $api->post('/applyJoinUnder', 'StudentController@applyJoinUnder');
        $api->post('/studentReview', 'StudentController@studentReview');
        $api->post('/throughOrRefuse', 'StudentController@throughOrRefuse');
        $api->post('/studentBindApp', 'StudentController@studentBindApp');
        $api->post('/studentExamsManage', 'StudentController@studentExamsManage');
        $api->post('/getStudentQrCode', 'StudentController@getStudentQrCode');

        // class
        $api->post('/classList', 'StudentController@classList');
        $api->post('/addClass', 'StudentController@addClass');
        $api->post('/modifyClass', 'StudentController@modifyClass');
        $api->post('/deleteClass', 'StudentController@deleteClass');
        $api->post('/modifyJoinClassTime', 'StudentController@modifyJoinClassTime');

        // freeze
        $api->post('/freezeRecordList', 'StudentController@freezeRecordList');
        $api->post('/freezeModifyRemark', 'StudentController@freezeModifyRemark');

        // common
        $api->post('/paymentTypeSelect', 'CommonController@paymentTypeSelect');
        $api->post('/channelSelectList', 'CommonController@channelSelectList');
        $api->post('/sellerSelectList', 'CommonController@sellerSelectList');
        $api->post('/headTeacherSelectList', 'CommonController@headTeacherSelectList');
        $api->post('/venueSelectList', 'CommonController@venueSelectList');
        $api->post('/classSelectList', 'CommonController@classSelectList');
        $api->post('/courseTicketChoose', 'CommonController@courseTicketChoose');

        //lee
        $api->post('/modifyPostCard', 'StudentController@modifyPostCard');
        $api->post('/historySellerList', 'StudentController@historySellerList');
        $api->post('/reserveRecordList', 'StudentController@reserveRecordList');
        $api->post('/cancelReserve', 'StudentController@cancelReserve');
        $api->post('/signNotesList', 'StudentController@signNotesList');
        $api->post('/paymentReimburse', 'StudentController@paymentReimburse');

        // 非正式学员导入
        $api->any('/studentsImport', 'StudentController@studentsImport');

        // 二期优化
        $api->post('/addStudentFeedback', 'StudentController@addStudentFeedback');
        $api->post('/getStudentFeedback', 'StudentController@getStudentFeedback');
    });

    // 学员管理（不用登录）
    $api->group(['prefix' => 'student', 'namespace' => 'App\Api\Controllers\Students'], function ($api) {
        // 学员预约推广
        $api->post('/studentCodePoster', 'SubscribeController@studentCodePoster');
        $api->post('/subscribeByQrCode', 'SubscribeController@subscribeByQrCode');
        $api->post('/subscribeByApp', 'SubscribeController@subscribeByApp');

        // 学员模板下载
        $api->get('/downloadStudentExcel', 'StudentController@downloadStudentExcel');
        $api->any('/uploadStudentExcel', 'StudentController@uploadStudentExcel');
        $api->any('/studentImportData', 'StudentController@studentImportData');
    });

    // H5学员预约
    $api->group(['prefix' => 'reserve', 'namespace' => 'App\Api\Controllers\Students'], function ($api) {
        $api->any('/getStudentInfo', 'SubscribeController@getStudentInfo');
        $api->any('/addStudentInfo', 'SubscribeController@addStudentInfo');
        $api->any('/getQrCodeUrl', 'SubscribeController@getQrCodeUrl');
        $api->any('/subscribeSuccess', 'SubscribeController@subscribeSuccess');
    });


    // 班级管理/tracy
    $api->group(['prefix' => 'class', 'namespace' => 'App\Api\Controllers\Classes', 'middleware' => ['ClubAdmin','Log']], function ($api) {
        $api->post('/addClass', 'ClassesController@addClass');
        $api->post('/editClassData', 'ClassesController@editClassData');
        $api->post('/editClass', 'ClassesController@editClass');
        $api->post('/classList', 'ClassesController@classList');
        $api->post('/modifyClassStatus', 'ClassesController@modifyClassStatus');
        $api->post('/deleteClass', 'ClassesController@deleteClass');
        $api->post('/classTeacherReport', 'ClassesController@classTeacherReport');
        $api->post('/classProfile', 'ClassesController@classProfile');
        $api->post('/classBasicData', 'ClassesController@classBasicData');
        $api->post('/classShowInApp', 'ClassesController@classShowInApp');
        $api->post('/classSetData', 'ClassesController@classSetData');
        $api->post('/uploadClassImg', 'ClassesController@uploadClassImg');
        $api->post('/classStudentList', 'ClassesController@classStudentList');
        $api->post('/classCourseList', 'ClassesController@classCourseList');
        $api->post('/deleteClassCourse', 'ClassesController@deleteClassCourse');
        $api->post('/addClassCourse', 'ClassesController@addClassCourse');
        $api->post('/addMonthClassCourse', 'ClassesController@addMonthClassCourse');
        $api->post('/stopClassCourse', 'ClassesController@stopClassCourse');
        $api->post('/classStartCourseTime', 'ClassesController@classStartCourseTime');
        $api->post('/thisYearNotStopClass', 'ClassesController@thisYearNotStopClass');
        $api->post('/teacherList', 'ClassesController@teacherList');
        $api->post('/addTeacher', 'ClassesController@addTeacher');
        $api->post('/modifyTeacherStatus', 'ClassesController@modifyTeacherStatus');
        $api->post('/courseSurvey', 'ClassesController@courseSurvey');
        $api->post('/modifyCourseStatus', 'ClassesController@modifyCourseStatus');
        $api->post('/modifyCourseRemark', 'ClassesController@modifyCourseRemark');
        $api->post('/courseStudentList', 'ClassesController@courseStudentList');
        $api->post('/courseStudentSubscribeSign', 'ClassesController@courseStudentSubscribeSign');
        $api->post('/modifyCourseSignStatus', 'ClassesController@modifyCourseSignStatus');
        $api->post('/modifyCourseMvp', 'ClassesController@modifyCourseMvp');
        $api->post('/clearCourseSignStatus', 'ClassesController@clearCourseSignStatus');
        $api->post('/addOutsideStudentSelect', 'ClassesController@addOutsideStudentSelect');
        $api->post('/addOutsideStudent', 'ClassesController@addOutsideStudent');
        $api->post('/modifyCourseSignRemark', 'ClassesController@modifyCourseSignRemark');
        $api->post('/courseCoachList', 'ClassesController@courseCoachList');
        $api->post('/addCourseCoach', 'ClassesController@addCourseCoach');
        $api->post('/modifyCourseCoachStatus', 'ClassesController@modifyCourseCoachStatus');
        $api->post('/modifyCourseCoachFee', 'ClassesController@modifyCourseCoachFee');
        $api->post('/courseEvaluate', 'ClassesController@courseEvaluate');
        $api->post('/uploadClassCourseSignImage', 'ClassesController@uploadClassCourseSignImage');
        $api->post('/getCourseSignImg', 'ClassesController@getCourseSignImg');
        $api->post('/deleteCourseSignImg', 'ClassesController@deleteCourseSignImg');
        $api->post('/modifyCourseCoachComment', 'ClassesController@modifyCourseCoachComment');
        $api->post('/deleteClassImg', 'ClassesController@deleteClassImg');

        // 二期优化
        $api->post('/uploadSickLeaveImg', 'ClassesController@uploadSickLeaveImg');
        $api->post('/getSickLeaveImg', 'ClassesController@getSickLeaveImg');
        $api->post('/deleteSickLeaveImg', 'ClassesController@deleteSickLeaveImg');

        // select
        $api->post('/classTypeSelect', 'CommonController@classTypeSelect');
        $api->post('/paymentSelect', 'CommonController@paymentSelect');
        $api->post('/venueSelectList', 'CommonController@venueSelectList');
        $api->post('/classSelectList', 'CommonController@classSelectList');
        $api->post('/headTeacherSelectList', 'CommonController@headTeacherSelectList');
        $api->post('/coachSelectList', 'CommonController@coachSelectList');

        $api->post('/test', 'ClassesController@test');
    });
    $api->group(['prefix' => 'class', 'namespace' => 'App\Api\Controllers\Classes'], function ($api) {
        $api->get('/signExport', 'ClassesController@signExport');
    });


    // 公共模块
    $api->group(['prefix' => 'common'], function ($api) {

        //俱乐部所在的省市
        $api->any('/province', 'App\Api\Controllers\Common\CommonController@province');
        //俱乐部所在城市
        $api->any('/city', 'App\Api\Controllers\Common\CommonController@city');
        //俱乐部所在的区域
        $api->any('/district', 'App\Api\Controllers\Common\CommonController@district');
        //获取地址的经纬度
        $api->any('/getLatitudeLongitude', 'App\Api\Controllers\Common\CommonController@getLatitudeLongitude');
    });

    $api->group(['prefix'=> 'club'],function ($api){
        //导出
        $api->any('/clubexec', 'App\Api\Controllers\Club\ClubsController@clubexec');
        //俱乐部汇总
        $api->any('/clubSummaryexport', 'App\Api\Controllers\Club\ClubsController@clubSummaryexport');
    });
    // 俱乐部管理
    $api->group(['prefix' => 'club','middleware' => ['ClubAdmin','Log']], function ($api) {
        //俱乐部列表
        $api->any('/clublist', 'App\Api\Controllers\Club\ClubsController@clublist');
        //添加俱乐部
        $api->any('/clubadd', 'App\Api\Controllers\Club\ClubsController@clubadd');
        //修改俱乐部
        $api->any('/clubedit', 'App\Api\Controllers\Club\ClubsController@clubedit');
        //俱乐部品类
        $api->any('/getclass', 'App\Api\Controllers\Club\ClubsController@getclass');
        //俱乐部预览
        $api->any('/clubview', 'App\Api\Controllers\Club\ClubsController@clubview');
        //俱乐部详情
        $api->any('/clubitems', 'App\Api\Controllers\Club\ClubsController@clubitems');

        //俱乐部密码重置
        $api->any('/clubreset', 'App\Api\Controllers\Club\ClubsController@clubreset');
        //俱乐部上下架
        $api->any('/shelves', 'App\Api\Controllers\Club\ClubsController@shelves');
        //俱乐部概况
        $api->any('/clubSurvey', 'App\Api\Controllers\Club\ClubsController@clubSurvey');
        //俱乐部汇总
        $api->any('/clubSummary', 'App\Api\Controllers\Club\ClubsController@clubSummary');


        //概览
        $api->any('/clubStatistics', 'App\Api\Controllers\Club\ClubsController@clubStatistics');


    });


    // 场馆管理
    $api->group(['prefix' => 'venue','middleware' => ['ClubAdmin','Log']], function ($api) {
        //场馆列表
        $api->any('/venuelist', 'App\Api\Controllers\Venue\VenueController@venuelist');
        //场馆添加
        $api->any('/venueadd', 'App\Api\Controllers\Venue\VenueController@venueadd');
        //场馆修改
        $api->any('/editVenue', 'App\Api\Controllers\Venue\VenueController@editVenue');
        //场馆删除
        $api->any('/deleteVenue', 'App\Api\Controllers\Venue\VenueController@deleteVenue');
        //场馆详情
        $api->any('/venueDetail', 'App\Api\Controllers\Venue\VenueController@venueDetail');
        //场馆添加名称验证
        $api->any('/modifyVenueName', 'App\Api\Controllers\Venue\VenueController@modifyVenueName');


        //修改分成比例
        $api->any('/modifySiteDivided', 'App\Api\Controllers\Venue\VenueController@modifySiteDivided');
        //场馆快照
        $api->any('/history', 'App\Api\Controllers\Venue\VenueController@history');
        //所有场馆快照
        $api->any('/venueHistory', 'App\Api\Controllers\Venue\VenueController@venueHistory');

        //班级列表
        $api->any('/classList', 'App\Api\Controllers\Venue\VenueController@classList');
        //历史班级数
        $api->any('/historyClass', 'App\Api\Controllers\Venue\VenueController@historyClass');
        //汇总信息
        $api->any('/summaryInfo', 'App\Api\Controllers\Venue\VenueController@summaryInfo');
        //app是否显示
        $api->any('/appShow', 'App\Api\Controllers\Venue\VenueController@appShow');
        //是否促销
        $api->any('/promotions', 'App\Api\Controllers\Venue\VenueController@promotions');
        //修改交通信息
        $api->any('/modifyTrafficInfo', 'App\Api\Controllers\Venue\VenueController@modifyTrafficInfo');
        //修改描述
        $api->any('/descriptionedit', 'App\Api\Controllers\Venue\VenueController@descriptionedit');
        //修改备注
        $api->any('/remarkedit', 'App\Api\Controllers\Venue\VenueController@remarkedit');

        //失效场馆
        $api->any('/failure', 'App\Api\Controllers\Venue\VenueController@failure');
        //场馆图片列表
        $api->any('/venueImageList', 'App\Api\Controllers\Venue\VenueController@venueImageList');
        //场馆图片新增
        $api->any('/venueImageAdd', 'App\Api\Controllers\Venue\VenueController@venueImageAdd');
        //场馆图片删除
        $api->any('/venueImageDel', 'App\Api\Controllers\Venue\VenueController@venueImageDel');

        //所有场馆快照
        $api->any('/allVenueHistory', 'App\Api\Controllers\Venue\VenueController@allVenueHistory');
        //运动类型选择
        $api->any('/sportTypeSelect', 'App\Api\Controllers\Venue\VenueController@sportTypeSelect');
        //修改运动类型
        $api->any('/modifySportType', 'App\Api\Controllers\Venue\VenueController@modifySportType');

        //场馆select
        $api->any('/venueSelectList', 'App\Api\Controllers\Venue\VenueController@venueSelectList');

    });

    $api->group(['prefix'=> 'sales'],function ($api){

        //12.销售管理-用户--导出
        $api->any('/salesExport', 'App\Api\Controllers\Sales\SalesController@salesCount');
        //13.1销售管理-用户--缴费记录--导出
        $api->any('/salesloglistexport', 'App\Api\Controllers\Sales\SalesController@salesloglistexport');
        //13.3销售管理-用户--退款记录--导出
        $api->any('/refundlistexport', 'App\Api\Controllers\Sales\SalesController@refundlistexport');
    });

    // 销售管理
    $api->group(['prefix' => 'sales','middleware' => ['ClubAdmin','Log']], function ($api) {
        //1.销售管理列表查询
        $api->any('/salesList', 'App\Api\Controllers\Sales\SalesController@salesList');
        //2.销售管理修改操作
        $api->any('/salesEditAction', 'App\Api\Controllers\Sales\SalesController@salesEditAction');
        //3.销售管理修改
        $api->any('/salesEdit', 'App\Api\Controllers\Sales\SalesController@salesEdit');
        //4.销售管理修改
        $api->any('/salesEdit', 'App\Api\Controllers\Sales\SalesController@salesEdit');
        $api->any('/gatAllSales', 'App\Api\Controllers\Sales\SalesController@gatAllSales');

        //5.销售管理-名下学员搜索列表
        $api->any('/salesStudent', 'App\Api\Controllers\Sales\SalesController@salesStudent');
        //6.销售管理-名下学员转移
        $api->any('/salesStudentChange', 'App\Api\Controllers\Sales\SalesController@salesStudentChange');


        //7.销售管理-业绩搜索列表
        $api->any('/salesRecord', 'App\Api\Controllers\Sales\SalesController@salesRecord');
        $api->any('/salesPicStatistics', 'App\Api\Controllers\Sales\SalesController@salesPicStatistics');

        //8.销售管理-业绩-提成详情
        $api->any('/salesExtract', 'App\Api\Controllers\Sales\SalesController@salesExtract');
        $api->any('/salesitems', 'App\Api\Controllers\Sales\SalesTreamController@salesitems');

        //9.销售管理-用户-获取海报
        $api->any('/salesBill', 'App\Api\Controllers\Sales\SalesController@salesBill');
        //10.销售管理-用户-图表
        $api->any('/salesChat', 'App\Api\Controllers\Sales\SalesController@salesChat');
        //11.销售管理-用户--统计列表
        $api->any('/salesCount', 'App\Api\Controllers\Sales\SalesController@salesCount');

        //13.销售管理-用户--缴费记录
        $api->any('/salesloglist', 'App\Api\Controllers\Sales\SalesController@salesloglist');

        //缴费记录-报表
        $api->any('/salesClassChat', 'App\Api\Controllers\Sales\SalesController@salesClassChat');

        //13.2销售管理-用户--退款记录
        $api->any('/refundlist', 'App\Api\Controllers\Sales\SalesController@refundlist');



        //14.销售团队管理列表查询
        $api->any('/treamList', 'App\Api\Controllers\Sales\SalesTreamController@treamList');
        //15.销售团队管理修改操作
        $api->any('/treamEditAction', 'App\Api\Controllers\Sales\SalesTreamController@treamEditAction');
        //16.销售团队管理修改
        $api->any('/treamEdit', 'App\Api\Controllers\Sales\SalesTreamController@treamEdit');

        //17.销售团队管理-销售额详情列表
        $api->any('/treamRecord', 'App\Api\Controllers\Sales\SalesTreamController@treamRecord');
        //18.销售团队管理-业绩--提成详情
        $api->any('/treamExtract', 'App\Api\Controllers\Sales\SalesTreamController@treamExtract');
        //19.销售团队管理-业绩--详情
        $api->any('/treamitems', 'App\Api\Controllers\Sales\SalesTreamController@treamitems');
    });


    // 系统管理
    $api->group(['prefix' => 'system','middleware' => ['ClubAdmin','Log']], function ($api) {
        //    1.测验列表搜索
        $api->any('/examsList', 'App\Api\Controllers\System\SystemController@examsList');
        // 2.测验添加
        $api->any('/examsadd', 'App\Api\Controllers\System\SystemController@examsadd');
        // 3.修改信息显示
        $api->any('/examseditshow', 'App\Api\Controllers\System\SystemController@examseditshow');
        // 4.修改修改
        $api->any('/examsedit', 'App\Api\Controllers\System\SystemController@examsedit');
        //5.测验删除
        $api->any('/examsdel', 'App\Api\Controllers\System\SystemController@examsdel');
        // 6.测验操作提交
        $api->any('/examscheck', 'App\Api\Controllers\System\SystemController@examscheck');
        //6.测验详情
        $api->any('/examsitemslist', 'App\Api\Controllers\System\SystemController@examsitemslist');
        //    7.测验管理添加项目
        $api->any('/examsitemsadd', 'App\Api\Controllers\System\SystemController@examsitemsadd');
        //    8.测验管理修改项目
        $api->any('/examsitemseditshow', 'App\Api\Controllers\System\SystemController@examsitemseditshow');
        //    9.测验管理修改项目
        $api->any('/examsitemsedit', 'App\Api\Controllers\System\SystemController@examsitemsedit');
        //    10.测验管理删除项目
        $api->any('/examsitemsdel', 'App\Api\Controllers\System\SystemController@examsitemsdel');



        //----------测验管理等级
        //    11.测验等级管理添加项目
        $api->any('/examsleveladd', 'App\Api\Controllers\System\SystemController@examsleveladd');
        //    12.测验管理修改项目
        $api->any('/examsleveleditshow', 'App\Api\Controllers\System\SystemController@examsleveleditshow');
        //    13.测验管理修改项目
        $api->any('/examsleveledit', 'App\Api\Controllers\System\SystemController@examsleveledit');
        //    14.测验管理删除项目
        $api->any('/examsleveldel', 'App\Api\Controllers\System\SystemController@examsleveldel');


        //    ---------测验管理添加综合
        //    15.测验等级管理添加项目
        $api->any('/examsgeneraladd', 'App\Api\Controllers\System\SystemController@examsgeneraladd');
        //    16.测验管理修改项目
        $api->any('/examsgeneraleditshow', 'App\Api\Controllers\System\SystemController@examsgeneraleditshow');
        //    17.测验管理修改项目
        $api->any('/examsgeneraledit', 'App\Api\Controllers\System\SystemController@examsgeneraledit');
        //    18.测验管理删除项目
        $api->any('/examsgeneraldel', 'App\Api\Controllers\System\SystemController@examsgeneraldel');


        //添加学员-学员搜索
        $api->any('/addstudentsearch', 'App\Api\Controllers\System\SystemController@addstudentsearch');
        //添加学员--测验
        $api->any('/addstudentexams', 'App\Api\Controllers\System\SystemController@addstudentexams');
        $api->any('/getnoeffectexams', 'App\Api\Controllers\System\SystemController@getnoeffectexams');
        $api->any('/updatestudentcore', 'App\Api\Controllers\System\SystemController@updatestudentcore');

        //23指定测验学员列表--测验
        $api->any('/studentexamslist', 'App\Api\Controllers\System\SystemController@studentexamslist');

        //删除学员--测验
        $api->any('/delstudentexams', 'App\Api\Controllers\System\SystemController@delstudentexams');

        //添加成绩
        $api->any('/addstudentcore', 'App\Api\Controllers\System\SystemController@addstudentcore');

        //查看学员详情--业务待查看
        $api->any('/showstudentitems', 'App\Api\Controllers\System\SystemController@showstudentitems');
        //查看学员详情删除-
        $api->any('/showstudentdel', 'App\Api\Controllers\System\SystemController@showstudentdel');

        //查看学员详情---查看成绩
        $api->any('/showstudentexams', 'App\Api\Controllers\System\SystemController@showstudentexams');


        //######################学员来源#####################
        //来源列表
        $api->any('/channellist', 'App\Api\Controllers\System\SystemController@channellist');
        //来源添加
        $api->any('/channeladd', 'App\Api\Controllers\System\SystemController@channeladd');
        //获取子来源
        $api->any('/getsonchannel', 'App\Api\Controllers\System\SystemController@getsonchannel');
        //来源修改显示
        $api->any('/channeleditshow', 'App\Api\Controllers\System\SystemController@channeleditshow');
        //来源修改
        $api->any('/channeledit', 'App\Api\Controllers\System\SystemController@channeledit');
        //来源删除
        $api->any('/channeldel', 'App\Api\Controllers\System\SystemController@channeldel');


        #########################日志管理###########################
        //日志搜索
        $api->any('/logsearch', 'App\Api\Controllers\System\SystemController@logsearch');

        //#########################教练管理费####################################
        //教练费用搜索显示
        $api->any('/coachlist', 'App\Api\Controllers\System\SystemController@coachlist');
        //教练费用添加
        $api->any('/coachadd', 'App\Api\Controllers\System\SystemController@coachadd');
        //教练费用修改
        $api->any('/coachedit', 'App\Api\Controllers\System\SystemController@coachedit');
        //教练费用删除
        $api->any('/coachdel', 'App\Api\Controllers\System\SystemController@coachdel');


        ############################短信################################
        //1.短信发送列表
        $api->any('/messagelist', 'App\Api\Controllers\System\SystemController@messagelist');
        //1.1短信发送模板
        $api->any('/messageTemplate', 'App\Api\Controllers\System\SystemController@messageTemplate');

        //2.添加短信
        $api->any('/messageadd', 'App\Api\Controllers\System\SystemController@messageadd');
        //3.修改短信显示
        $api->any('/messageeditshow', 'App\Api\Controllers\System\SystemController@messageeditshow');
        //4.修改短信
        $api->any('/messageedit', 'App\Api\Controllers\System\SystemController@messageedit');
        //5.删除短信
        $api->any('/messagedel', 'App\Api\Controllers\System\SystemController@messagedel');
        //6.短信提交审核
        $api->any('/messagecheck', 'App\Api\Controllers\System\SystemController@messagecheck');

        //35.1.1获取所有俱乐部
        $api->any('/getallclub', 'App\Api\Controllers\System\SystemController@getallclub');
        //35.1.1获取所有场馆
        $api->any('/getallvenue', 'App\Api\Controllers\System\SystemController@getallvenue');
        //35.1.1获取俱乐部下场馆
        $api->any('/getvenue', 'App\Api\Controllers\System\SystemController@getvenue');
        //35.1.2获取所有场馆下面班级
        $api->any('/getclass', 'App\Api\Controllers\System\SystemController@getclass');
        //35.1.3获取班级下的学员
        $api->any('/getstudent', 'App\Api\Controllers\System\SystemController@getstudent');


        ########################公告管理############################
        //1.公告发送列表
        $api->any('/noticelist', 'App\Api\Controllers\System\SystemController@noticelist');
        //2.添加公告
        $api->any('/noticeadd', 'App\Api\Controllers\System\SystemController@noticeadd');
        //3.修改公告显示
        $api->any('/noticeeditshow', 'App\Api\Controllers\System\SystemController@noticeeditshow');
        //4.修改公告
        $api->any('/noticeedit', 'App\Api\Controllers\System\SystemController@noticeedit');
        //5.删除公告
        $api->any('/noticedel', 'App\Api\Controllers\System\SystemController@noticedel');
        //6.公告提交审核
        $api->any('/noticecheck', 'App\Api\Controllers\System\SystemController@noticecheck');

    });


    //预约模块 9个
    $api->group(['prefix' => 'subscribe','middleware' => ['ClubAdmin','Log']], function ($api) {
        //全部来源渠道
        $api->any('/allChannel', 'App\Api\Controllers\Subscribe\SubscribeController@allChannel');
        //全部销售员
        $api->any('/allSales', 'App\Api\Controllers\Subscribe\SubscribeController@allSales');
        //预约列表
        $api->any('/subscribeList', 'App\Api\Controllers\Subscribe\SubscribeController@subscribeList');
        //全部场馆
        $api->any('/allVenue', 'App\Api\Controllers\Subscribe\SubscribeController@allVenue');
        //根据场馆获取班级
        $api->any('/getClassByVenue', 'App\Api\Controllers\Subscribe\SubscribeController@getClassByVenue');
        //预约管理
        $api->any('/subscribeManage', 'App\Api\Controllers\Subscribe\SubscribeController@subscribeManage');
        //获取可预约班级
        $api->any('/subscribeClass', 'App\Api\Controllers\Subscribe\SubscribeController@subscribeClass');
        //获取可预约课程
        $api->any('/subscribeCourse', 'App\Api\Controllers\Subscribe\SubscribeController@subscribeCourse');
        //添加预约
        $api->any('/addSubscribe', 'App\Api\Controllers\Subscribe\SubscribeController@addSubscribe');
    });

    //课表模块 17个
    $api->group(['prefix' => 'course','middleware' => ['ClubAdmin','Log']], function ($api) {
        //获取所有的场馆
        $api->any('/allVenue', 'App\Api\Controllers\Course\CourseController@allVenue');
        //获取某个场馆下的所有班级
        $api->any('/getClassByVenue', 'App\Api\Controllers\Course\CourseController@getClassByVenue');
        //课程列表
        $api->any('/courseList', 'App\Api\Controllers\Course\CourseController@courseList');
        //编辑备注
        $api->any('/editRemark', 'App\Api\Controllers\Course\CourseController@editRemark');
        //根据用户所在的俱乐部获取所有的教练
        $api->any('/getCoachByUserClub', 'App\Api\Controllers\Course\CourseController@getCoachByUserClub');
        //添加或修改教练
        $api->any('/handleCoach', 'App\Api\Controllers\Course\CourseController@handleCoach');
        //根据班级及日期获取课程数/已创建数/已停课数
        $api->any('/getCourseTotal', 'App\Api\Controllers\Course\CourseController@getCourseTotal');
        //批量创建课程
        $api->any('/patchCreateCourse', 'App\Api\Controllers\Course\CourseController@patchCreateCourse');
        //批量停课
        $api->any('/patchStopCourse', 'App\Api\Controllers\Course\CourseController@patchStopCourse');
        //课程概况列表汇总
        $api->any('/summaryCourse', 'App\Api\Controllers\Course\CourseController@summaryCourse');
        //获取所有渠道来源
        $api->any('/allChannel', 'App\Api\Controllers\Course\CourseController@allChannel');
        //获取所有销售员
        $api->any('/allSales', 'App\Api\Controllers\Course\CourseController@allSales');
        //课程总统计
        $api->any('/courseTotal', 'App\Api\Controllers\Course\CourseController@courseTotal');
        //教练总统计
        $api->any('/coachTotal', 'App\Api\Controllers\Course\CourseController@coachTotal');
        //预约总统计
        $api->any('/subscribeTotal', 'App\Api\Controllers\Course\CourseController@subscribeTotal');
        //体验总统计
        $api->any('/experienceTotal', 'App\Api\Controllers\Course\CourseController@experienceTotal');
        //出勤总统计
        $api->any('/attendanceTotal', 'App\Api\Controllers\Course\CourseController@attendanceTotal');
    });

    //二维码推广管理
    $api->group(['namespace' => 'App\Api\Controllers\Recommend','middleware' => ['ClubAdmin','Log']], function ($api) {
        $api->group(['prefix' => 'qrcode'],function ($api) {
            $api->post('/recommendList','RecommendController@recommendList');
            $api->post('/reserveList','RecommendController@reserveList');
            $api->post('/getClubReserveSales','RecommendController@getClubReserveSales');
        });

        $api->group(['prefix' => 'reward'], function ($api) {
            $api->post('/rewardList','RecommendController@rewardList');
            $api->post('/rewardCourseSettleList','RecommendController@rewardCourseSettleList');
            $api->post('/rewardModifyHistory','RecommendController@rewardModifyHistory');
            $api->post('/modifyRewardSettle','RecommendController@modifyRewardSettle');
        });
    });

});
