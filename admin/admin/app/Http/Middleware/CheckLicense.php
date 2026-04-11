<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * 授权检查中间件
 * 
 * 检查系统是否已授权，未授权则阻止访问API
 */
class CheckLicense
{
    protected $licenseService;

    public function __construct(LicenseService $licenseService)
    {
        $this->licenseService = $licenseService;
    }

    /**
     * 处理请求
     */
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // 检查是否是排除的路由
        if ($this->isExceptedRoute($request)) {
            return $next($request);
        }

        // 检查授权状态
        if (!$this->licenseService->isLicensed()) {
            // 记录未授权访问
            $this->licenseService->logLicenseStatus();

            // 返回403错误
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => '系统授权已过期',
                    'message' => '请联系供应商获取授权',
                    'code' => 'LICENSE_EXPIRED',
                    'remaining_days' => 0,
                ], 403);
            }

            // Web请求重定向到授权过期页面
            return redirect()->route('license.expired');
        }

        // 授权有效，继续处理请求
        return $next($request);
    }

    /**
     * 检查是否是排除的路由
     */
    protected function isExceptedRoute(Request $request): bool
    {
        $currentUri = $request->path();
        $currentRouteName = $request->route()?->getName();
        
        // 直接检查URI - 授权过期页面和登录相关页面
        $excludedUris = [
            'license-expired',
            'login',
            'logout',
            'register',
        ];
        
        if (in_array($currentUri, $excludedUris)) {
            return true;
        }
        
        // 检查URI是否以特定前缀开头
        $excludedPrefixes = ['password/', 'verification/'];
        foreach ($excludedPrefixes as $prefix) {
            if (str_starts_with($currentUri, $prefix)) {
                return true;
            }
        }

        // 如果没有路由名称，返回false
        if (!$currentRouteName) {
            return false;
        }

        // 检查配置中的排除路由
        $exceptRoutes = config('license.except_routes', []);
        
        foreach ($exceptRoutes as $exceptRoute) {
            // 支持通配符匹配
            if (str_contains($exceptRoute, '*')) {
                $pattern = str_replace('*', '.*', $exceptRoute);
                if (preg_match("/^{$pattern}$/", $currentRouteName)) {
                    return true;
                }
            } elseif ($currentRouteName === $exceptRoute) {
                return true;
            }
        }

        return false;
    }
}
