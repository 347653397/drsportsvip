<?php

namespace App\Console\Commands;

use App\Facades\Util\Log;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClass\ClubClassTime;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubDepartment\ClubDepartment;
use App\Model\ClubPayment\ClubPayment;
use App\Model\ClubRole\ClubRole;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubStudent\ClubStudent;
use App\Model\ClubUser\ClubUser;
use App\Model\ClubVenue\ClubVenue;
use App\Model\Permission\Permission;
use App\Model\Permission\Role;
use App\Model\Permission\RoleMenu;
use App\Model\Permission\RolePermission;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProductDefaultData extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'ProductDefaultData';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'product club admin default data';

    /**
     * 生成俱乐部默认数据
     * @return string
     * @throws \Exception
     */
    public function handle()
    {
        $allClubId = Club::all();

        DB::transaction(function () use ($allClubId) {
            foreach ($allClubId as $key => $value) {
                // 默认场馆
                $defaultVenue = ClubVenue::where('club_id', $value->id)
                    ->where('name', '默认场馆')
                    ->first();
                if (empty($defaultVenue)) {
                    $venue = new ClubVenue();
                    $venue->name = '默认场馆';
                    $venue->club_id = $value->id;
                    $venue->province = $value->province_name;
                    $venue->city = $value->city_name;
                    $venue->district = $value->district_name;
                    $venue->province_id = $value->province_id;
                    $venue->city_id = $value->city_id;
                    $venue->district_id = $value->district_id;
                    $venue->price_in_app = 300;
                    $venue->status = 1;
                    $venue->class_count = 1;
                    $venue->effect_class_count = 1;
                    $defaultVenue = $venue->saveOrFail();
                }

                // 默认销售
                $defaultsales = ClubSales::where('club_id', $value->id)
                    ->where('sales_name', '默认销售')
                    ->first();
                if (empty($defaultsales)) {
                    $salesUser = new ClubUser();
                    $salesUser->tel = $value->mobile;
                    $salesUser->account = strtolower($value->pre_account).'defaultsales'.$value->id;
                    $salesUser->username = '默认销售';
                    $salesUser->password = md5(123456);
//                        $salesUser->role_id = $salesRoleId;
                    $salesUser->club_id = $value->id;
//                        $salesUser->dept_id = $salesDeptId;
                    $salesUser->dept_name = '销售部';
                    $salesUser->save();
                    $salesUserId = $salesUser->id;
                    // 添加默认销售用户
                    $sales = new ClubSales();
                    $sales->club_id = $value->id;
                    $sales->user_id = $salesUserId;
//                        $sales->sales_dept_id = $salesDeptId;
                    $sales->sales_name = '默认销售';
                    $sales->mobile = $value->mobile;
                    $sales->status = 1;
                    $defaultsales = $sales->save();
                }

                // 默认班级
                $defaultClass = ClubClass::where('club_id', $value->id)
                    ->where('name', '默认班级')
                    ->first();
                if (empty($defaultClass)) {
                    $class = new ClubClass();
                    $class->name = '默认班级';
                    $class->club_id = $value->id;
                    $class->type = 1;
                    $class->pay_tag_name = '免费体验';
//                    $class->venue_id = $defaultVenue->id;
                    $class->venue_name = '默认场馆';
                    $class->student_limit = 100;
//                    $class->teacher_id = $defaultsales->id;
                    $class->status = 1;
                    $classRes = $class->save();
                    $classId = $class->id;
                    // 班级时间
                    $classTime = new ClubClassTime();
                    $classTime->class_id = $classId;
                    $classTime->day = 1;
                    $classTime->start_time = '09:00:00';
                    $classTime->end_time = '10:00:00';
                    $classTimeRes = $classTime->save();
                }

                // 添加体验缴费方案
                $freePayment = ClubPayment::where('club_id', $value->id)
                    ->where('name', '体验缴费')
                    ->where('tag', 1)
                    ->where('is_free', 1)
                    ->where('is_default', 1)
                    ->first();
                if (empty($freePayment)) {
                    $payment = new ClubPayment();
                    $payment->club_id = $value->id; //俱乐部id
                    $payment->name = "体验缴费";//缴费方案名称
                    $payment->payment_tag = '体验缴费';
                    $payment->type = 1; //缴费方案所适用的班级
                    $payment->tag = 1; //缴费方案类型
                    $payment->price = 0;//价格
                    $payment->original_price = 300;//原价
                    $payment->min_price = 0;//底价
                    $payment->course_count = 1;//课时数
                    $payment->use_to_student_type = 1;//适用学员
                    $payment->private_leave_count = 0;//事假数
                    $payment->show_in_app = 0;//是否在App显示
                    $payment->limit_to_buy = 0;//是否限购
                    $payment->is_free = 1;
                    $payment->is_default = 1;
                    $payment->status = 1; //有效
                    $payres = $payment->save();
                }
            }
        });

        return "success";
    }
}


