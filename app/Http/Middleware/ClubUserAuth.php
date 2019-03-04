<?php

namespace App\Http\Middleware;

use App\Model\Permission\Permission;
use App\Model\Permission\RolePermission;
use Closure;

class ClubUserAuth
{
    /**
     * 验证用户访问接口权限
     * @param $request
     * @param Closure $next
     * @return array|mixed
     */
    public function handle ($request, Closure $next)
    {
        // 当前用户信息
        $param = $request->input();
        $user = $param['user'];

        // 当前路由
        $routeArr = request()->route()->getAction();
        $urlArr = explode('/', $routeArr['uri']);
        $route = $urlArr[2] . '/' . $urlArr[3];

        // 当前路由权限
        $permission = Permission::where('permission_route', $route)->first();
        if (empty($permission)) {
            return response()->json([
                'code' => 4004,
                'msg' => '权限不存在'
            ]);
        }

        // 检测是否存在
        $bool = RolePermission::where('club_id', $user['id'])
            ->where('permission_id', $permission->id)
            ->where('role_id', $user['role_id'])
            ->exists();
        if ($bool === false) {
            return response()->json([
                'code' => 4004,
                'msg' => '暂无权限，请联系管理员'
            ]);
        }

        return $next($request);
    }
}