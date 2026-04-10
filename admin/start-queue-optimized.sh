#!/bin/bash
# 优化的队列启动脚本，解决 60 秒超时问题

cd "$(dirname "$0")"

echo "🚀 启动优化队列工作进程..."
echo "当前目录: $(pwd)"

# 检查是否已安装 supervisor
if command -v supervisorctl &> /dev/null; then
    echo "✅ 检测到 Supervisor，建议使用 Supervisor 管理队列"
    echo "   配置示例: /etc/supervisor/conf.d/laravel-queue.conf"
fi

# 方法1：使用 nohup 后台运行（无超时限制）
echo ""
echo "🔧 方法1: 使用 nohup 后台运行（推荐）"
echo "执行命令:"
echo "nohup php artisan queue:work --timeout=600 --sleep=3 --tries=3 --memory=256 > storage/logs/queue-worker.log 2>&1 &"
echo ""
echo "启动后检查:"
echo "ps aux | grep 'queue:work'"
echo "tail -f storage/logs/queue-worker.log"

# 方法2：使用 Laravel Horizon（高级管理）
echo ""
echo "🔧 方法2: 使用 Laravel Horizon（生产环境推荐）"
echo "安装: composer require laravel/horizon"
echo "发布: php artisan horizon:install"
echo "启动: php artisan horizon"

# 方法3：修改现有启动方式
echo ""
echo "🔧 方法3: 修改现有启动方式（增加超时）"
echo "将 --timeout=60 改为 --timeout=600"
echo "示例:"
echo "php artisan queue:work --once --timeout=600 --name=default --queue=default --backoff=0 --memory=128 --sleep=3 --tries=1"

# 当前问题诊断
echo ""
echo "📊 当前问题诊断:"
echo "❌ 错误: queue:work --once 超时 60 秒"
echo "💡 原因: PollComfyUiJobStatus 使用 WebSocket 监听，可能长时间运行"
echo "✅ 解决方案: 增加超时时间或改用持续运行的队列工作进程"

echo ""
echo "🚀 立即修复（选择一种）:"
echo "1. 后台运行: nohup php artisan queue:work --timeout=600 --sleep=3 --tries=3 --memory=256 > storage/logs/queue-worker.log 2>&1 &"
echo "2. 测试运行: php artisan queue:work --once --timeout=600"
echo "3. 重启现有: php artisan queue:restart"
echo ""
echo "📝 监控命令:"
echo "tail -f storage/logs/laravel.log"
echo "tail -f storage/logs/queue-worker.log"
echo "ps aux | grep 'queue:work'"