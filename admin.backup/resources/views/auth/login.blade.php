<!DOCTYPE html>
<html>
<head>
    <title>登录 - Beikuman</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            display: flex;
            width: 940px;
            height: 560px;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0,0,0,0.5);
        }
        .left-panel {
            width: 420px;
            background: linear-gradient(135deg, #1a1a1a 0%, #0f0f0f 100%);
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }
        .left-panel::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 30% 70%, rgba(59, 130, 246, 0.08) 0%, transparent 50%),
                        radial-gradient(circle at 70% 30%, rgba(139, 92, 246, 0.06) 0%, transparent 50%);
            pointer-events: none;
        }
        .brand { position: relative; z-index: 1; }
        .brand-name {
            font-size: 22px;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
        }
        .brand-name span { color: #3b82f6; }
        .left-content { position: relative; z-index: 1; }
        .left-title {
            font-size: 32px;
            font-weight: 700;
            color: white;
            line-height: 1.2;
            margin-bottom: 16px;
        }
        .left-subtitle {
            font-size: 14px;
            color: rgba(255,255,255,0.5);
            line-height: 1.6;
        }
        .left-footer {
            position: relative;
            z-index: 1;
            font-size: 12px;
            color: rgba(255,255,255,0.3);
        }
        .right-panel {
            flex: 1;
            background: #111111;
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .form-header { margin-bottom: 36px; }
        .form-header h2 {
            font-size: 24px;
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
        }
        .form-header p {
            font-size: 14px;
            color: rgba(255,255,255,0.4);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper input {
            width: 100%;
            padding: 14px 16px;
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            font-size: 14px;
            color: white;
            transition: all 0.2s;
        }
        .input-wrapper input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .input-wrapper input::placeholder { color: rgba(255,255,255,0.25); }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }
        .btn-login:hover { background: #2563eb; transform: translateY(-1px); }
        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="left-panel">
            <div class="brand">
                <div class="brand-name">Beikuman <span>Admin</span></div>
            </div>
            <div class="left-content">
                <div class="left-title">AI创作管理后台</div>
                <div class="left-subtitle">统一管理员工账号、工作流模板与生成任务，轻松调度 AI 创作任务。</div>
            </div>
            <div class="left-footer">© 2026 Beikuman</div>
        </div>
        <div class="right-panel">
            <div class="form-header">
                <h2>欢迎回来</h2>
                <p>请登录您的管理员账号</p>
            </div>

            @if ($errors->has('email'))
                <div class="error-msg">{{ $errors->first('email') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="form-group">
                    <label for="email">邮箱地址</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="admin@example.com" required autofocus>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                </div>
                <button type="submit" class="btn-login">登 录</button>
            </form>
        </div>
    </div>
</body>
</html>
