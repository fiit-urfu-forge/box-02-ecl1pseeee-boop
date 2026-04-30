import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Badge } from '@/components/ui/Badge';
import { api } from '@/api/client';
import { formatRub } from '@/lib/format';
import type { CrossSellResponse } from '@/types';

interface Props {
  userId: number;
}

export function CrossSellCarousel({ userId }: Props) {
  const [data, setData] = useState<CrossSellResponse | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .getCrossSell(userId)
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading) return <Skeleton className="h-40 w-full" />;
  if (!data || !data.items || data.items.length === 0) return null;

  return (
    <Card>
      <div className="flex items-center justify-between mb-3">
        <div>
          <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
            Cross-Sell Optimizer
          </h2>
          <p className="text-xs text-gray-400 mt-0.5">Персональные продукты экосистемы</p>
        </div>
        {data.is_stub && <Badge tone="demo">AI Demo</Badge>}
      </div>

      <div className="flex gap-3 overflow-x-auto -mx-1 px-1 pb-1 snap-x snap-mandatory">
        {data.items.map((it) => (
          <div
            key={it.product_name}
            className="snap-start flex-shrink-0 w-56 rounded-2xl border border-gray-100 dark:border-gray-700 p-4 bg-gray-50 dark:bg-gray-700/40"
          >
            <p className="text-sm font-semibold text-gray-900 dark:text-white">{it.product_name}</p>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-3">{it.reason}</p>
            <p className="text-base font-bold text-emerald-600 dark:text-emerald-400 mt-3">
              +{formatRub(it.potential_gain)} / мес
            </p>
          </div>
        ))}
      </div>
    </Card>
  );
}
