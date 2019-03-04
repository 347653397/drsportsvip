<?php

namespace App\Http;

use App\Http\Middleware\ClubAdmin;
use App\Http\Middleware\ClubUserAuth;
use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    /**
     * The application's global HTTP middleware stack.
     *
     * These middleware are run during every request to your application.
     *
     * @var array
     */
    protected $middleware = [
        \Illuminate\Foundation\Http\Middleware\CheckForMaintenanceMode::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        \App\Http\Middleware\TrustProxies::class,
    ];

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            // \Illuminate\Session\Middleware\AuthenticateSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            'throttle:60,1',
            'bindings',
        ],
    ];

    /**
     * The application's route middleware.
     *
     * These middleware may be assigned to groups or used individually.
     *
     * @var array
     */
    protected $routeMiddleware = [
        'auth' => \Illuminate\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'bindings' => \Illuminate\Routing\Middleware\SubstituteBindings::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,

        'jwt.auth' => \Tymon\JWTAuth\Middleware\GetUserFromToken::class, //用于在请求头和参数中检查是否包含token，并尝试对其解码
        'jwt.refresh' => \Tymon\JWTAuth\Middleware\RefreshToken::class, //再次从请求中解析token，并顺序刷新token（同时废弃老的token）并将其作为下一个响应的一部分
        'ClubAdmin' => \App\Http\Middleware\ClubAdmin::class, //俱乐部后台身份认证
        'Log' => \App\Http\Middleware\OperationLog::class, //俱乐部后台操作日志
        'CrossHttp' => \App\Http\Middleware\CrossHttp::class, // 允许接口跨域访问
        'ClubUserAuth' => \App\Http\Middleware\ClubUserAuth::class // 验证接口访问权限
    ];
}
