<?php

namespace App\Console\Commands;

use App\Services\ComfyUi\ComfyUiClient;
use App\Services\ComfyUi\ErrorHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckComfyUiHealth extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'comfyui:health-check 
                            {--fix : 尝试自动修复问题}
                            {--detailed : 显示详细诊断信息}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '检查 ComfyUI 服务器健康状态';

    /**
     * Execute the console command.
     */
    public function handle(ComfyUiClient $client, ErrorHandler $errorHandler)
    {
        $this->info('🔍 开始检查 ComfyUI 服务器健康状态...');
        
        $healthStatus = $errorHandler->getHealthStatus();
        $this->displayHealthStatus($healthStatus);
        
        // 执行连接测试
        $connectionTest = $this->testConnection($client);
        $this->displayConnectionTest($connectionTest);
        
        // 检查队列状态
        $queueStatus = $this->checkQueueStatus($client);
        $this->displayQueueStatus($queueStatus);
        
        // 检查系统配置
        $configStatus = $this->checkConfiguration();
        $this->displayConfigStatus($configStatus);
        
        // 总体评估
        $this->displayOverallAssessment($healthStatus, $connectionTest, $queueStatus, $configStatus);
        
        // 如果启用修复选项
        if ($this->option('fix')) {
            $this->attemptAutoFix($connectionTest, $queueStatus, $configStatus);
        }
        
        return 0;
    }
    
    /**
     * 显示健康状态
     */
    private function displayHealthStatus(array $status): void
    {
        $this->newLine();
        $this->info('📊 健康统计:');
        
        $this->table(
            ['指标', '值', '状态'],
            [
                ['总请求数', $status['total_requests'], ''],
                ['总错误数', $status['total_errors'], ''],
                ['错误率', $status['error_rate'] . '%', $this->getRateStatus($status['error_rate'])],
                ['系统状态', $status['status'], $this->getStatusBadge($status['status'])],
                ['最后检查', $status['last_check'], ''],
            ]
        );
    }
    
    /**
     * 测试连接
     */
    private function testConnection(ComfyUiClient $client): array
    {
        $startTime = microtime(true);
        
        try {
            // 测试基础连接
            $response = $client->fetchQueue();
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            return [
                'success' => true,
                'response_time_ms' => $responseTime,
                'queue_running' => count($response['queue_running'] ?? []),
                'queue_pending' => count($response['queue_pending'] ?? []),
                'message' => '连接成功',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'error' => $e->getMessage(),
                'message' => '连接失败',
            ];
        }
    }
    
    /**
     * 显示连接测试结果
     */
    private function displayConnectionTest(array $test): void
    {
        $this->newLine();
        $this->info('🔗 连接测试:');
        
        if ($test['success']) {
            $this->line('✅ ' . $test['message']);
            $this->line("   响应时间: {$test['response_time_ms']}ms");
            $this->line("   运行中任务: {$test['queue_running']}");
            $this->line("   等待中任务: {$test['queue_pending']}");
        } else {
            $this->error('❌ ' . $test['message']);
            $this->line("   错误信息: {$test['error']}");
            $this->line("   响应时间: {$test['response_time_ms']}ms (超时前)");
        }
    }
    
    /**
     * 检查队列状态
     */
    private function checkQueueStatus(ComfyUiClient $client): array
    {
        try {
            $queue = $client->fetchQueue();
            
            $running = count($queue['queue_running'] ?? []);
            $pending = count($queue['queue_pending'] ?? []);
            $total = $running + $pending;
            
            $status = 'healthy';
            if ($total > 10) {
                $status = 'busy';
            }
            if ($total > 50) {
                $status = 'congested';
            }
            
            return [
                'success' => true,
                'running' => $running,
                'pending' => $pending,
                'total' => $total,
                'status' => $status,
                'message' => '队列状态正常',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => '获取队列状态失败',
            ];
        }
    }
    
    /**
     * 显示队列状态
     */
    private function displayQueueStatus(array $status): void
    {
        $this->newLine();
        $this->info('📋 队列状态:');
        
        if ($status['success']) {
            $this->line("✅ {$status['message']}");
            $this->line("   运行中: {$status['running']}");
            $this->line("   等待中: {$status['pending']}");
            $this->line("   总计: {$status['total']}");
            $this->line("   状态: " . $this->getStatusBadge($status['status']));
        } else {
            $this->error("❌ {$status['message']}");
            $this->line("   错误: {$status['error']}");
        }
    }
    
    /**
     * 检查配置
     */
    private function checkConfiguration(): array
    {
        $issues = [];
        
        // 检查基础URL配置
        $baseUrl = config('services.comfyui.base_url');
        if (!$baseUrl || $baseUrl === 'http://127.0.0.1:8188') {
            $issues[] = 'ComfyUI 基础URL使用默认值，可能需要配置远程服务器';
        }
        
        // 检查超时配置
        $timeout = config('services.comfyui.timeout_seconds');
        if ($timeout < 60) {
            $issues[] = "超时时间({$timeout}秒)可能不足，建议至少60秒";
        }
        
        // 检查队列配置
        $queueConnection = config('queue.default');
        if ($queueConnection === 'sync') {
            $issues[] = '队列使用同步驱动，建议使用 Redis 或 database';
        }
        
        return [
            'issues' => $issues,
            'base_url' => $baseUrl,
            'timeout' => $timeout,
            'queue_connection' => $queueConnection,
        ];
    }
    
    /**
     * 显示配置状态
     */
    private function displayConfigStatus(array $config): void
    {
        $this->newLine();
        $this->info('⚙️ 配置检查:');
        
        $this->line("   基础URL: {$config['base_url']}");
        $this->line("   超时时间: {$config['timeout']}秒");
        $this->line("   队列驱动: {$config['queue_connection']}");
        
        if (!empty($config['issues'])) {
            $this->newLine();
            $this->warn('⚠️ 发现配置问题:');
            foreach ($config['issues'] as $issue) {
                $this->line("   • {$issue}");
            }
        } else {
            $this->line("✅ 配置检查通过");
        }
    }
    
    /**
     * 显示总体评估
     */
    private function displayOverallAssessment(array $health, array $connection, array $queue, array $config): void
    {
        $this->newLine();
        $this->info('📈 总体评估:');
        
        $issues = [];
        
        // 评估连接状态
        if (!$connection['success']) {
            $issues[] = 'ComfyUI 服务器无法连接';
        } elseif ($connection['response_time_ms'] > 1000) {
            $issues[] = '服务器响应较慢 (' . $connection['response_time_ms'] . 'ms)';
        }
        
        // 评估错误率
        if ($health['error_rate'] > 20) {
            $issues[] = '错误率较高 (' . $health['error_rate'] . '%)';
        }
        
        // 评估队列状态
        if ($queue['success'] && $queue['total'] > 20) {
            $issues[] = '队列任务较多 (' . $queue['total'] . '个)';
        }
        
        // 评估配置
        if (!empty($config['issues'])) {
            $issues[] = '存在配置问题 (' . count($config['issues']) . '个)';
        }
        
        if (empty($issues)) {
            $this->line('✅ 系统状态良好，无需干预');
        } else {
            $this->warn('⚠️ 发现 ' . count($issues) . ' 个问题:');
            foreach ($issues as $issue) {
                $this->line("   • {$issue}");
            }
            
            $this->newLine();
            $this->line('💡 建议操作:');
            $this->line('   1. 运行 php artisan comfyui:health-check --fix 尝试自动修复');
            $this->line('   2. 检查 ComfyUI 服务器日志');
            $this->line('   3. 验证网络连接和防火墙设置');
            $this->line('   4. 调整超时配置（增加 services.comfyui.timeout_seconds）');
        }
    }
    
    /**
     * 尝试自动修复
     */
    private function attemptAutoFix(array $connection, array $queue, array $config): void
    {
        $this->newLine();
        $this->info('🔧 尝试自动修复...');
        
        $fixesApplied = 0;
        
        // 如果连接失败，尝试重启队列
        if (!$connection['success']) {
            $this->line('🔄 重启队列工作进程...');
            exec('php artisan queue:restart', $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->line('✅ 队列重启命令已发送');
                $fixesApplied++;
            } else {
                $this->error('❌ 队列重启失败');
            }
        }
        
        // 如果队列拥堵，清理旧任务
        if ($queue['success'] && $queue['total'] > 30) {
            $this->line('🧹 清理失败任务...');
            exec('php artisan queue:flush', $output, $returnCode);
            
            if ($returnCode === 0) {
                $this->line('✅ 失败任务已清理');
                $fixesApplied++;
            } else {
                $this->error('❌ 任务清理失败');
            }
        }
        
        // 如果配置有问题，给出建议
        if (!empty($config['issues'])) {
            $this->line('📝 配置建议:');
            foreach ($config['issues'] as $issue) {
                $this->line("   • {$issue}");
            }
            $fixesApplied++;
        }
        
        if ($fixesApplied > 0) {
            $this->newLine();
            $this->info("✅ 已应用 {$fixesApplied} 个修复");
            $this->line('建议等待几分钟后重新运行健康检查');
        } else {
            $this->line('⚠️ 未发现可自动修复的问题');
        }
    }
    
    /**
     * 获取状态徽章
     */
    private function getStatusBadge(string $status): string
    {
        $badges = [
            'healthy' => '<fg=green>✅ 健康</>',
            'degraded' => '<fg=yellow>⚠️ 降级</>',
            'unhealthy' => '<fg=red>❌ 不健康</>',
            'busy' => '<fg=yellow>🔄 繁忙</>',
            'congested' => '<fg=red>🚨 拥堵</>',
        ];
        
        return $badges[$status] ?? $status;
    }
    
    /**
     * 获取比率状态
     */
    private function getRateStatus(float $rate): string
    {
        if ($rate < 5) {
            return '<fg=green>优秀</>';
        } elseif ($rate < 20) {
            return '<fg=yellow>一般</>';
        } else {
            return '<fg=red>较差</>';
        }
    }
}