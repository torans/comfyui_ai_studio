<?php

/**
 * 测试授权过期功能
 * 
 * 运行方式: php test-license-expired.php
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== 授权状态测试 ===\n\n";

$licenseService = app(\App\Services\LicenseService::class);
$info = $licenseService->getLicenseInfo();

echo "当前授权状态:\n";
echo "- 是否已授权: " . ($info['is_licensed'] ? '是' : '否') . "\n";
echo "- 永久授权: " . ($info['has_permanent_license'] ? '是' : '否') . "\n";
echo "- 有效授权码: " . ($info['has_valid_license_key'] ? '是' : '否') . "\n";
echo "- 试用期内: " . ($info['is_in_trial'] ? '是' : '否') . "\n";
echo "- 剩余天数: " . $info['remaining_days'] . "\n";
echo "- 安装日期: " . $info['install_date'] . "\n";
echo "- 到期日期: " . $info['trial_expires'] . "\n\n";

if (!$info['is_licensed']) {
    echo "❌ 系统未授权！\n";
    echo "API接口将返回403错误。\n";
    echo "Web请求将重定向到 /license-expired 页面。\n\n";
    
    echo "如何解锁:\n";
    echo "1. 设置 APP_LICENSE_PERMANENT=true (永久授权)\n";
    echo "2. 设置有效的 APP_LICENSE_KEY\n";
    echo "3. 修改 APP_INSTALL_DATE 延长试用期\n";
} else {
    echo "✅ 系统已授权！\n";
    echo "所有功能正常可用。\n";
    
    if ($info['remaining_days'] <= 7 && $info['remaining_days'] > 0) {
        echo "\n⚠️  警告: 试用期即将结束，剩余 " . $info['remaining_days'] . " 天\n";
    }
}
