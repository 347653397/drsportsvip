<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/25
 * Time: 10:51
 */

namespace App\Api\Controllers\Login;


use App\Libraries\Code\Code;
use App\Http\Controllers\Controller;
use App\Model\Club\Club;
use App\Model\ClubUser\ClubUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Facades\JWTAuth;
use Ramsey\Uuid\Uuid;

class LoginController extends Controller
{
    /**
     * 登陆验证
     * @param Request $request
     * @return array
     */
    public function login(Request $request)
    {
        $data = $request->all();
        $validate  = \Validator::make($data,[
            'account'  => 'required|string',
            'password' => 'required|string|between:6,20',
            'code'     => 'required|string',
        ]);
        if ($validate->fails()) {
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $account = strtolower($data['account']);
        $password = $data['password'];
        $code = strtoupper($data['code']);
        $key = (string)$data['codeToken'];
        $reCode = strtoupper(Cache::get($key));

        if ($code == $reCode) {
            $user = ClubUser::with('role')->where('account',$account)->first();

            if (count($user) > 0) {
                if ($user->user_status == 2 || $user->role->is_efficacy == 2) {
                    return returnMessage('1117', config('error.permission.1117'));
                }

                $salt = $user->salt;
                $org_password = $salt.$user->password;
                $password = $salt.md5($password);
                if ($password == $org_password) {
                    $cacheName = $this->getCacheName($account);
                    if (Cache::has($cacheName)) {
                        $cacheInfo  = Cache::get($cacheName);
                        try {
                            JWTAuth::invalidate($cacheInfo['access_token']);
                        }
                        catch (\Exception $exception) {

                        }
                        Cache::forget($this->getCacheName($user->account));
                    }
                    $token = JWTAuth::fromUser($user);
                    Cache::remember($cacheName,$this->getTTL(),function () use ($token){
                        return $this->tokenPayLoad($token);
                    });
                    $user->token = $token;
                    return returnMessage('200','请求成功',$user);
                }
                return returnMessage('102','用户或密码错误，请重新输入');
            }
            return returnMessage('102','用户或密码错误，请重新输入');
        }
        return returnMessage('103','您输的验证码有误');
    }

    //获取缓存名称
    protected function getCacheName($account){
            return 'jwt_'.md5($account);
    }

    //获取过期时间
    protected function getTTL(){
        return config('jwt.ttl');
    }

    //用户token基本信息
    protected function tokenPayLoad($token){
        return [
            'access_token' => $token,
            'token_type'   => 'bearer',
            'expires_in'   => $this->getTTL(),
        ];
    }

    /**
     * 获取登陆成功的用户信息
     */
    public function getUserInfo(Request $request){
        $user = JWTAuth::parseToken()->authenticate();
        $user->clubName = Club::where('id',$user->club_id)->value('name');
        return returnMessage('200','请求成功',$user);

    }


    /**
     * 获取验证码
     */
    public function getCode(Request $request){
            $data = $request->all();
            $key = $data['codeToken'];
            $oldKey = Cache::get('getToken');
            if($key == $oldKey){
                $code = new Code();
                $code->make();
            }
        return returnMessage('501','请求参数错误');
    }

    /**
     * 获取CodeToken
     * @return string
     */
    public function getCodeToken()
    {
        $data = Uuid::uuid4();
        $str = $data->getHex();
        $arr = [
          'codeToken' => $str
        ];

        Cache::put('getToken',$str,10);
        return returnMessage('200','请求成功',$arr);
    }

    /**
     * 修改密码
     * @param Request $request
     * @return array
     */
    public function editPass(Request $request){
        $data = $request->all();
        $validate  = \Validator::make($data,[
            'id'        => 'required|numeric',
            'oldPass'   => 'required|string|between:6,20',
            'password'  => 'required|string|between:6,20',
            'repass'    => 'required|string|between:6,20',
        ]);
        if($validate->fails()){
            $errors = $validate->errors()->toArray();
            foreach ($errors as $error) {
                return returnMessage('1001', $error[0]);
            }
        }

        $user_id = $data['id'];
        $old_pass = $data['oldPass'];
        $password = $data['password'];
        $repass = $data['repass'];

        // 两次密码不一致
        if($repass != $password){
            return returnMessage('104','新密码输入不一致，请重新输入');
        }

        $user = ClubUser::where('id',$user_id)->first();
        $user_salt = $user->salt;
        $user_pass = $user->password;

        // 原密码
        $old_pass = $user_salt.md5($old_pass);
        // 要比较的密码
        $pass =  $user_salt.$user_pass;
        // 原密码是否正确
        if($old_pass != $pass){
          return  returnMessage('105','原始密码错误，请重新输入');

        }
        // 生成新的salt
        $length = 5;
        $arr = array_merge(range('a','z'),range('A','Z'),range(0,9));
        shuffle($arr);
        $new_salt = implode('',array_slice($arr,0,$length));

        $user->salt = $new_salt;
        $user->password = md5($password);
        $user->save();

        // 强制下线
        $cacheName = \App\Facades\Permission\Permission::getCacheName($user->account);
        $cacheInfo  = Cache::get($cacheName);
        // 清除缓存
        Cache::forget($cacheName);
        // 清除token
        JWTAuth::invalidate($cacheInfo['access_token']);

        return  returnMessage('200','密码修改成功，请使用新密码登陆');
    }

    /**
     * 退出
     * @param Request $request
     * @return array
     */
    public function logout(Request $request){
        //获取token
        $token = JWTAuth::getToken();
        //获取用户信息
        $user = JWTAuth::parseToken()->authenticate();

        //清除缓存与token
        Cache::forget($this->getCacheName($user->account));
        JWTAuth::invalidate($token);
        return returnMessage('200','退出成功');
    }
}
