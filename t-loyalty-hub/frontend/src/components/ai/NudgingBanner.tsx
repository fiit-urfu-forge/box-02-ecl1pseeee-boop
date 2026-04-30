import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { api } from '@/api/client';
import type { Nudging } from '@/types';

interface Props {
  userId: number;
}

export function NudgingBanner({ userId }: Props) {
  const [data, setData] = useState<Nudging | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .getDynamicNudging(userId)
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading) return <Skeleton className="h-20 w-full" />;
  if (!data?.message) return null;

  return (
    <div className="rounded-3xl bg-gradient-to-r from-amber-50 to-orange-50 dark:from-amber-900/30 dark:to-orange-900/30 border border-amber-200 dark:border-amber-800 p-4 flex items-start gap-3">
      <div className="text-2xl">⚡</div>
      <div className="flex-1">
        <div className="flex items-center justify-between mb-1">
          <p className="text-xs font-medium text-amber-800 dark:text-amber-300 uppercase tracking-wide">
            Dynamic Nudging
          </p>
          {data.is_stub && <Badge tone="demo">AI Demo</Badge>}
        </div>
        <p className="text-sm text-amber-900 dark:text-amber-100">{data.message}</p>
        {data.boost_multiplier > 1 && (
          <p className="text-xs text-amber-700 dark:text-amber-300 mt-1">
            ×{data.boost_multiplier} баллов · {data.trigger_time}
          </p>
        )}
      </div>
    </div>
  );
}
