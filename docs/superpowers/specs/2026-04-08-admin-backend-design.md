# Admin Backend Design

Date: 2026-04-08

## Goal

在当前项目根目录下新增 `admin/` 目录，承载 Laravel 13 + Inertia.js + MySQL 后端。

这套后端负责两类能力：

1. 内部管理后台，供管理员使用
2. 给 Tauri 员工端使用的业务 API

员工不登录后台。管理员不登录 Tauri。 前端用 bun做包管理。

## Product Boundary

### Admin

管理员通过 Inertia 后台完成以下操作：

- 管理员工账号
- 管理工作流模板
- 查看所有生成任务
- 查看任务结果和失败原因
- 配置 ComfyUI 服务

### Tauri Employee Client

员工通过 Tauri 客户端完成以下操作：

- 账号密码登录
- 发起文生图、图生图、图生视频任务
- 查看自己的任务列表
- 查看自己的生成结果

### ComfyUI

ComfyUI 只作为执行引擎存在，不再暴露给 Tauri 直接调用。

所有上传、工作流拼装、提交 `/prompt`、轮询 `/history`、结果落库，都由 Laravel 后端完成。

## Architecture

### High-Level Flow

1. 员工在 Tauri 登录，拿到 API token
2. Tauri 调 Laravel API 提交任务
3. Laravel 创建 `generation_jobs` 记录
4. Laravel 队列 Job 异步调用 ComfyUI
5. Laravel 持续同步执行状态和结果
6. Tauri 轮询自己的任务和结果 API

### Why This Split

- 避免 Tauri 直接跨域连接 ComfyUI
- 工作流模板集中维护，避免前端硬编码越来越多
- 队列、重试、失败记录都统一在后端
- 权限模型清楚，员工只能看自己的任务
- 后续即使接网页端，也不需要再重做 ComfyUI 调度层

## Directory Layout

项目根目录新增：

```text
admin/
  app/
    Actions/
    Jobs/
    Models/
    Services/
    Http/
      Controllers/
        Admin/
        Api/
  bootstrap/
  config/
  database/
    migrations/
    seeders/
  resources/
    js/
      Pages/
      Layouts/
      Components/
  routes/
    web.php
    api.php
  storage/
  tests/
```

### Key Internal Modules

#### `app/Services/ComfyUi`

负责：

- 上传图片到 ComfyUI
- 提交 workflow
- 查询 history
- 解析结果

#### `app/Services/Workflow`

负责：

- 从数据库读取工作流模板
- 根据任务参数注入 prompt、比例、图片、模型
- 输出最终执行用 workflow JSON

#### `app/Jobs`

负责异步任务执行：

- `DispatchGenerationJob`
- `SyncGenerationJobStatus`

#### `app/Http/Controllers/Api`

提供给 Tauri 的接口：

- 登录
- 当前用户
- 创建任务
- 查询任务列表
- 查询任务详情

#### `app/Http/Controllers/Admin`

提供后台页面数据：

- 用户管理
- 工作流模板管理
- 任务管理
- 系统配置

## Data Model

### `users`

字段建议：

- `id`
- `name`
- `email`
- `password`
- `openid`
- `openid_provider`
- `openid_bound_at`
- `role`，值为 `admin` 或 `employee`
- `status`
- `last_login_at`
- `created_at`
- `updated_at`

说明：

- `openid` 用于预留第三方身份系统绑定，例如钉钉
- `openid_provider` 用于标记身份来源，例如 `dingtalk`
- `openid_bound_at` 用于记录绑定时间
- 第一版员工登录仍然以账号密码为主，openid 暂时只做预留字段，不作为主登录链路

### `personal_access_tokens`

使用 Sanctum，给 Tauri 员工端发 token。

### `workflow_templates`

字段建议：

- `id`
- `name`
- `code`
- `type`，值为 `t2i`, `i2i`, `i2v`
- `version`
- `definition_json`
- `parameter_schema_json`
- `is_active`
- `created_by`
- `updated_by`
- `created_at`
- `updated_at`

说明：

- `definition_json` 存原始 workflow
- `parameter_schema_json` 存可注入参数定义，例如 prompt、比例、模型、图片节点

### `generation_jobs`

字段建议：

- `id`
- `user_id`
- `workflow_template_id`
- `type`
- `status`，值为 `pending`, `queued`, `running`, `succeeded`, `failed`
- `input_json`
- `resolved_workflow_json`
- `comfy_prompt_id`
- `error_message`
- `started_at`
- `finished_at`
- `created_at`
- `updated_at`

说明：

- `input_json` 是员工提交的原始业务参数
- `resolved_workflow_json` 是实际发给 ComfyUI 的最终 workflow，便于排障

### `generation_assets`

字段建议：

- `id`
- `generation_job_id`
- `user_id`
- `type`，值为 `image`, `video`
- `filename`
- `subfolder`
- `storage_disk`
- `storage_path`
- `preview_path`
- `metadata_json`
- `created_at`
- `updated_at`

说明：

- 员工端任务结果最终从这张表读取
- 保持 `user_id` 冗余字段，方便按用户快速筛选

### Optional `system_settings`

字段建议：

- `key`
- `value`

用于存：

- ComfyUI base URL
- 默认工作流版本
- 队列策略

## Auth Design

### Admin Auth

后台管理员使用 Laravel Web 登录，会话鉴权。

### Employee API Auth

员工在 Tauri 客户端使用账号密码登录，Laravel 返回 Sanctum token。

Tauri 后续每次请求带 Bearer Token。

用户表中预留 openid 相关字段，供后续接入钉钉等第三方身份系统时使用。

### Permission Rule

- 员工只能访问自己的任务和结果
- 管理员可以在后台查看全部用户和全部任务
- 管理员接口与员工 API 严格分离

## API Design For Tauri

前缀统一使用 `/api`.

### Auth

- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

### Generation

- `POST /api/generation-jobs`
- `GET /api/generation-jobs`
- `GET /api/generation-jobs/{id}`

### Asset Access

- `GET /api/generation-assets/{id}`

### Suggested Request Shape

`POST /api/generation-jobs`

```json
{
  "type": "i2v",
  "workflow_code": "ltx_2_3_i2v",
  "inputs": {
    "prompt": "镜头缓慢推进，人物轻轻转头",
    "aspect_ratio": "16:9",
    "image_upload_id": "temp-upload-id"
  }
}
```

### Suggested Response Shape

```json
{
  "id": 123,
  "status": "pending"
}
```

## Upload Strategy

第一版建议不要再让 Tauri 直接把源图传到 ComfyUI。

改成：

1. Tauri 上传到 Laravel
2. Laravel 存临时文件
3. 队列任务执行时，再由 Laravel 上传到 ComfyUI

这样好处：

- 完全绕开浏览器跨域
- 上传日志和失败原因可追踪
- 源图与任务绑定更清楚

建议后续加一张临时上传表或直接落本地私有存储目录。

## Queue Design

第一版使用 Laravel Queue。

### Queue Job Lifecycle

1. 创建业务任务，状态 `pending`
2. 分发执行队列，状态改为 `queued`
3. 调用 ComfyUI `/prompt` 成功后，状态改为 `running`
4. 轮询或同步 `/history`
5. 成功则写 `generation_assets` 并标记 `succeeded`
6. 失败则写 `error_message` 并标记 `failed`

### Long Running Video Jobs

图生视频任务可能持续 30 分钟以上。

因此队列设计必须满足：

- 较长 timeout
- 失败重试可控
- history 同步不能只依赖前端

建议视频任务采用分阶段策略：

- `DispatchGenerationJob` 负责发起 `/prompt`
- `SyncGenerationJobStatus` 负责后续轮询和收尾

这样比单个超长同步任务更稳。

## Workflow Strategy

工作流不再写死在 Tauri 里作为主数据源。

第一版允许保留前端本地 workflow 仅供过渡，但权威来源迁移到 Laravel。

后台管理员可上传或替换 workflow 模板：

- 文生图 workflow
- 图生图 workflow
- 图生视频 workflow

参数注入规则在后端定义，例如：

- prompt 节点
- negative prompt 节点
- ratio 对应的宽高节点
- image input 节点
- model 节点

## Admin Pages

第一版建议先做这几页：

### Dashboard

- 今日任务数
- 运行中任务
- 失败任务
- 最近结果

### Users

- 查看员工列表
- 新增员工
- 重置密码
- 禁用账号

### Workflows

- 查看模板列表
- 上传 workflow JSON
- 编辑模板元数据
- 切换启用版本

### Jobs

- 查看全部任务
- 筛选状态、类型、用户
- 查看失败原因
- 查看最终 workflow

### Settings

- 配置 ComfyUI 地址
- 默认工作流
- 队列参数

## Tauri Migration Plan

### Phase 1

先保留当前 Tauri 页面结构，只改请求目标：

- 登录改打 Laravel
- 生成任务改打 Laravel
- 结果列表改打 Laravel

### Phase 2

逐步删除 Tauri 里直接对 ComfyUI 的代码：

- 直连 `/prompt`
- 直连 `/history`
- 直连 `/upload/image`
- 本地 workflow 注入逻辑

### Phase 3

仅保留纯前端职责：

- 页面交互
- 本地预览
- 用户 token 存储
- 任务列表展示

## Risks

### Risk 1: 双源工作流

如果 Laravel 和 Tauri 同时维护 workflow，会很快漂移。

解决：

- 后端作为最终权威来源
- Tauri 仅做短期兼容

### Risk 2: 视频任务时间过长

如果用单个超长请求去等视频结果，可靠性差。

解决：

- 队列异步化
- 状态落库
- 前端查任务，不直接等 ComfyUI

### Risk 3: 权限混乱

如果管理后台和员工 API 复用同一套路由和授权逻辑，后期容易出错。

解决：

- `web.php` 只管后台
- `api.php` 只管员工端 API
- 明确区分 admin / employee

## MVP Scope

第一版只做最小闭环：

1. 管理员后台登录
2. 员工 API 登录
3. 员工提交文生图任务
4. 员工提交图生视频任务
5. 管理员查看所有任务
6. 员工查看自己的任务与结果
7. 工作流模板后台可配置

## Non-Goals For MVP

第一版不做：

- 手机验证码登录
- 多组织多租户
- 员工后台页面
- 对外开放 API
- 计费系统
- 自动扩容多个 ComfyUI 节点

## Recommended Next Step

下一步不是立刻改 Tauri。

下一步应该先在 `admin/` 中完成：

1. Laravel 13 初始化
2. Inertia 后台初始化
3. 用户与任务核心表迁移
4. 员工登录 API
5. 文生图任务最小闭环 API

先把一条链路跑通，再接 Tauri。
