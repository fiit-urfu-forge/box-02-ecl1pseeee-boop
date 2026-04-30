import { useEffect, useState } from 'react';
import { api } from '@/api/client';
import { useUserStore } from '@/stores/userStore';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import type { User } from '@/types';

const SEGMENT_COLORS: Record<string, string> = {
  LOW: 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
  MEDIUM: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
  HIGH: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-200',
};

export function DemoSelector() {
  const setUser = useUserStore((s) => s.setUser);
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [filter, setFilter] = useState<string>('ALL');

  useEffect(() => {
    api
      .getUsers()
      .then((res) => setUsers(res.data))
      .catch(() => setError('Не удалось загрузить пользователей. Проверьте, что бэкенд запущен и CSV импортирован.'))
      .finally(() => setLoading(false));
  }, []);

  const filtered = filter === 'ALL' ? users : users.filter((u) => u.financial_segment === filter);

  return (
    <div className="min-h-screen flex flex-col">
      <header className="flex items-center justify-between px-4 py-3 max-w-2xl w-full mx-auto">
        <p className="text-sm text-gray-500 dark:text-gray-400">T-Loyalty Hub · Demo</p>
        <ThemeToggle />
      </header>

      <main className="flex-1 flex flex-col items-center justify-start p-4 max-w-2xl w-full mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 dark:text-white mt-4 mb-1 text-center">
          Выберите тестового пользователя
        </h1>
        <p className="text-sm text-gray-500 dark:text-gray-400 mb-6 text-center">
          Демонстрация раздела лояльности экосистемы Т-Банка
        </p>

        <div className="flex gap-2 mb-4 flex-wrap justify-center">
          {(['ALL', 'LOW', 'MEDIUM', 'HIGH'] as const).map((s) => (
            <button
              key={s}
              onClick={() => setFilter(s)}
              className={`px-3 py-1.5 rounded-full text-sm font-medium transition-colors ${
                filter === s
                  ? 'bg-gray-900 text-white dark:bg-white dark:text-gray-900'
                  : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 border border-gray-200 dark:border-gray-700'
              }`}
            >
              {s === 'ALL' ? 'Все' : s}
            </button>
          ))}
        </div>

        {loading && <p className="text-gray-500 dark:text-gray-400">Загрузка...</p>}
        {error && (
          <div className="rounded-2xl bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 p-4 text-sm text-red-800 dark:text-red-200 max-w-md">
            {error}
          </div>
        )}

        {!loading && !error && (
          <div className="grid gap-3 w-full">
            {filtered.map((user) => (
              <button
                key={user.id}
                onClick={() => setUser(user)}
                className="flex items-center justify-between bg-white dark:bg-gray-800 rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow text-left border border-gray-100 dark:border-gray-700"
              >
                <div className="min-w-0 flex-1">
                  <p className="font-semibold text-gray-900 dark:text-white truncate">{user.full_name}</p>
                  <p className="text-xs text-gray-400 mt-0.5 truncate">{user.email}</p>
                </div>
                <span className={`text-xs font-medium px-2.5 py-1 rounded-full ml-3 ${SEGMENT_COLORS[user.financial_segment]}`}>
                  {user.financial_segment}
                </span>
              </button>
            ))}
            {filtered.length === 0 && (
              <p className="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
                Нет пользователей с этим сегментом
              </p>
            )}
          </div>
        )}
      </main>
    </div>
  );
}
