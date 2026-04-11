import { Sun, Moon, Monitor } from 'lucide-react';
import { useTheme } from '../hooks/useTheme';

export function ThemeToggle() {
  const { theme, setTheme, isDark } = useTheme();
  
  const themes = [
    { value: 'light' as const, icon: Sun, label: '浅色', description: '明亮模式' },
    { value: 'dark' as const, icon: Moon, label: '深色', description: '暗黑模式' },
    { value: 'auto' as const, icon: Monitor, label: '自动', description: '跟随系统' },
  ];

  return (
    <div className="theme-toggle">
      <div className="theme-toggle-header">
        <span className="theme-toggle-title">主题设置</span>
        <div className="theme-toggle-indicator">
          <div className={`theme-dot ${isDark ? 'dark' : 'light'}`} />
          <span>{isDark ? '深色模式' : '浅色模式'}</span>
        </div>
      </div>
      
      <div className="theme-options">
        {themes.map(({ value, icon: Icon, label, description }) => (
          <button
            key={value}
            onClick={() => setTheme(value)}
            className={`theme-option ${theme === value ? 'active' : ''}`}
            title={description}
            aria-label={`切换到${label}主题`}
          >
            <div className="theme-option-icon">
              <Icon size={18} />
            </div>
            <div className="theme-option-content">
              <span className="theme-option-label">{label}</span>
              <span className="theme-option-description">{description}</span>
            </div>
            {theme === value && (
              <div className="theme-option-check">
                <div className="check-dot" />
              </div>
            )}
          </button>
        ))}
      </div>
      
      <style>{`
        .theme-toggle {
          background: var(--card-bg);
          border: 1px solid var(--border-color);
          border-radius: 12px;
          padding: 16px;
          width: 280px;
          box-shadow: var(--shadow-md);
        }
        
        .theme-toggle-header {
          display: flex;
          justify-content: space-between;
          align-items: center;
          margin-bottom: 16px;
          padding-bottom: 12px;
          border-bottom: 1px solid var(--border-subtle);
        }
        
        .theme-toggle-title {
          font-weight: 600;
          font-size: 14px;
          color: var(--text-primary);
        }
        
        .theme-toggle-indicator {
          display: flex;
          align-items: center;
          gap: 8px;
          font-size: 12px;
          color: var(--text-secondary);
        }
        
        .theme-dot {
          width: 10px;
          height: 10px;
          border-radius: 50%;
        }
        
        .theme-dot.light {
          background: linear-gradient(135deg, #fbbf24, #f59e0b);
        }
        
        .theme-dot.dark {
          background: linear-gradient(135deg, #4f46e5, #7c3aed);
        }
        
        .theme-options {
          display: flex;
          flex-direction: column;
          gap: 8px;
        }
        
        .theme-option {
          display: flex;
          align-items: center;
          gap: 12px;
          padding: 12px;
          border-radius: 8px;
          border: 1px solid var(--border-color);
          background: var(--bg-subtle);
          cursor: pointer;
          transition: all 0.2s ease;
          text-align: left;
          width: 100%;
        }
        
        .theme-option:hover {
          background: var(--bg-hover);
          border-color: var(--primary-color);
          transform: translateY(-1px);
        }
        
        .theme-option.active {
          background: var(--primary-bg-subtle);
          border-color: var(--primary-color);
          box-shadow: 0 0 0 1px var(--primary-color);
        }
        
        .theme-option-icon {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 36px;
          height: 36px;
          border-radius: 8px;
          background: var(--bg-surface);
          color: var(--text-secondary);
        }
        
        .theme-option.active .theme-option-icon {
          background: var(--primary-color);
          color: white;
        }
        
        .theme-option-content {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 2px;
        }
        
        .theme-option-label {
          font-weight: 500;
          font-size: 14px;
          color: var(--text-primary);
        }
        
        .theme-option-description {
          font-size: 12px;
          color: var(--text-tertiary);
        }
        
        .theme-option-check {
          width: 20px;
          height: 20px;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        .check-dot {
          width: 8px;
          height: 8px;
          border-radius: 50%;
          background: var(--primary-color);
        }
      `}</style>
    </div>
  );
}
