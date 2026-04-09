// 主题系统测试脚本
console.log('=== Tauri 主题适配测试 ===');

// 检查 CSS 变量是否定义
function checkCSSVariables() {
  const root = document.documentElement;
  const styles = getComputedStyle(root);
  
  const variables = [
    '--bg-primary',
    '--text-primary',
    '--primary-color',
    '--border-color'
  ];
  
  console.log('CSS 变量检查:');
  variables.forEach(varName => {
    const value = styles.getPropertyValue(varName).trim();
    console.log(`  ${varName}: ${value || '未定义'}`);
  });
}

// 检查主题属性
function checkThemeAttribute() {
  const theme = document.documentElement.getAttribute('data-theme');
  const hasDarkClass = document.documentElement.classList.contains('dark');
  
  console.log(`\n主题状态:`);
  console.log(`  data-theme: ${theme || '未设置'}`);
  console.log(`  .dark class: ${hasDarkClass ? '有' : '无'}`);
}

// 模拟系统主题变化
function simulateSystemThemeChange(isDark) {
  const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
  
  // 创建一个模拟的 MediaQueryListEvent
  const event = new MediaQueryListEvent('change', {
    media: '(prefers-color-scheme: dark)',
    matches: isDark
  });
  
  console.log(`\n模拟系统主题变化: ${isDark ? '深色' : '浅色'}`);
  mediaQuery.dispatchEvent(event);
}

// 运行测试
setTimeout(() => {
  checkCSSVariables();
  checkThemeAttribute();
  
  // 测试主题切换
  console.log('\n=== 主题切换测试 ===');
  
  // 测试浅色主题
  document.documentElement.setAttribute('data-theme', 'light');
  setTimeout(() => {
    console.log('切换到浅色主题');
    checkThemeAttribute();
    
    // 测试深色主题
    document.documentElement.setAttribute('data-theme', 'dark');
    setTimeout(() => {
      console.log('切换到深色主题');
      checkThemeAttribute();
      
      // 测试系统主题检测
      simulateSystemThemeChange(true);
      setTimeout(() => {
        checkThemeAttribute();
        console.log('\n=== 测试完成 ===');
      }, 100);
    }, 100);
  }, 100);
}, 500);