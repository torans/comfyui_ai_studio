import { useEffect, useState } from 'react';
import { useStore } from '../store';

export type ThemeMode = 'light' | 'dark' | 'auto';

export function useTheme() {
  const store = useStore();
  const [systemTheme, setSystemTheme] = useState<'light' | 'dark'>('light');

  // 检测系统主题变化
  useEffect(() => {
    if (typeof window.matchMedia !== 'function') {
      return;
    }

    const updateSystemTheme = () => {
      const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      setSystemTheme(isDark ? 'dark' : 'light');
    };

    updateSystemTheme();
    
    const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
    const handleChange = (e: MediaQueryListEvent) => {
      setSystemTheme(e.matches ? 'dark' : 'light');
    };
    
    mediaQuery.addEventListener('change', handleChange);
    return () => mediaQuery.removeEventListener('change', handleChange);
  }, []);

  // 计算实际应用的主题
  const effectiveTheme = store.theme === 'auto' ? systemTheme : store.theme;

  // 应用主题到 document
  useEffect(() => {
    document.documentElement.setAttribute('data-theme', effectiveTheme);
    document.documentElement.classList.toggle('dark', effectiveTheme === 'dark');
  }, [effectiveTheme]);

  return {
    theme: store.theme,
    setTheme: store.setTheme,
    systemTheme,
    effectiveTheme,
    isDark: effectiveTheme === 'dark',
  };
}
