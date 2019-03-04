<?php

namespace App\Api\Controllers\Permission;

use App\Facades\Permission\Permission;
use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubUser\ClubUser;
use App\Model\Permission\Department;
use App\Model\Permission\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class DepartmentController extends Controller
{
    /**
     * 新增部门
     * @param Request $request
     * @return array
     */
    public function addDepartment(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'departmentName' => 'required|string',
            'superiorId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 部门名称不能相同
        $isDepartment = Department::where('name', $input['departmentName'])
            ->where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->exists();
        if ($isDepartment === true) {
            return returnMessage('1107', config('error.permission.1107'));
        }

        // 添加教练、或销售的子部门
        $parentDeptType = Department::where('id', $input['superiorId'])
            ->where('club_id', $input['user']['club_id'])
            ->value('type');

        // 添加教练子部门
        if ($parentDeptType != 0) {
            $department = new Department();
            $department->name = $input['departmentName'];
            $department->type = $parentDeptType;
            $department->parent_id = $input['superiorId'];
            $department->club_id = $input['user']['club_id'];
            $department->save();
            return returnMessage('200', '');
        }

        $department = new Department();
        $department->name = $input['departmentName'];
        $department->parent_id = $input['superiorId'];
        $department->club_id = $input['user']['club_id'];
        $department->save();
        return returnMessage('200', '');
    }

    /**
     * 修改部门
     * @param Request $request
     * @return array
     */
    public function modifyDepartment(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'departmentName' => 'required|string',
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 部门不存在
        $department = Department::find($input['id']);
        if (empty($department)) {
            return returnMessage('1108', config('error.permission.1108'));
        }
        // 同一俱乐部下面，部门名称不能相同
        $isDepartment = Department::where('name', $input['departmentName'])
            ->where('club_id', $input['user']['club_id'])
            ->where('id', '<>', $input['id'])
            ->where('is_delete', 0)
            ->exists();
        if ($isDepartment === true) {
            return returnMessage('1107', config('error.permission.1107'));
        }

        try {
            $department->name = $input['departmentName'];
            $department->save();
        } catch (\Exception $e) {
            return returnMessage('1109', config('error.permission.1109'));
        }
        return returnMessage('200', '');
    }

    /**
     * 删除部门
     * @param Request $request
     * @return array
     */
    public function deleteDepartment(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 部门不存在
        $department = Department::find($input['id']);
        if (empty($department)) {
            return returnMessage('1108', config('error.permission.1108'));
        }
        // 不可删除
        if ($department->is_grant_delete == 1) {
            return returnMessage('1113', config('error.permission.1113'));
        }
        // 部门下存在员工不可删除
        $deptUser = ClubUser::where('dept_id', $input['id'])->count();
        if ($deptUser > 0) {
            return returnMessage('1114', config('error.permission.1114'));
        }

        $departArr = [];
        $deptArr = Permission::getDepartmentAllId($departArr, $input['id']);

        try {
            DB::transaction(function () use ($deptArr) {
                foreach ($deptArr as $value) {
                    Department::where('id', $value)->update(['is_delete' => 1]);
                }
            });
        } catch (\Exception $e) {
            return returnMessage('1111', config('error.permission.1111'));
        }
        return returnMessage('200', '');
    }

    /**
     * 修改部门负责人
     * @param Request $request
     * @return array
     */
    public function modifyLeader(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'leaders' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 部门不存在
        $department = Department::find($input['id']);
        if (empty($department)) {
            return returnMessage('1108', config('error.permission.1108'));
        }

        try {
            $department->principal_id = $input['leaders'];
            $department->save();
        } catch (\Exception $e) {
            return returnMessage('1111', config('error.permission.1111'));
        }
        return returnMessage('200', '');
    }

    /**
     * 部门列表
     * @param Request $request
     * @return array
     */
    public function departmentList(Request $request)
    {
        $input = $request->all();
        $firstDept = Department::where('parent_id', 0)
            ->where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->get();

        $result = $firstDept->transform(function ($items) use ($input) {
            $arr['id'] = $items->id;
            $arr['departmentName'] = $items->name;
            $arr['departmentUserCont'] = $this->departmentUserCount($items->id);
            $arr['departmentLeader'] = $this->departmentLeader($items->principal_id);
            $arr['isGrantDelete'] = $items->is_grant_delete;
            if ($this->departmentDepth($items->id) === true) {
                $arr['children'] = $this->departmentChild($items->id);
            }
            return $arr;
        });

        $list['result'] = $result;
        return returnMessage('200', '', $list);
    }

    // 部门人数
    public function departmentUserCount($deptId, $userCurrCount = 0)
    {
        $deptArr = [];
        $deptArr = self::getDepartmentAllId($deptArr,$deptId);

        if (count($deptArr) > 0) {
            $userCurrCount = User::whereIn('dept_id', $deptArr)->count();
        }

        return $userCurrCount;
    }

    // 对应部门id
    public function getDepartmentAllId($departArr,$deptId)
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

    // 部门领导人,存在多个逗号隔开
    public function departmentLeader($principalId)
    {
        if (strpos($principalId, ',') === false) {
            return User::where('id', $principalId)->value('username');
        }
        $leaders = explode(',', $principalId);
        $leader = '';
        foreach ($leaders as $key => $value) {
            if ($key == count($leaders)-1) {
                $leader .= User::where('id', $value)->value('username');
            } else {
                $leader .= User::where('id', $value)->value('username').',';
            }
        }
        return $leader;
    }

    // 部门下的子部门
    public function departmentChild($parentId)
    {
        $result = Department::where('parent_id', $parentId)
            ->where('is_delete', 0)
            ->get();
        $list = $result->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['departmentName'] = $items->name;
            $arr['departmentUserCont'] = $this->departmentUserCount($items->id);
            $arr['departmentLeader'] = $this->departmentLeader($items->principal_id);
            $arr['isGrantDelete'] = $items->is_grant_delete;
            if ($this->departmentDepth($items->id) === true) {
                $arr['children'] = $this->departmentChild($items->id);
            }
            return $arr;
        });
        return $list;
    }

    // 部门深度，是否有子集
    public function departmentDepth($id)
    {
        return Department::where('parent_id', $id)->where('is_delete', 0)->exists();
    }

    // 部门下的子部门
    public function departmentChildSelect($parentId)
    {
        $result = Department::where('parent_id', $parentId)->where('is_delete', 0)->get();
        $list = $result->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['departmentName'] = $items->name;
            $arr['isGrantDelete'] = $items->is_grant_delete;
            if ($this->departmentDepth($items->id) === true) {
                $arr['children'] = $this->departmentChildSelect($items->id);
            }
            return $arr;
        });
        return $list;
    }

    /**
     * 部门select
     * @param Request $request
     * @return array
     */
    public function deptSelect(Request $request)
    {
        $input = $request->all();
        $firstDept = Department::where('parent_id', 0)
            ->where('club_id', $input['user']['club_id'])
            ->where('is_delete', 0)
            ->get();

        $deptList = $firstDept->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['departmentName'] = $items->name;
            if ($this->departmentDepth($items->id) === true) {
                $arr['children'] = $this->departmentChildSelect($items->id);
            }
            return $arr;
        });
        $list['result'] = $deptList;
        return returnMessage('200', '', $list);
    }

    /**
     * 获取部门负责人
     * @param Request $request
     * @return array
     */
    public function getDepartmentLeader(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $dept = Department::find($input['id']);
        if (empty($dept)) {
            return returnMessage('1107', config('error.permission.1107'));
        }

        if (empty($dept->principal_id)) {
            return returnMessage('200', '');
        }

        $leader = explode(',', $dept->principal_id);

        $list = [];
        foreach ($leader as $key => $value) {
            $list[$key]['userId'] = $value;
            $list[$key]['userName'] = User::where('id', $value)->value('username');
        }

        return returnMessage('200', '', $list);
    }
}
