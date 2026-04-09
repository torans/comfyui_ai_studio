<!DOCTYPE html>
<html>
<head>
    <title>Dashboard - Beikuman Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            color: white;
        }
        .header {
            height: 64px;
            background: #111111;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 32px;
        }
        .header-brand {
            font-size: 16px;
            font-weight: 700;
            color: white;
        }
        .header-brand span { color: #3b82f6; }
        .header-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-user-name { font-size: 14px; color: rgba(255,255,255,0.7); }
        .header-logout {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            text-decoration: none;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .header-logout:hover { background: rgba(255,255,255,0.06); color: white; }
        .main { padding: 32px; }
        .page-title {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .page-subtitle {
            font-size: 14px;
            color: rgba(255,255,255,0.4);
            margin-bottom: 32px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: #111111;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.2s;
        }
        .stat-card:hover { border-color: rgba(59, 130, 246, 0.3); transform: translateY(-2px); }
        .stat-label {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
            font-weight: 500;
            margin-bottom: 8px;
        }
        .stat-value {
            font-size: 36px;
            font-weight: 700;
        }
        .stat-value.pending { color: #f59e0b; }
        .stat-value.running { color: #3b82f6; }
        .stat-value.failed { color: #ef4444; }
        .stat-value.assets { color: #10b981; }
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 16px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 32px;
        }
        .action-card {
            background: #111111;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
        }
        .action-card:hover {
            border-color: rgba(59, 130, 246, 0.4);
            background: rgba(59, 130, 246, 0.05);
        }
        .action-icon {
            width: 48px;
            height: 48px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 20px;
        }
        .action-title { font-size: 14px; font-weight: 600; margin-bottom: 4px; }
        .action-desc { font-size: 12px; color: rgba(255,255,255,0.4); }
        .table-card {
            background: #111111;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 16px;
            overflow: hidden;
        }
        .table-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.06);
            font-size: 15px;
            font-weight: 600;
        }
        .table-empty {
            padding: 60px 24px;
            text-align: center;
            color: rgba(255,255,255,0.3);
        }
        .table-empty-icon { font-size: 40px; margin-bottom: 12px; opacity: 0.3; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-brand">Beikuman <span>Admin</span></div>
        <div class="header-user">
            <span class="header-user-name">{{ auth()->user()->name }}</span>
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="header-logout">退出</a>
            <form id="logout-form" action="{{ route('logout') }}" method="POST" style="display: none;">@csrf</form>
        </div>
    </div>

    <div class="main">
        <h1 class="page-title">控制台</h1>
        <p class="page-subtitle">查看系统概览与管理入口</p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">等待处理</div>
                <div class="stat-value pending">{{ $stats['pending'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">生成中</div>
                <div class="stat-value running">{{ $stats['running'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">失败</div>
                <div class="stat-value failed">{{ $stats['failed'] }}</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">资源总数</div>
                <div class="stat-value assets">{{ $stats['recent_assets'] }}</div>
            </div>
        </div>

        <h2 class="section-title">快捷操作</h2>
        <div class="quick-actions">
            <a href="#" class="action-card">
                <div class="action-icon">⚡</div>
                <div class="action-title">创建任务</div>
                <div class="action-desc">提交新的生成请求</div>
            </a>
            <a href="#" class="action-card">
                <div class="action-icon">📋</div>
                <div class="action-title">工作流管理</div>
                <div class="action-desc">编辑与配置模板</div>
            </a>
            <a href="#" class="action-card">
                <div class="action-icon">👥</div>
                <div class="action-title">用户管理</div>
                <div class="action-desc">管理员工账号</div>
            </a>
        </div>

        <h2 class="section-title">最近任务</h2>
        <div class="table-card">
            <div class="table-header">暂无任务记录</div>
        </div>
    </div>
</body>
</html>
