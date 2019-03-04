<?php

namespace App\Api\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Model\Permission\Department;
use App\Model\Permission\Role;
use App\Model\Permission\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CommonController extends Controller
{
    // 员工select
    public function userSelect(Request $request)
    {
        $input = $request->all();
        $user = User::where('club_id', $input['user']['club_id'])
            ->where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->get();
        $result = $user->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['userName'] = $items->username;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    // 部门员工select
    public function departmentUserSelect(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'departmentId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $department = Department::find($input['departmentId']);
        if (empty($department)) {
            return returnMessage('1107', config('error.permission.1107'));
        }

        $leader = explode(',', $department->principal_id);

        $user = User::where('dept_id', $input['departmentId'])->where('is_delete', 0)->get();
        $result = $user->transform(function ($items) use ($leader) {
            $arr['id'] = $items->id;
            $arr['userName'] = $items->username;
            if (in_array($items->id, $leader)) {
                $arr['isChecked'] = true;
            } else {
                $arr['isChecked'] = false;
            }
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    // 筛选角色select
    public function roleSelect(Request $request)
    {
        $input = $request->all();
        $role = Role::where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->get();
        $result = $role->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['roleName'] = $items->role_name;
            return $arr;
        });
        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    // 添加员工-角色select
    public function addUserRoleSelect(Request $request)
    {
        $input = $request->all();
        $role = Role::where('club_id', $input['user']['club_id'])
            ->where('is_efficacy', 1)
            ->where('is_delete', 0)
            ->where('is_admin', 0)
            ->get();

        $result = $role->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['roleName'] = $items->role_name;
            return $arr;
        });
        $list['result'] = $result;

        return returnMessage('200', '', $list);
    }
}