# 项目记忆

## 项目概述
- 项目路径: `/Users/jran/Developer/codes/2026/beikuman_ai_tools/admin`
- Laravel 13 + Inertia.js + React + MySQL
- Tauri 桌面应用的后端管理系统

## 重要约束
- **必须使用中文**: 所有代码注释、用户界面、错误消息都必须使用中文
- **使用 bun 作为包管理器**: npm/yarn/pnpm 都要换成 bun

## 工作流类型
- `t2i` - 文生图 (Text-to-Image)
- `i2i` - 图生图 (Image-to-Image)
- `t2v` - 文生视频 (Text-to-Video)
- `i2v` - 图生视频 (Image-to-Video)
- `other` - 其他工具 (Other Tools)

## 工作流配置方案

### JSON 存放位置
- 工作流 JSON 存储在数据库 `workflow_templates` 表的 `definition_json` 字段
- 源文件存放在 `../../src/workflows/` 目录

### 动态变量配置
通过 `parameter_schema_json` 字段配置动态变量：

```json
{
  "prompt": {
    "node": "69",
    "field": "prompt",
    "label": "提示词",
    "type": "textarea",
    "default": "一只可爱的蓝色机械猫"
  },
  "width": {
    "node": "64:13",
    "field": "width",
    "label": "宽度",
    "type": "number",
    "default": 1088
  }
}
```

### WorkflowResolver 工作流程
1. 加载 `definition_json` 作为工作流模板
2. 遍历 `parameter_schema_json`
3. 根据 `node` 和 `field` 定位到工作流中的具体位置
4. 用用户输入的值替换原始值

## 已完成功能
- ✅ 工作流管理 API (CRUD + 启动/停止)
- ✅ 工作流管理页面
- ✅ 工作流列表展示
- ✅ 启动/停止工作流
- ✅ 动态变量注入 (WorkflowResolver)
