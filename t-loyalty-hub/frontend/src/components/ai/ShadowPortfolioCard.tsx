import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Badge } from '@/components/ui/Badge';
import { api } from '@/api/client';
import { formatRub } from '@/lib/format';
import type { ShadowPortfolio } from '@/types';

interface Props {
  userId: number;
}

export function ShadowPortfolioCard({ userId }: Props) {
  const [data, setData] = useState<ShadowPortfolio | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .getShadowPortfolio(userId)
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading) return <Skeleton className="h-48 w-full" />;
  if (!data) return null;

  const ratio = data.shadow_cashback > 0 ? data.real_cashback / data.shadow_cashback : 0;
  const pct = Math.round(ratio * 100);

  return (
    <Card className="bg-gradient-to-br from-fuchsia-50 to-violet-50 dark:from-fuchsia-900/20 dark:to-violet-900/20 border-fuchsia-100 dark:border-fuchsia-900/40">
      <div className="flex items-start justify-between mb-3">
        <div>
          <h2 className="text-sm font-medium text-fuchsia-700 dark:text-fuchsia-300 uppercase tracking-wide">
            AI Shadow Portfolio
          </h2>
          <p className="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Сравнение с идеальным кэшбэком</p>
        </div>
        {data.is_stub && <Badge tone="demo">AI Demo</Badge>}
      </div>

      {data.is_fallback ? (
        <p className="text-sm text-gray-500 dark:text-gray-400">{data.insight}</p>
      ) : (
        <>
          <div className="grid grid-cols-2 gap-3 mb-3">
            <div>
              <p className="text-xs text-gray-500 dark:text-gray-400">Реальный</p>
              <p className="text-xl font-bold text-gray-900 dark:text-white">{formatRub(data.real_cashback)}</p>
            </div>
            <div>
              <p className="text-xs text-gray-500 dark:text-gray-400">Идеальный</p>
              <p className="text-xl font-bold text-fuchsia-700 dark:text-fuchsia-300">
                {formatRub(data.shadow_cashback)}
              </p>
            </div>
          </div>

          <div className="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mb-3">
            <div
              className="h-full bg-gradient-to-r from-fuchsia-500 to-violet-500 rounded-full transition-all duration-700"
              style={{ width: `${Math.min(100, pct)}%` }}
            />
          </div>

          <p className="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Ты используешь {pct}% потенциала. Упускаешь{' '}
            <span className="font-semibold text-fuchsia-700 dark:text-fuchsia-300">{formatRub(data.gap)}</span>
          </p>

          <p className="text-sm text-gray-700 dark:text-gray-200 italic">{data.insight}</p>
        </>
      )}
    </Card>
  );
}
