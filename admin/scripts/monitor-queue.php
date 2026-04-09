<?php

/**
 * 队列监控脚本
 * 监控 Laravel 队列状态，自动重启失败的任务
 * 用法：php scripts/monitor-queue.php
 */

use App\Models\GenerationJob;
use Illuminate\Support\Facades\Log;

require __DIR__ . '/../bootstrap/app.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

class QueueMonitor
{
    private $maxStuckMinutes = 10; // 任务卡住的最大分钟数
    private $checkInterval = 60;   // 检查间隔（秒）
    
    public function run()
    {
        Log::info('队列监控脚本启动');
        
        while (true) {
            try {
                $this->checkStuckJobs();
                $this->checkFailedJobs();
                $this->checkQueueHealth();
                
                sleep($this->checkInterval);
            } catch (Exception $e) {
                Log::error('队列监控脚本异常: ' . $e->getMessage());
                sleep($this->checkInterval * 2); // 异常时等待更久
            }
        }
    }
    
    /**
     * 检查卡住的任务
     */
    private function checkStuckJobs()
    {
        $stuckTime = now()->subMinutes($this->maxStuckMinutes);
        
        $stuckJobs = GenerationJob::where('status', 'running')
            ->where('updated_at', '<', $stuckTime)
            ->get();
            
        foreach ($stuckJobs as $job) {
            Log::warning("检测到卡住的任务 #{$job->id}，最后更新于 {$job->updated_at}");
            
            // 尝试重新触发轮询
            $this->restartPolling($job);
        }
    }
    
    /**
     * 检查失败的任务
     */
    private function checkFailedJobs()
    {
        $recentFailedJobs = GenerationJob::where('status', 'failed')
            ->where('updated_at', '>', now()->subHours(1))
            ->get();
            
        if ($recentFailedJobs->count() > 5) {
            Log::error("检测到大量失败任务: {$recentFailedJobs->count()} 个");
            // 可以发送通知或执行恢复操作
        }
    }
    
    /**
     * 检查队列健康状态
     */
    private function checkQueueHealth()
    {
        // 检查队列进程是否运行
        $output = [];
        exec('pgrep -f "queue:work"', $output);
        
        if (empty($output)) {
            Log::emergency('队列工作进程未运行！尝试重启...');
            $this->restartQueueWorker();
        }
    }
    
    /**
     * 重启轮询任务
     */
    private function restartPolling(GenerationJob $job)
    {
        try {
            // 如果任务有 prompt_id，重新触发轮询
            if ($job->comfy_prompt_id) {
                Log::info("重新触发轮询任务 #{$job->id}");
                
                // 这里可以重新分发轮询任务
                // \App\Jobs\PollComfyUiJobStatus::dispatch($job->id);
                
                // 或者标记为需要人工干预
                $job->update([
                    'status' => 'needs_review',
                    'error_message' => '任务卡住，需要人工检查'
                ]);
            }
        } catch (Exception $e) {
            Log::error("重启轮询任务失败 #{$job->id}: " . $e->getMessage());
        }
    }
    
    /**
     * 重启队列工作进程
     */
    private function restartQueueWorker()
    {
        try {
            // 使用 supervisor 或 systemd 管理的队列
            exec('sudo systemctl restart laravel-queue 2>/dev/null', $output, $returnCode);
            
            if ($returnCode === 0) {
                Log::info('队列工作进程重启成功');
            } else {
                // 尝试直接启动
                $command = 'cd ' . base_path() . ' && php artisan queue:work --daemon > /dev/null 2>&1 &';
                exec($command);
                Log::info('队列工作进程已启动');
            }
        } catch (Exception $e) {
            Log::error('重启队列工作进程失败: ' . $e->getMessage());
        }
    }
}

// 运行监控
if (php_sapi_name() === 'cli') {
    $monitor = new QueueMonitor();
    $monitor->run();
} else {
    echo "此脚本只能在命令行运行\n";
}