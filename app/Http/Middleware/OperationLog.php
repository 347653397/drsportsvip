<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/15
 * Time: 11:29
 */

namespace App\Http\Middleware;
use App\Model\ClubOperationLog\ClubOperationLog;
use Closure;

use Tymon\JWTAuth\Facades\JWTAuth;
class OperationLog
{
    public function handle($request, Closure $next)
    {
        $param = $request->input();

        $url = url()->full();

        $route = request()->route()->getAction();
        $urlArr  = explode('/',$route['uri']);

        $menuId = [
            'login' => 0,
            'student' => 2,
            'course' => 3,
            'class' => 4,
            'venue' => 5,
            'subscribe' => 6,
            'pay' => 7,
            'sales' => 8,
            'coach' => 9,
            'permission'=> 10,
            'club'=> 11,
            'system'=> 12,
            'qrcode' => 13,
            'reward' => 14
        ];

        if(empty($menuId[$urlArr[2]])){
            return response()->json([
                'code' => '404',
                'msg' => '404'
            ]);
        }
        $arr = [
          'operation_user_id' => $param['user']['id'],
          'operation_user_name' => $param['user']['username'],
          'module_id' => $menuId[$urlArr[2]],
          'operation_content' => json_encode($param),
          'operation_desc' => $url
        ];
        try{
            ClubOperationLog::create($arr);
        }catch (\Exception $e){
            return response()->json([
               'code' => '500',
                'msg' => '服务器繁忙'
            ]);
        }
        return $next($request);
    }
}