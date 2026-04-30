import { useEffect, useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { Skeleton } from '@/components/ui/Skeleton';
import { api } from '@/api/client';
import type { ZeroClick } from '@/types';

interface Props {
  userId: number;
}

export function ZeroClickNotification({ userId }: Props) {
  const [data, setData] = useState<ZeroClick | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .getZeroClick(userId)
      .then((res) => setData(res.data))
      .catch(() => setData(null))
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading) return <Skeleton className="h-16 w-full" />;
  if (!data?.activated_offer) return null;

  return (
    <div className="rounded-3xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 p-4 flex items-start gap-3">
      <div className="text-2xl">✨</div>
      <div className="flex-1">
        <div className="flex items-center justify-between mb-1">
          <p className="text-xs font-medium text-emerald-800 dark:text-emerald-300 uppercase tracking-wide">
            Zero-Click активация
          </p>
          {data.is_stub && <Badge tone="demo">AI Demo</Badge>}
        </div>
        <p className="text-sm text-emerald-900 dark:text-emerald-100">
          Мы заранее включили: <strong>{data.activated_offer}</strong>
        </p>
        {data.partner_name && (
          <p className="text-xs text-emerald-700 dark:text-emerald-300 mt-1">
            Партнёр: {data.partner_name} · уверенность {(data.probability * 100).toFixed(0)}%
          </p>
        )}
      </div>
    </div>
  );
}
