<?php

namespace App\Http\Middleware;

use Closure;
use Tymon\JWTAuth\Facades\JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
class ClubAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        try{
            if(!$user = JWTAuth::parseToken()->authenticate()){
                return response()->json([
                    'code' => 4004,
                    'msg' => '用户不存在'
                ]);
            }
            if(empty($user->club_id)){
                return response()->json([
                    'code' => 4004,
                    'msg' => '缺失俱乐部id'
                ]);
            }
            if($user->user_status == 2){
                return response()->json([
                    'code' => 4004,
                    'msg'  => '该账号已失效'
                ]);
            }
            if($user->is_delete == 1){
                return response()->json([
                    'code' => 4004,
                    'msg'  => '该账号已删除'
                ]);
            }

        } catch (TokenExpiredException $e){
            return response()->json([
                'code' => 4004,
                'msg' => 'token 已过期'
            ]);
        } catch (TokenInvalidException $e){
            return response()->json([
                'code' => 4004,
                'msg' => '无效 token'
            ]);
        }catch (JWTException $e){
            return response()->json([
                'code' => 4004,
                'msg' => 'token 缺失'
            ]);
        }
        $request->offsetSet('user',$user->toArray());
        return $next($request);
    }
}
