<?php

namespace App\Console\Commands;

use App\Facades\Util\Log;
use App\Model\Club\Club;
use App\Model\ClubClass\ClubClass;
use App\Model\ClubClassStudent\ClubClassStudent;
use App\Model\ClubCourse\ClubCourse;
use App\Model\ClubCourse\ClubCourseSign;
use App\Model\ClubCourseTickets\ClubCourseTickets;
use App\Model\ClubStudent\ClubStudent;
use App\Model\Permission\Permission;
use App\Model\Permission\Role;
use App\Model\Permission\RoleMenu;
use App\Model\Permission\RolePermission;
use App\Model\Permission\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ProductPermission extends Command
{
    /**
     * The name and signature of the console command.
     * @var string
     */
    protected $signature = 'ProductPermission';

    /**
     * The console command description.
     * @var string
     */
    protected $description = 'product club admin permission';

    /**
     * 生成管理角色权限
     * @return string
     * @throws \Exception
     */
    public function handle()
    {
        // 查询所有管理员角色
        $roles = Role::where('is_admin', 1)->get();
        Log::setGroup('ProductDataError')->error('角色', ['roles' => $roles]);

        // 纠正管理员role_id
        foreach ($roles as $key => $value) {
            User::where('club_id', $value->club_id)
                ->where('username', '超级管理员')
                ->update(['role_id' => $value->id]);
        }

        // 菜单
        $menus = Permission::where('parent_id', 0)->get();
        Log::setGroup('ProductDataError')->error('菜单', ['menus' => $menus]);

        // 权限
        $permissions = Permission::all();
        Log::setGroup('ProductDataError')->error('权限', ['roles' => $permissions]);

        try {
            DB::transaction(function () use ($roles, $menus, $permissions) {
                // 清除表菜单权限表
                RoleMenu::truncate();
                RolePermission::truncate();

                foreach ($roles as $key => $value) {
                    // 过滤掉已下架和已删除的俱乐部
                    $club = Club::find($value->club_id);
                    if ($club->status == 0 || $club->is_delete == 1) continue;

                    // 添加菜单
                    foreach ($menus as $k => $v) {
                        $roleMenu = new RoleMenu();
                        $roleMenu->club_id = $value->club_id;
                        $roleMenu->role_id = $value->id;
                        $roleMenu->menu_id = $v->id;
                        $roleMenu->saveOrFail();
                    }

                    // 添加权限
                    foreach ($permissions as $k => $v) {
                        $rolePermission = new RolePermission();
                        $rolePermission->club_id = $value->club_id;
                        $rolePermission->role_id = $value->id;
                        $rolePermission->permission_id = $v->id;
                        $rolePermission->saveOrFail();
                    }
                }
            });
        } catch (\Exception $e) {
            return "error";
        }

        return "success";
    }
}


