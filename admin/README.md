# ComfyUI 管理后台

基于 Laravel 13 + Inertia.js + React 的 AI 生成任务管理系统。

## 环境要求

- PHP 8.4+
- MySQL 8.0+
- Redis
- Bun
- Node.js 20+

## 安装

```bash
composer install
bun install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

## 启动服务

项目需要启动多个服务，建议使用以下命令组合：

### 1. 启动 Laravel 开发服务器

```bash
php artisan serve
```

### 2. 启动 Laravel Reverb (WebSocket)

用于实时推送任务状态到 Tauri 客户端：

```bash
php artisan reverb:start
```

### 3. 启动队列监听器

处理异步任务（如提交到 ComfyUI）：

```bash
php artisan queue:work
```

### 4. 启动 Vite 开发服务器

```bash
bun run dev
```

### 5. 启动 Tauri 桌面客户端

```bash
bun run tauri dev
```

## 快捷启动

使用 `composer dev` 可以同时启动多个服务：

```bash
composer dev
```

这会启动：

- PHP 开发服务器 (localhost:8000)
- 队列监听器
- 日志监控 (pail)
- Vite 开发服务器

## 环境变量

主要配置项：

```env
# 数据库
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=beikuman
DB_USERNAME=root
DB_PASSWORD=your_password

# 队列
QUEUE_CONNECTION=database

# 广播 (使用 Reverb)
BROADCAST_CONNECTION=reverb

# Redis (用于 Reverb)
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# ComfyUI
COMFYUI_BASE_URL=https://comfyui.lanqiu.tech
COMFYUI_TIMEOUT_SECONDS=60

# Reverb WebSocket
REVERB_APP_ID=beikuman
REVERB_APP_KEY=beikuman_key
REVERB_APP_SECRET=beikuman_secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

## 测试账号

```
邮箱: employee@beikuman.com
密码: password
```

## 主要功能

- **工作流管理**：创建、编辑、删除 AI 生成工作流模板
- **任务调度**：通过队列异步提交任务到 ComfyUI
- **实时状态**：Laravel Reverb WebSocket 推送任务状态更新
- **Tauri 桌面客户端**：独立的桌面应用，通过 Admin API 与 ComfyUI 交互
