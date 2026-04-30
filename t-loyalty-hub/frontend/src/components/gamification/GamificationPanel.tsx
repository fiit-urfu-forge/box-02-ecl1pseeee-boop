import { useEffect, useState } from 'react';
import { HealthScoreGauge } from '@/components/gamification/HealthScoreGauge';
import { Skeleton } from '@/components/ui/Skeleton';
import { api } from '@/api/client';
import type { Gamification } from '@/types';

interface Props {
  userId: number;
}

export function GamificationPanel({ userId }: Props) {
  const [data, setData] = useState<Gamification | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    api
      .recordVisit(userId)
      .then((res) => setData(res.data))
      .catch(() =>
        api
          .getGamification(userId)
          .then((res) => setData(res.data))
          .catch(() => setData({ health_score: 0, loyalty_tier: 'Новичок', streak_days: 0, last_visit_date: null }))
      )
      .finally(() => setLoading(false));
  }, [userId]);

  if (loading || !data) {
    return <Skeleton className="h-64 w-full" />;
  }

  return <HealthScoreGauge score={data.health_score} tier={data.loyalty_tier} streakDays={data.streak_days} />;
}
