# Tauri 主题适配系统 - 使用说明

## 功能概述

已为你的 Tauri 应用添加完整的主题适配系统，支持：
- ✅ **白天模式** - 明亮界面
- ✅ **夜间模式** - 暗黑界面  
- ✅ **跟随系统** - 自动匹配系统主题（默认）
- ✅ **设置页面** - 在设置中添加主题选择
- ✅ **CSS 变量** - 统一的设计令牌系统
- ✅ **状态持久化** - 记住用户选择

## 文件结构

```
src/
├── hooks/useTheme.ts          # 主题 Hook，检测系统主题变化
├── store.ts                   # 更新了 Zustand store，添加主题状态
├── App.tsx                    # 更新了 SettingsView，添加主题选择
├── App.css                    # 添加了 CSS 变量和主题样式
└── components/ThemeToggle.tsx # 独立主题切换组件（备用）
```

## 使用方法

### 1. 在组件中使用主题

```tsx
import { useTheme } from './hooks/useTheme';

function MyComponent() {
  const { theme, setTheme, isDark, effectiveTheme } = useTheme();
  
  return (
    <div className={isDark ? 'dark-mode' : 'light-mode'}>
      <button onClick={() => setTheme('dark')}>切换到深色</button>
      <button onClick={() => setTheme('light')}>切换到浅色</button>
      <button onClick={() => setTheme('auto')}>跟随系统</button>
    </div>
  );
}
```

### 2. 在 CSS 中使用变量

```css
.my-element {
  background: var(--bg-card);
  color: var(--text-primary);
  border: 1px solid var(--border-color);
}

.my-element:hover {
  background: var(--bg-hover);
}
```

### 3. 可用 CSS 变量

#### 背景颜色
- `--bg-primary` - 主背景
- `--bg-secondary` - 次要背景
- `--bg-card` - 卡片背景
- `--bg-hover` - 悬停背景
- `--bg-surface` - 表面背景
- `--sidebar-bg` - 侧边栏背景

#### 文字颜色
- `--text-primary` - 主要文字
- `--text-secondary` - 次要文字
- `--text-tertiary` - 三级文字

#### 边框颜色
- `--border-color` - 主要边框
- `--border-subtle` - 细微边框

#### 主题颜色
- `--primary-color` - 主色
- `--primary-bg-subtle` - 主色背景

#### 阴影
- `--shadow-sm` - 小阴影
- `--shadow-md` - 中阴影
- `--shadow-lg` - 大阴影

## 设置页面

主题设置已集成到应用的设置页面（Settings Tab）：
1. 点击侧边栏的"设置"图标
2. 在"界面主题"部分选择：
   - **跟随系统** - 自动匹配 macOS/Windows 系统主题
   - **白天模式** - 强制使用浅色主题
   - **夜间模式** - 强制使用深色主题

## 扩展功能

### 1. 添加更多主题
可以在 `store.ts` 中扩展 `ThemeMode` 类型，添加更多主题选项。

### 2. 主题相关功能
```tsx
// 检测系统主题变化
const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
mediaQuery.addEventListener('change', (e) => {
  console.log('系统主题变化:', e.matches ? '深色' : '浅色');
});

// 获取当前主题
const currentTheme = document.documentElement.getAttribute('data-theme');
const isDarkMode = document.documentElement.classList.contains('dark');
```

### 3. Tauri 特定优化
```rust
// 在 Rust 后端可以添加系统主题检测
#[cfg(target_os = "macos")]
fn get_system_appearance() -> String {
    // macOS 系统外观检测
}
```

## 测试方法

1. **手动测试**：
   - 切换系统主题（系统设置 → 外观）
   - 检查应用是否自动跟随变化
   - 在设置页面手动切换主题

2. **代码测试**：
   ```bash
   # 运行开发服务器
   npm run dev
   
   # 构建应用
   npm run build
   
   # Tauri 开发
   npm run tauri dev
   ```

## 注意事项

1. **CSS 变量优先级**：CSS 变量在 `:root` 和 `[data-theme="dark"]` 中定义，确保正确覆盖
2. **过渡动画**：添加了 `transition: background-color 0.3s ease` 实现平滑切换
3. **浏览器兼容**：CSS 变量在现代浏览器中完全支持
4. **Tauri 兼容**：主题系统纯前端实现，与 Tauri 完全兼容

## 故障排除

### 问题：主题不切换
- 检查 `localStorage` 中是否有 `app_theme` 值
- 检查控制台是否有 JavaScript 错误
- 验证 CSS 变量是否正确加载

### 问题：颜色不一致
- 确保所有颜色都使用 CSS 变量，而不是硬编码
- 检查 CSS 变量作用域是否正确

### 问题：系统主题不检测
- 检查 `useTheme.ts` 中的 `matchMedia` 监听器
- 验证浏览器是否支持 `prefers-color-scheme`

---

**创建时间**: 2026-04-09  
**最后更新**: 2026-04-09  
**助手**: 十六助手 ✨