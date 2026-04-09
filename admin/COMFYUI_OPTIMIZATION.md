# ComfyUI 远程连接优化指南

## 问题描述
ComfyUI 远程连接失败导致 Laravel 队列任务超时退出，错误信息：
```
Symfony\Component\Process\Exception\ProcessTimedOutException
The process "... queue:work --once ..." exceeded the timeout of 60 seconds.
```

## 根本原因
1. **网络延迟**：远程 ComfyUI 服务器响应慢
2. **超时设置不足**：默认60秒超时，但AI生成任务可能更久
3. **无重试机制**：网络波动导致一次性失败
4. **队列配置问题**：`queue:work --once` 的超时限制

## 已实施的优化方案

### 1. 增加任务超时时间
- **PollComfyUiJobStatus**: `60秒` → `300秒` (5分钟)
- **DispatchGenerationJob**: `300秒` → `600秒` (10分钟)

### 2. 添加HTTP请求重试机制
- 所有 ComfyUI API 调用自动重试3次
- 指数退避策略：1秒 → 2秒 → 4秒
- 详细日志记录每次重试

### 3. 优化错误处理
- 网络错误时记录警告事件，但不立即失败
- 增加重试间隔，避免频繁请求
- 保持任务状态，继续轮询

### 4. 创建队列监控脚本
- 自动检测卡住的任务
- 监控队列进程健康状态
- 自动恢复机制

## 配置文件优化

### .env 配置建议
```env
# ComfyUI 配置
COMFYUI_BASE_URL=http://your-comfyui-server:8188
COMFYUI_TIMEOUT_SECONDS=120  # 增加到120秒

# 队列配置
QUEUE_CONNECTION=redis  # 推荐使用 Redis 而不是 database
QUEUE_TIMEOUT=600       # 队列任务超时时间

# Laravel 队列工作进程
QUEUE_WORKER_TIMEOUT=600
QUEUE_WORKER_SLEEP=3
QUEUE_WORKER_TRIES=3
```

### Supervisor 配置（生产环境）
```ini
[program:laravel-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /path/to/your/admin/artisan queue:work --sleep=3 --tries=3 --timeout=600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=your_user
numprocs=4
redirect_stderr=true
stdout_logfile=/path/to/your/admin/storage/logs/worker.log
stopwaitsecs=3600
```

## 使用方式

### 1. 应用优化代码
```bash
cd /Users/jran/Developer/codes/2026/beikuman_ai_tools/admin

# 清除缓存
php artisan optimize:clear

# 重启队列
php artisan queue:restart

# 启动队列监听（开发环境）
php artisan queue:listen --timeout=600

# 或使用工作进程（生产环境）
php artisan queue:work --timeout=600
```

### 2. 运行监控脚本
```bash
# 后台运行监控
nohup php scripts/monitor-queue.php > storage/logs/monitor.log 2>&1 &

# 查看监控日志
tail -f storage/logs/monitor.log
```

### 3. 测试连接
```bash
# 测试 ComfyUI 连接
curl http://your-comfyui-server:8188/history

# 测试队列状态
php artisan queue:failed  # 查看失败任务
php artisan queue:retry all  # 重试所有失败任务
```

## 故障排除

### 问题1：任务仍然超时
**解决方案**：
1. 检查网络连接：`ping your-comfyui-server`
2. 增加超时时间：修改 `.env` 中的 `COMFYUI_TIMEOUT_SECONDS`
3. 检查 ComfyUI 服务器负载

### 问题2：队列进程频繁重启
**解决方案**：
1. 使用 Supervisor 管理进程
2. 增加 `stopwaitsecs` 配置
3. 检查内存使用：`php artisan queue:monitor`

### 问题3：ComfyUI 响应慢
**解决方案**：
1. 优化 ComfyUI 配置：
   ```python
   # ComfyUI 启动参数
   python main.py --listen 0.0.0.0 --port 8188 --enable-cors-header
   ```
2. 使用更快的模型或减少复杂度
3. 增加服务器资源

### 问题4：大量任务失败
**解决方案**：
1. 实现任务优先级队列
2. 添加任务限流
3. 实现任务取消机制

## 高级优化

### 1. 异步任务处理
```php
// 使用 Laravel Horizon 管理队列
composer require laravel/horizon
php artisan horizon:install
```

### 2. 任务状态持久化
```php
// 定期保存任务状态，防止进程崩溃丢失
class PollComfyUiJobStatus implements ShouldQueue
{
    public function handle()
    {
        // 保存检查点
        cache()->put("job_{$this->jobId}_last_check", now());
        
        // ... 原有逻辑
    }
}
```

### 3. 健康检查端点
```php
// routes/api.php
Route::get('/health/comfyui', function () {
    try {
        $client = app(ComfyUiClient::class);
        $queue = $client->fetchQueue();
        return response()->json(['status' => 'healthy', 'queue' => $queue]);
    } catch (Exception $e) {
        return response()->json(['status' => 'unhealthy', 'error' => $e->getMessage()], 500);
    }
});
```

## 监控指标

### 关键指标
1. **任务成功率**：成功任务数 / 总任务数
2. **平均处理时间**：任务从创建到完成的时间
3. **队列深度**：等待处理的任务数
4. **错误率**：失败任务比例

### 告警规则
- 连续5个任务失败 → 发送通知
- 队列深度超过50 → 警告
- ComfyUI 响应时间 > 30秒 → 检查服务器

## 总结

通过以上优化，你的系统应该能够：
1. ✅ 处理网络不稳定的情况
2. ✅ 支持长时间运行的AI任务
3. ✅ 自动恢复失败的任务
4. ✅ 提供详细的监控和日志

如果问题仍然存在，请检查：
1. ComfyUI 服务器是否正常运行
2. 网络防火墙设置
3. 服务器资源（CPU、内存、磁盘）
4. Laravel 和 ComfyUI 的日志文件

---

**优化时间**: 2026-04-09  
**最后更新**: 2026-04-09  
**助手**: 十六助手 ✨