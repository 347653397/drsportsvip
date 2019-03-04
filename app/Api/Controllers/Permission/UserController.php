<?php

namespace App\Api\Controllers\Permission;

use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubCoach\ClubCoach;
use App\Model\ClubSales\ClubSales;
use App\Model\ClubUser\ClubUser;
use App\Model\Permission\Department;
use App\Model\Permission\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth;

class UserController extends Controller
{
    /**
     * 添加员工
     * @param Request $request
     * @return array
     */
    public function addUser(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'account' => 'required|string',
            'password' => 'required|string|between:6,16',
            'name' => 'required|string',
            'phone' => 'required|string',
            'roleId' => 'required|numeric',
            'departmentId' => 'required|numeric',
            'workType' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        // 俱乐部前缀
        $preAccount = Club::where('id', $input['user']['club_id'])->value('pre_account');

        // 账号已存在不允许添加
        $isAccount = User::where('account', $preAccount.strtolower($input['account']))->exists();
        if ($isAccount === true) {
            return returnMessage('1101', config('error.permission.1101'));
        }

        // 姓名已存在不允许添加
        $isName = User::where('username', $input['name'])
            ->where('club_id', $input['user']['club_id'])
            ->exists();
        if ($isName === true) {
            return returnMessage('1102', config('error.permission.1102'));
        }

        // 添加销售或教练
        $deptType = Department::where('id', $input['departmentId'])->value('type');
        DB::transaction(function () use ($input, $deptType, $preAccount) {
            $userId = $this->addClubUser($input, $preAccount);
            if ($deptType == 1) {
                $this->addCoach($input, $userId, $input['user']['club_id']); // 教练
            }
            if ($deptType == 2) {
                $this->addSales($input, $userId, $input['user']['club_id']); // 销售
            }
        });
        return returnMessage('200', '');
    }

    // 添加用户数据
    public function addClubUser($input, $preAccount)
    {
        $insertData['account'] = $preAccount.strtolower($input['account']);
        $insertData['password'] = md5($input['password']);
        $insertData['username'] = $input['name'];
        $insertData['tel'] = $input['phone'];
        $insertData['club_id'] = $input['user']['club_id'];
        $insertData['role_id'] = $input['roleId'];
        $insertData['dept_id'] = $input['departmentId'];
        $insertData['work_type'] = $input['workType'];
        $insertData['dept_name'] = Department::where('id', $input['departmentId'])->value('name');
        $insertData['created_at'] = date('Y-m-d H:i:s', time());
        $insertData['updated_at'] = date('Y-m-d H:i:s', time());
        $userId = ClubUser::insertGetId($insertData);
        return $userId;
    }

    // 添加教练数据
    public function addCoach($input, $userId, $clubId)
    {
        $clubCoach = new ClubCoach();
        $clubCoach->club_id = $clubId;
        $clubCoach->name = $input['name'];
        $clubCoach->user_id = $userId;
        $clubCoach->tel = $input['phone'];
        $clubCoach->type = $input['workType'];
        $clubCoach->status = 1;
        $clubCoach->save();
    }

    // 添加销售数据
    public function addSales($input, $userId, $clubId)
    {
        $clubSales = new ClubSales();
        $clubSales->user_id = $userId;
        $clubSales->club_id = $clubId;
        $clubSales->sales_dept_id = $input['departmentId'];
        $clubSales->sales_name = $input['name'];
        $clubSales->mobile = $input['phone'];
        $clubSales->status = 1;
        $clubSales->save();
    }

    /**
     * 修改员工
     * @param Request $request
     * @return array
     */
    public function modifyUser(Request $request)
    {
        $input = $request->all();
        $clubId = $input['user']['club_id'];
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'password' => 'nullable|string|between:6,20',
            'name' => 'required|string',
            'phone' => 'required|string',
            'roleId' => 'required|numeric',
            'departmentId' => 'required|numeric',
            'workType' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $password = isset($input['password']) ? $input['password'] : "";

        $user = User::find($input['id']);
        if (empty($user)) {
            return returnMessage('1104', config('error.permission.1104'));
        }

        // 姓名已存在不允许添加
        $isName = User::where('username', $input['name'])
            ->where('id', '<>', $input['id'])
            ->where('club_id', $input['user']['club_id'])
            ->exists();
        if ($isName === true) {
            return returnMessage('1116', config('error.permission.1116'));
        }

        // 原部门类型
        $lastDeptType = Department::where('id', $user->dept_id)->value('type');

        // 现部门类型
        $nowDeptType = Department::where('id', $input['departmentId'])->value('type');

        try {
            DB::transaction(function () use ($user, $lastDeptType, $nowDeptType, $input, $clubId) {
                // 教练部员工转移到销售部
                if ($lastDeptType == 1 && $nowDeptType == 2) {
                    ClubCoach::where('user_id', $user->id)
                        ->update(['is_delete' => 1]);
                    $this->addSales($input, $user->id, $clubId);
                }

                // 销售部员工转移到教练部
                if ($lastDeptType == 2 && $nowDeptType == 1) {
                    ClubSales::where('user_id', $user->id)
                        ->update(['is_delete' => 1]);
                    $this->addCoach($input, $user->id, $clubId);
                }

                // 普通部员工转移到教练部
                if (in_array($lastDeptType, [1, 2]) === false && $nowDeptType == 1) {
                    $this->addSales($input, $user->id, $clubId);
                }

                // 普通部员工转移到销售部
                if (in_array($lastDeptType, [1, 2]) === false && $nowDeptType == 2) {
                    $this->addCoach($input, $user->id, $clubId);
                }

                // 教练部员工转移到普通部
                if ($lastDeptType == 1 && in_array($nowDeptType, [1, 2]) === false) {
                    ClubCoach::where('user_id', $user->id)
                        ->update(['is_delete' => 1]);
                }

                // 销售部员工转移到普通部
                if ($lastDeptType == 2 && in_array($nowDeptType, [1, 2]) === false) {
                    ClubSales::where('user_id', $user->id)
                        ->update(['is_delete' => 1]);
                }

                // 销售部员工转移到销售部
                if ($lastDeptType == 2 && $nowDeptType == 2) {
                    ClubSales::where('user_id', $user->id)
                        ->update(['sales_dept_id' => $input['departmentId'], 'sales_name' => $input['name']]);
                }

                // 密码为空时不更新密码字段
                if (!empty($input['password'])) {
                    $user->password = md5($input['password']);
                }
                $user->username = $input['name'];
                $user->tel = $input['phone'];
                $user->role_id = $input['roleId'];
                $user->dept_id = $input['departmentId'];
                $user->dept_name = Department::where('id', $input['departmentId'])->value('name');
                $user->work_type = $input['workType'];
                $user->saveOrFail();
            });
        } catch (\Exception $e) {
            return returnMessage('1110', config('error.permission.1110'));
        }

        // 修改密码强制下线
        if (!empty($password)) {
            $cacheName = \App\Facades\Permission\Permission::getCacheName($user->account);
            $cacheInfo  = Cache::get($cacheName);
            if (!empty($cacheInfo)) {
                // 清除缓存
                Cache::forget($cacheName);
                // 清除token
                JWTAuth::invalidate($cacheInfo['access_token']);
            }
        }

        return returnMessage('200', '');
    }

    /**
     * 员工详情
     * @param Request $request
     * @return array
     */
    public function userDetail(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'userId' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $user = User::find($input['userId']);
        if (empty($user)) {
            return returnMessage('1104', config('error.permission.1104'));
        }

        $arr = [
            'account' => $user->account,
            'username' => $user->username,
            'phone' => $user->phone,
            'roleId' => $user->role_id,
            'departmentId' => $user->dept_id,
            'workType' => $user->work_type
        ];
        return returnMessage('200', '', $arr);
    }

    /**
     * 修改员工状态
     * @param Request $request
     * @return array
     */
    public function modifyUserStatus(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric',
            'userStatus' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $user = User::find($input['id']);
        if (empty($user)) {
            return returnMessage('1104', config('error.permission.1104'));
        }

        $dept = Department::where('id', $user->dept_id)->first();

        try {
            DB::transaction(function () use ($user, $dept, $input) {
                $user->user_status = $input['userStatus'];
                $user->save();

                // 员工生效
                if ($input['userStatus'] == 1) {
                    // 教练生效
                    if ($dept->type == 1) {
                        ClubCoach::where('user_id', $user->id)->update(['status' => 1]);
                    }

                    // 销售生效
                    if ($dept->type == 2) {
                        ClubSales::where('user_id', $user->id)->update(['status' => 1]);
                    }
                }

                // 员工失效
                if ($input['userStatus'] == 2) {
                    // 教练失效
                    if ($dept->type == 1) {
                        ClubCoach::where('user_id', $user->id)->update(['status' => 0]);
                    }

                    // 销售失效
                    if ($dept->type == 2) {
                        ClubSales::where('user_id', $user->id)->update(['status' => 0]);
                    }
                }
            });
        } catch (\Exception $e) {
            return returnMessage('1110', config('error.permission.1110'));
        }

        // 失效账号强制下线
        if ($input['userStatus'] == 2) {
            $cacheName = \App\Facades\Permission\Permission::getCacheName($user->account);
            $cacheInfo  = Cache::get($cacheName);
            if (!empty($cacheInfo)) {
                // 清除缓存
                Cache::forget($cacheName);
                // 清除token
                JWTAuth::invalidate($cacheInfo['access_token']);
            }
        }

        return returnMessage('200', '');
    }

    /**
     * 删除员工
     * @param Request $request
     * @return array
     */
    public function deleteUser(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'id' => 'required|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $user = User::find($input['id']);
        if (empty($user)) {
            return returnMessage('1104', config('error.permission.1104'));
        }

        $dept = Department::where('id', $user->dept_id)->first();

        try {
            DB::transaction(function () use ($user, $dept) {
                $user->delete();

                // 删除教练
                if ($dept->type == 1) {
                    ClubCoach::where('user_id', $user->id)->update(['is_delete' => 1]);
                }

                // 删除销售
                if ($dept->type == 2) {
                    ClubSales::where('user_id', $user->id)->update(['is_delete' => 1]);
                }
            });
        } catch (\Exception $e) {
            return returnMessage('1111', config('error.permission.1111'));
        }
        return returnMessage('200', '');
    }

    /**
     * 用户列表
     * @param Request $request
     * @return array
     */
    public function userList(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'searchVal' => 'nullable|string',
            'pagePerNum' => 'required|numeric',
            'currentPage' => 'required|numeric',
            'searchUserName' => 'nullable|string',
            'searchWorkType' => 'nullable|numeric',
            'searchRole' => 'nullable|numeric',
            'searchDepartment' => 'nullable|numeric',
            'searchUserStatus' => 'nullable|numeric'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }
        $searchVal = isset($input['searchVal']) ? $input['searchVal'] : '';
        $searchUserName = isset($input['searchUserName']) ? $input['searchUserName'] : '';
        $searchWorkType = isset($input['searchWorkType']) ? $input['searchWorkType'] : '';
        $searchRole = isset($input['searchRole']) ? $input['searchRole'] : '';
        $searchDepartment = isset($input['searchDepartment']) ? $input['searchDepartment'] : '';
        $searchUserStatus = isset($input['searchUserStatus']) ? $input['searchUserStatus'] : '';

        $userList = User::with('department', 'role')
            ->where(function ($query) use ($searchVal) {
                if (!empty($searchVal)) {
                    $query->where('id', $searchVal)->orWhere('account', $searchVal);
                }
            })->where(function ($query) use ($searchUserName) {
                if (!empty($searchUserName)) {
                    $query->where('username', $searchUserName);
                }
            })->where(function ($query) use ($searchWorkType) {
                if (!empty($searchWorkType)) {
                    $query->where('work_type', $searchWorkType);
                }
            })->where(function ($query) use ($searchRole) {
                if (!empty($searchRole)) {
                    $query->where('role_id', $searchRole);
                }
            })->where(function ($query) use ($searchDepartment) {
                if (!empty($searchDepartment)) {
                    $query->where('dept_id', $searchDepartment);
                }
            })->where(function ($query) use ($searchUserStatus) {
                if (!empty($searchUserStatus)) {
                    $query->where('user_status', $searchUserStatus);
                }
            })
            ->where('club_id', $input['user']['club_id'])
            ->orderBy('created_at', 'desc')
            ->paginate($input['pagePerNum'], ['*'], 'currentPage', $input['currentPage']);

        $list['totalNum'] = $userList->total();
        $list['result'] = $userList->transform(function ($items) {
            $arr['id'] = $items->id;
            $arr['account'] = isset($items->account) ? $items->account : '';
            $arr['userName'] = isset($items->username) ? $items->username : '';
            $arr['phone'] = isset($items->tel) ? $items->tel : '';
            $arr['roleInfo'] = isset($items->role->role_name) ? $items->role->role_name : '';
            $arr['departmentInfo'] = Department::where('id', $items->dept_id)->value('name');
            $arr['createTime'] = isset($items->created_at) ? $items->created_at->format('Y-m-d H:i:s') : '';
            $arr['userStatus'] = $items->user_status;
            $arr['workType'] = $items->work_type;
            $deptArr=[];
            $arr['deptIds'] = $this->pushDeptIdToArr($deptArr, $items->dept_id);
            return $arr;
        });

        return returnMessage('200', '', $list);
    }

    // 检测账号
    public function checkAccount(Request $request)
    {
        $input = $request->all();
        $validate = Validator::make($input, [
            'checkAccount' => 'required|string'
        ]);
        if ($validate->fails()) {
            return returnMessage('1001', config('error.common.1001'));
        }

        $bool = User::where('account', $input['checkAccount'])->exists();
        $result['isHave'] = $bool;
        return returnMessage('200', '', $result);
    }

    // 通过员工部门id获取所有上级部门
    public function pushDeptIdToArr(&$arr, $deptId)
    {
        array_push($arr, $deptId);

        $parentId = Department::where('id', $deptId)->value('parent_id');

        if ($parentId > 0) {
            $this->pushDeptIdToArr($arr, $parentId);
        }

        return array_reverse($arr);
    }
}
