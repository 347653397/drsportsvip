<?php

namespace App\Api\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubUser\ClubUser;
use App\Model\Permission\Permission;
use App\Model\Permission\Role;
use App\Model\Permission\RoleMenu;
use App\Model\Permission\RolePermission;
use App\Model\Permission\User;
use function foo\func;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class RoleController extends Controller
{
    /**
     * 添加角色
     * @param Request $request
     * @return array
     */
    public function addRole(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'roleName' => 'required|string',
            'roleDesc' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 角色名称已存在，不能重复添加
        $isAccount = Role::where('role_name', $input['roleName'])
            ->where('club_id', $input['user']['club_id'])
            ->exists();
        if ($isAccount === true) {
            return returnMessage('1105', config('error.permission.1105'));
        }

        $permission = Permission::find(1);

        DB::transaction(function () use ($input, $permission) {
            $role = new Role();
            $role->role_name = $input['roleName'];
            $role->role_desc = $input['roleDesc'];
            $role->club_id = $input['user']['club_id'];
            $role->save();
            $roleId = $role->id;

            $roleMenu = new RoleMenu();
            $roleMenu->club_id = $input['user']['club_id'];
            $roleMenu->role_id = $roleId;
            $roleMenu->menu_id = $permission->id;
            $roleMenu->save();

            $rolePermission = new RolePermission();
            $rolePermission->club_id = $input['user']['club_id'];
            $rolePermission->role_id = $roleId;
            $rolePermission->permission_id = $permission->id;
            $rolePermission->save();
        });

        return returnMessage('200', '');
    }

    /**
     * 修改角色
     * @param Request $request
     * @return array
     */
    public function modifyRole(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'roleId' => 'required|numeric',
            'roleName' => 'required|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 角色不存在，无法修改
        $role = Role::find($input['roleId']);
        if (empty($role)) {
            return returnMessage('1106', config('error.permission.1106'));
        }
        // 角色已存在，无法修改
        $isRole = Role::where('role_name', $input['roleName'])
            ->where('club_id', $input['user']['club_id'])
            ->where('id', '<>', $input['roleId'])
            ->exists();
        if ($isRole === true) {
            return returnMessage('1105', config('error.permission.1105'));
        }

        $role->role_name = $input['roleName'];
        $role->save();
        return returnMessage('200', '');
    }

    /**
     * 修改角色状态
     * @param Request $request
     * @return array
     */
    public function modifyRoleStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'roleId' => 'required|numeric',
            'roleStatus' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 角色不存在，无法修改
        $role = Role::find($input['roleId']);
        if (empty($role)) {
            return returnMessage('1106', config('error.permission.1106'));
        }

        DB::transaction(function () use ($role, $input) {
            $role->is_efficacy = $input['roleStatus'];
            $role->save();

            // 员工失效
            if ($input['roleStatus'] == 2) {
                User::where('club_id', $input['user']['club_id'])
                    ->where('role_id', $input['roleId'])
                    ->update(['user_status' => $input['roleStatus']]);
            }
        });

        // 登录失效的员工强制下线
        if ($input['roleStatus'] == 2) {
            // 强制用户重新登录
            $users = User::where('club_id', $input['user']['club_id'])
                ->where('role_id', $input['roleId'])
                ->get();

            if ($users->isEmpty()) {
                return returnMessage('200', '');
            }

            foreach ($users as $key => $value) {
                $cacheName = \App\Facades\Permission\Permission::getCacheName($value->account);
                $cacheInfo  = Cache::get($cacheName);

                if (empty($cacheInfo)) continue;

                // 清除缓存
                Cache::forget($cacheName);

                // 清除token
                JWTAuth::invalidate($cacheInfo['access_token']);
            }
        }

        return returnMessage('200', '');
    }

    /**
     * 删除角色
     * @param Request $request
     * @return array
     */
    public function deleteRole(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'roleId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 角色不存在
        $role = Role::find($input['roleId']);
        if (empty($role)) {
            return returnMessage('1106', config('error.permission.1106'));
        }

        // 不可删除
        if ($role->is_grant_delete == 1) {
            return returnMessage('1113', config('error.permission.1113'));
        }

        // 存在员工也不可删除
        $roleUser = ClubUser::where('role_id', $input['roleId'])
            ->where('club_id', $input['user']['club_id'])
            ->count();
        if ($roleUser > 0) {
            return returnMessage('1115', config('error.permission.1115'));
        }

        DB::transaction(function () use ($input) {
            Role::where('id', $input['roleId'])->update(['is_delete' => 1]);

            RoleMenu::where('id', $input['roleId'])->update(['is_delete' => 1]);

            RolePermission::where('id', $input['roleId'])->update(['is_delete' => 1]);
        });

        return returnMessage('200', '');
    }

    /**
     * 角色列表
     * @param Request $request
     * @return array
     */
    public function roleList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'searchRoleStatus' => 'nullable|numeric',
            'currentPage' => 'required|numeric',
            'pagePerNum' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $searchRoleStatus = isset($input['searchRoleStatus']) ? $input['searchRoleStatus'] : 0;

        $roleList = Role::with('user')
            ->where(function ($query) use($searchRoleStatus) {
                if (!empty($searchRoleStatus)) {
                    $query->where('is_efficacy', $searchRoleStatus);
                }
            })
            ->where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->orderBy('id', 'asc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $roleList->total();
        $list['result'] = $roleList->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['roleName'] = $items->role_name;
            $arr['roleDesc'] = $items->role_desc;
            $arr['roleUserCount'] = $items->user->count();
            $arr['roleStatus'] = $items->is_efficacy;
            $arr['isGrantDelete'] = $items->is_grant_delete;
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    /**
     * 修改权限
     * @param Request $request
     * @return array
     */
    public function modifyRolePermission(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'roleId' => 'required|numeric',
            'menus' => 'nullable|string',
            'permissions' => 'nullable|string'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        // 菜单
        $menuStr = isset($input['menus']) ? $input['menus'] : '';
        $menuArr = array_unique(explode(',', $menuStr));

        // 权限
        $permissionStr = isset($input['permissions']) ? $input['permissions'] : '';
        $permissionArr = array_unique(explode(',', $permissionStr));

        DB::transaction(function () use ($input, $menuArr, $permissionArr) {
            // 先删除菜单
            RoleMenu::where('role_id', $input['roleId'])
                ->where('club_id', $input['user']['club_id'])
                ->delete();

            // 重新生成菜单
            if (!empty($menuArr)) {
                foreach ($menuArr as $value) {
                    $role = new RoleMenu();
                    $role->club_id = $input['user']['club_id'];
                    $role->role_id = $input['roleId'];
                    $role->menu_id = $value;
                    $role->save();
                }
            }

            // 先删除权限
            RolePermission::where('role_id', $input['roleId'])
                ->where('club_id', $input['user']['club_id'])
                ->delete();

            // 重新生成权限
            if (!empty($permissionArr)) {
                foreach ($permissionArr as $value) {
                    $permission = new RolePermission();
                    $permission->club_id = $input['user']['club_id'];
                    $permission->role_id = $input['roleId'];
                    $permission->permission_id = $value;
                    $permission->save();
                }
            }
        });

        // 强制用户重新登录
        $users = User::where('club_id', $input['user']['club_id'])
            ->where('role_id', $input['roleId'])
            ->get();

        if ($users->isEmpty()) {
            return returnMessage('200', '');
        }

        foreach ($users as $key => $value) {
            $cacheName = \App\Facades\Permission\Permission::getCacheName($value->account);
            $cacheInfo  = Cache::get($cacheName);

            if (empty($cacheInfo)) continue;

            // 清除缓存
            Cache::forget($cacheName);

            // 清除token
            JWTAuth::invalidate($cacheInfo['access_token']);
        }

        return returnMessage('200', '');
    }

    /**
     * 权限checkbox
     * @param Request $request
     * @return array
     */
    public function permissionCheckbox(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'roleId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $role = Role::find($input['roleId']);
        if (empty($role)) {
            return returnMessage('1106', config('error.permission.1106'));
        }

        $arr['roleName'] = $role->role_name;
        $arr['menuCheckBox'] = \App\Facades\Permission\Permission::menu($input['roleId'], $role->club_id);
        $arr['permissionCheckbox'] = \App\Facades\Permission\Permission::permission($input['roleId'], $role->club_id);

        return returnMessage('200', '', $arr);
    }

    /**
     * 当前用户权限
     * @param Request $request
     * @return array
     */
    public function getUserPermission(Request $request)
    {
        $input = $request->all();
        $roleId = $input['user']['role_id'];

        $role = Role::find($roleId);
        if (empty($role)) {
            return returnMessage('1106', config('error.permission.1106'));
        }

        $club = Club::find($input['user']['club_id']);
        if (empty($club)) {
            return returnMessage('1204', config('error.club.1204'));
        }

        $arr['roleName'] = $role->role_name;
        if ($club->parent_id == 0) {
            $arr['isParent'] = 1;
        } else {
            $arr['isParent'] = 0;
        }
        $arr['menuCheckbox'] = \App\Facades\Permission\Permission::menu($roleId, $role->club_id);
        $arr['permissionCheckbox'] = \App\Facades\Permission\Permission::permission($roleId, $role->club_id);

        return returnMessage('200', '', $arr);
    }
}