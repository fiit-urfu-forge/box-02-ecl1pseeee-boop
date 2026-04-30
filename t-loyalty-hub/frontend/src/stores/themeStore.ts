const STORAGE_KEY = 'tloyalty:theme';

export type Theme = 'light' | 'dark';

export function initTheme(): Theme {
  const saved = (localStorage.getItem(STORAGE_KEY) as Theme | null) ??
    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
  document.documentElement.classList.toggle('dark', saved === 'dark');
  return saved;
}

export function toggleTheme(): Theme {
  const isDark = document.documentElement.classList.toggle('dark');
  const theme: Theme = isDark ? 'dark' : 'light';
  localStorage.setItem(STORAGE_KEY, theme);
  return theme;
}

export function getTheme(): Theme {
  return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}
