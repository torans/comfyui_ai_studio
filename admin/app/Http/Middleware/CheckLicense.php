<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        // 排除授权过期页面和登录相关页面
        $uri = $request->path();
        if (in_array($uri, ['license-expired', 'login', 'logout', 'register']) || str_starts_with($uri, 'password/') || str_starts_with($uri, 'verification/')) {
            return $next($request);
        }

        // 检查是否过期
        $expires = config('license.expires');
        if ($expires && now()->gt(\Carbon\Carbon::parse($expires))) {
            if ($request->expectsJson()) {
                return response()->json(['error' => '系统授权已过期'], 403);
            }
            return redirect('/license-expired');
        }

        return $next($request);
    }
}
