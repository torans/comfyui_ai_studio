# Reverb 与 Tauri 实时通信方案

## 结论

可以做，而且当前方向是对的。

`admin` 作为 Laravel + Reverb 服务端负责广播，`tauri` 作为客户端订阅私有频道接收实时任务状态。Tauri 端不需要自己手搓 WebSocket 协议层，直接在前端 UI 层使用 `laravel-echo` + `pusher-js` 即可，因为 Reverb 兼容 Pusher 协议。

但有三个地方必须做对，不然就是连不上，或者更糟，串用户消息：

- Reverb 服务端口和广播目标端口不要混淆。
- Tauri 必须订阅私有频道，不要用公共频道。
- 私有频道鉴权必须带上登录后的 Sanctum token。

## 官方文档里的关键点

根据 Laravel 官方 Reverb 文档：

- Reverb 通过 `php artisan install:broadcasting` 安装，会顺带执行 Reverb 安装流程。
- Reverb 服务通过 `php artisan reverb:start` 启动。
- `allowed_origins` 需要在 `config/reverb.php` 中配置，不在允许列表里的来源会被拒绝。
- `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` 是 Reverb 进程自身监听的地址。
- `REVERB_HOST` / `REVERB_PORT` 是 Laravel 广播消息要发往的目标地址。
- 在 Herd / Valet 的 secure 模式下，可以通过 `--hostname` 使用站点证书，走 `wss`。

官方文档：

- [Laravel Reverb 13.x](https://laravel.com/docs/13.x/reverb)

## 当前仓库里的现状

当前 `admin` 已经有一部分正确配置：

- Reverb 配置文件存在：[`admin/config/reverb.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/config/reverb.php)
- 广播私有频道规则已存在：[`admin/routes/channels.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/routes/channels.php)
- 任务状态广播事件已存在：[`admin/app/Events/GenerationJobStatusChanged.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/app/Events/GenerationJobStatusChanged.php)
- 创建任务时已经触发广播：[`admin/app/Http/Controllers/Api/GenerationJobController.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/app/Http/Controllers/Api/GenerationJobController.php)
- 轮询 ComfyUI 状态时也会广播：[`admin/app/Jobs/PollComfyUiJobStatus.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/app/Jobs/PollComfyUiJobStatus.php)

也就是说，后端主链已经不是从零开始了。

## 推荐通信模型

### 频道模型

后端按用户私有频道广播：

- `user.{userId}`

这个规则已经在 [`admin/routes/channels.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/routes/channels.php) 里定义好了：

- 只有 `$user->id === $userId` 才能订阅成功。

Tauri 端应当订阅：

- `Echo.private(\`user.${currentUser.id}\`)`

而不是：

- `Echo.channel('admin-updates')`

后者是公共频道，调试方便，但不适合正式任务状态推送。

### 事件模型

后端广播事件名：

- `generation-job.status-changed`

所以 Tauri 端监听应该是：

```ts
echo.private(`user.${userId}`)
  .listen('.generation-job.status-changed', (payload) => {
    console.log('job update', payload)
  })
```

注意前面的点号。因为 Laravel 自定义了 `broadcastAs()`，Echo 监听自定义事件名时要用 `.event-name`。

## 本地开发配置

### admin/.env

本地开发建议明确区分这两组变量：

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=beikuman
REVERB_APP_KEY=beikuman_key
REVERB_APP_SECRET=beikuman_secret

REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080

REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

你当前的 [`admin/.env`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/.env) 里已经有 `REVERB_HOST/PORT/SCHEME`，但没有显式写出 `REVERB_SERVER_HOST/PORT`。虽然会走默认值，建议还是补上，少踩坑。

### 启动方式

在 `admin` 目录下启动：

```bash
php artisan reverb:start --debug
php artisan queue:work
php artisan serve
```

如果你用 Herd，本地站点走普通 HTTP，这样就够了。

### Herd Secure 模式

如果 Herd 给你的站点启用了 HTTPS，并且你希望 Tauri 也通过 `wss` 连接，官方文档建议使用站点 hostname 启动 Reverb，例如：

```bash
php artisan reverb:start --host="0.0.0.0" --port=8080 --hostname="your-app.test"
```

这样可以直接使用 Herd / Valet 生成的证书。

如果你只是本地联调 Tauri，优先建议先用：

- `ws://localhost:8080`

先把连通性和鉴权打通，再考虑 `wss`。先跑通，再讲究。

## Tauri 端接入方式

### 依赖

在 Tauri 前端项目安装：

```bash
bun add laravel-echo pusher-js
```

### Echo 初始化

```ts
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

const token = localStorage.getItem('auth_token')

window.Pusher = Pusher

export const echo = new Echo({
  broadcaster: 'reverb',
  key: 'beikuman_key',
  wsHost: '127.0.0.1',
  wsPort: 8080,
  wssPort: 8080,
  forceTLS: false,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: 'http://127.0.0.1:8000/broadcasting/auth',
  auth: {
    headers: {
      Authorization: `Bearer ${token}`,
      Accept: 'application/json',
    },
  },
})
```

这里最重要的是 `authEndpoint` 和 `Authorization` 头。

因为你现在的 API 认证走的是 Sanctum token，不是浏览器 Cookie 会话。Tauri 想订阅私有频道，就必须把 Bearer Token 带给 `/broadcasting/auth`。

### 订阅任务状态

```ts
echo.private(`user.${currentUser.id}`)
  .listen('.generation-job.status-changed', (event) => {
    console.log('收到任务状态', event)
  })
```

## 为什么不用公共频道

如果你用：

```ts
echo.channel('admin-updates')
```

会有两个问题：

1. 任何连上的客户端都能收到全部任务消息。
2. 后面做“我的生成记录”和“只看自己的任务”时，会和授权模型冲突。

这个项目的任务状态天然是用户私有数据，所以从一开始就应该走私有频道。

## allowed_origins 怎么配

官方文档明确说明，来源不在 `allowed_origins` 里的请求会被拒绝。

当前 [`admin/config/reverb.php`](/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin/config/reverb.php) 是：

```php
'allowed_origins' => ['*'],
```

开发阶段可以这样，省事。

生产环境建议收紧成明确列表，例如：

```php
'allowed_origins' => [
    'tauri://localhost',
    'http://localhost:1420',
    'https://admin.example.com',
]
```

注意一点，Tauri WebView 的 origin 在不同平台和运行模式下可能不同。开发期先用 `*` 最稳，等打包模式和部署方式固定后再收紧。

## 你现在真正要解决的点

不是“Reverb 能不能用”，而是“私有频道鉴权和 Tauri 的 Echo 初始化是否做对了”。

后端这边已经有：

- 私有频道定义
- 任务状态事件
- 任务创建广播
- 轮询结果广播

Tauri 端下一步该做的是：

1. 登录后保存 Sanctum token。
2. 初始化 Echo，给 `/broadcasting/auth` 带 Bearer Token。
3. 用 `user.{id}` 私有频道订阅。
4. 监听 `.generation-job.status-changed`。
5. 把消息按 `job_id` 更新到本地任务状态。

## 排查顺序

如果连不上，按这个顺序查：

1. `php artisan reverb:start --debug` 是否真的在跑。
2. `queue:work` 是否在跑，不然事件可能只写进队列没消费。
3. `/broadcasting/auth` 是否返回 200，而不是 401 / 403 / 419。
4. Tauri 是否带了 `Authorization: Bearer <token>`。
5. 订阅的频道名是否和后端一致，是 `user.{id}`，不是别的。
6. 监听事件名是否写成 `.generation-job.status-changed`。
7. Reverb 端口是否和 Echo 配置一致。
8. 如果是 HTTPS/Herd Secure，是否该用 `wss` 和 `--hostname`。

## 推荐落地

这个项目里，推荐保持下面这套方案不变：

- Laravel Admin 提供 Reverb WebSocket 服务。
- 任务状态事件继续使用私有频道 `user.{id}`。
- Tauri 使用 `laravel-echo` + `pusher-js` 连接 Reverb。
- Tauri 通过 Bearer Token 调 `/broadcasting/auth` 完成私有频道鉴权。
- 不使用公共频道承载正式任务消息。

这条路是对的。问题不在方向，问题在接线细节。
