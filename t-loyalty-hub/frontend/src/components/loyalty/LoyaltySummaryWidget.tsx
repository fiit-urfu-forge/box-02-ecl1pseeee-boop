import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { ProgramBadge } from '@/components/loyalty/ProgramBadge';
import { api } from '@/api/client';
import { formatByCurrency, formatRub } from '@/lib/format';
import type { LoyaltySummary } from '@/types';

interface Props {
  userId: number;
}

export function LoyaltySummaryWidget({ userId }: Props) {
  const [summary, setSummary] = useState<LoyaltySummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    setLoading(true);
    setError(null);
    api
      .getLoyaltySummary(userId)
      .then((res) => setSummary(res.data))
      .catch(() => setError('Не удалось загрузить сводку'))
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading) {
    return (
      <Card>
        <Skeleton className="h-6 w-32 mb-4" />
        <Skeleton className="h-12 w-48 mb-3" />
        <Skeleton className="h-4 w-full" />
      </Card>
    );
  }

  if (error || !summary) {
    return <Card><p className="text-red-500 text-sm">{error ?? 'Нет данных'}</p></Card>;
  }

  const { totals, by_currency, programs } = summary;

  return (
    <Card>
      <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">
        Кэшбэк за всё время
      </h2>
      <p className="text-3xl font-bold text-gray-900 dark:text-white mb-4">
        {formatRub(totals.rub)}
      </p>

      <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-5">
        {by_currency.map((c) => (
          <div key={c.currency} className="rounded-2xl bg-gray-50 dark:bg-gray-700/50 p-3">
            <p className="text-xs text-gray-500 dark:text-gray-400">{c.currency}</p>
            <p className="text-base font-semibold text-gray-900 dark:text-white mt-0.5">
              {formatByCurrency(c.total, c.currency)}
            </p>
            <p className="text-xs text-gray-400 mt-1">{c.transactions} операций</p>
          </div>
        ))}
      </div>

      <div className="border-t border-gray-100 dark:border-gray-700 pt-3 space-y-2">
        <p className="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Программы</p>
        {programs.map((p) => (
          <div key={p.account_id} className="flex items-center justify-between">
            <ProgramBadge name={p.loyalty_program_name} currency={p.cashback_currency} />
            <span className="text-sm font-medium text-gray-700 dark:text-gray-200">
              {formatByCurrency(p.current_balance, p.cashback_currency)}
            </span>
          </div>
        ))}
      </div>
    </Card>
  );
}
