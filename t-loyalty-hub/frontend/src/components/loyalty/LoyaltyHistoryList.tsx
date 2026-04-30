import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { api } from '@/api/client';
import { formatByCurrency, formatDate } from '@/lib/format';
import type { LoyaltyHistoryItem } from '@/types';

interface Props {
  userId: number;
}

export function LoyaltyHistoryList({ userId }: Props) {
  const [items, setItems] = useState<LoyaltyHistoryItem[] | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .getLoyaltyHistory(userId, 15)
      .then((res) => setItems(res.data))
      .catch(() => setItems([]))
      .finally(() => setLoading(false));
  }, [userId]);

  return (
    <Card>
      <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
        История выплат
      </h2>

      {loading && (
        <div className="space-y-2">
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} className="h-10 w-full" />
          ))}
        </div>
      )}

      {!loading && items && items.length === 0 && (
        <p className="text-sm text-gray-500 dark:text-gray-400">Истории пока нет</p>
      )}

      {!loading && items && items.length > 0 && (
        <ul className="divide-y divide-gray-100 dark:divide-gray-700">
          {items.map((it) => (
            <li key={it.transaction_id} className="flex items-center justify-between py-2.5">
              <div>
                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                  {it.program?.loyalty_program_name ?? 'Программа'}
                </p>
                <p className="text-xs text-gray-400">{formatDate(it.payout_date)}</p>
              </div>
              <p className="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                +{formatByCurrency(it.cashback_amount, it.program?.cashback_currency ?? 'RUB')}
              </p>
            </li>
          ))}
        </ul>
      )}
    </Card>
  );
}
