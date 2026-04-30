import { useState } from 'react';
import { getTheme, toggleTheme } from '@/stores/themeStore';

export function ThemeToggle() {
  const [theme, setTheme] = useState(getTheme());

  return (
    <button
      type="button"
      onClick={() => setTheme(toggleTheme())}
      className="rounded-full bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-2 shadow-sm hover:shadow-md transition-shadow"
      aria-label="Переключить тему"
    >
      <span className="text-lg">{theme === 'dark' ? '☀️' : '🌙'}</span>
    </button>
  );
}
