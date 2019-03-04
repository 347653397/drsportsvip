<?php

namespace App\Services\Permission;

use App\Model\ClubDepartment\ClubDepartment;
use App\Model\ClubSales\ClubSales;
use App\Model\Permission\Department;
use App\Model\Permission\Permission;
use App\Model\Permission\Role;
use App\Model\Permission\RoleMenu;
use App\Model\Permission\RolePermission;

class PermissionService
{
    // 菜单
    public function menu($roleId, $clubId)
    {
        $permission = Permission::where('parent_id', 0)->get();
        $list = $permission->transform(function ($items) use($roleId, $clubId) {
            $arr['id'] = $items->id;
            $arr['menuName'] = $items->permission_name;
            $arr['checked'] = $this->menuCheck($roleId, $items->id, $clubId);
            return $arr;
        });
        return $list;
    }

    // 权限
    public function permission($roleId, $clubId)
    {
        $permission = Permission::where('parent_id', 0)->get();
        $list = $permission->transform(function ($items) use($roleId, $clubId) {
            $arr['id'] = $items->id;
            $arr['permissionName'] = $items->permission_name;
            $arr['checked'] = $this->permissionCheck($roleId, $items->id, $clubId);
            if ($this->permissionDepth($items->id) === true) {
                $arr['children'] = $this->permissionChild($items->id, $roleId, $clubId);
            }
            return $arr;
        });
        return $list;
    }

    // 权限子集
    public function permissionChild($parentId, $roleId, $clubId)
    {
        $permission = Permission::where('parent_id', $parentId)->get();
        $list = $permission->transform(function ($items) use($roleId, $clubId) {
            $arr['id'] = $items->id;
            $arr['permissionName'] = $items->permission_name;
            $arr['checked'] = $this->permissionCheck($roleId, $items->id, $clubId);
            if ($this->permissionDepth($items->id) === true) {
                $arr['children'] = $this->permissionChild($items->id, $roleId, $clubId);
            }
            return $arr;
        });
        return $list;
    }

    // 菜单是否选中
    public function menuCheck($roleId, $menuId, $clubId)
    {
        return RoleMenu::where('role_id', $roleId)
            ->where('menu_id', $menuId)
            ->where('club_id', $clubId)
            ->exists();
    }

    // 权限是否选中
    public function permissionCheck($roleId, $permissionId, $clubId)
    {
        return RolePermission::where('role_id', $roleId)
            ->where('permission_id', $permissionId)
            ->where('club_id', $clubId)
            ->exists();
    }

    // 权限深度，是否有子集
    public function permissionDepth($id)
    {
        return Permission::where('parent_id', $id)->exists();
    }


    // 获取用户角色类型
    public function getUserRoleType($roleId)
    {
        $role = Role::find($roleId);

        return $role->type;
    }

    // 通过用户id获取销售id
    public function getSalesUserId($userId)
    {
        $sales = ClubSales::where('user_id', $userId)
            ->first();

        if (empty($sales)) {
            return 0;
        }

        return $sales->id;
    }

    // 获取部门负责人
    public function getDepartmentLeader($deptId)
    {
        $department = ClubDepartment::find($deptId);

        $arr = explode(',', $department->principal_id);

        return $arr;
    }

    // 是否是当前部门负责人
    public function isThisDeptLeader($userId, $deptId)
    {
        if ($deptId == 0) {
            return 0;
        }

        $department = ClubDepartment::find($deptId);
        $arr = explode(',', $department->principal_id);

        if (in_array($userId, $arr)) {
            return 1;
        } else {
            return 2;
        }
    }

    // 获取部门及其下面部门
    public function getDepartmentAllId($departArr, $deptId)
    {
        array_push($departArr,$deptId);

        $childDept = Department::where('parent_id', $deptId)
            ->where('is_delete', 0)
            ->get();
        if (!$childDept->isEmpty()) {
            foreach ($childDept as $value) {
                $departArr = $this->getDepartmentAllId($departArr, $value->id);
            }
        }

        return $departArr;
    }

    // 获取用户缓存token名称
    public function getCacheName($account)
    {
        return 'jwt_'.md5($account);
    }
}