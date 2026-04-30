import { useEffect, useState } from 'react';
import { Card } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { api } from '@/api/client';
import type { Offer } from '@/types';

interface Props {
  userId: number;
}

export function OffersGrid({ userId }: Props) {
  const [offers, setOffers] = useState<Offer[] | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .getOffers(userId)
      .then((res) => setOffers(res.data))
      .catch(() => setOffers([]))
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading) return <Skeleton className="h-64 w-full" />;
  if (!offers || offers.length === 0) {
    return (
      <Card>
        <p className="text-sm text-gray-500 dark:text-gray-400">Партнёрских предложений пока нет</p>
      </Card>
    );
  }

  return (
    <Card>
      <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
        Партнёрские предложения
      </h2>
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {offers.map((offer) => (
          <div
            key={offer.id}
            className="flex items-center gap-3 rounded-2xl border border-gray-100 dark:border-gray-700 p-3 bg-gray-50 dark:bg-gray-700/40"
          >
            <div
              className="flex-shrink-0 w-12 h-12 rounded-xl flex items-center justify-center text-white font-bold text-lg"
              style={{ backgroundColor: offer.brand_color_hex ?? '#6b7280' }}
              aria-hidden="true"
            >
              {offer.partner_name.charAt(0)}
            </div>
            <div className="flex-1 min-w-0">
              <p className="text-sm font-semibold text-gray-900 dark:text-white truncate">{offer.partner_name}</p>
              <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                {offer.short_description ?? ''}
              </p>
            </div>
            <span className="text-base font-bold text-emerald-600 dark:text-emerald-400 whitespace-nowrap">
              {offer.cashback_percent}%
            </span>
          </div>
        ))}
      </div>
    </Card>
  );
}
