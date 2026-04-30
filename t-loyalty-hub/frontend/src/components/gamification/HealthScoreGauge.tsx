import { useId } from 'react';
import { useUserStore } from '@/stores/userStore';
import { getSegmentTheme } from '@/lib/segmentTheme';

interface Props {
  score: number;
  tier: string;
  streakDays: number;
}

export function HealthScoreGauge({ score, tier, streakDays }: Props) {
  const segment = useUserStore((s) => s.user?.financial_segment);
  const theme = getSegmentTheme(segment);
  const gradientId = useId();

  const safeScore = Math.max(0, Math.min(100, score));
  const circumference = 2 * Math.PI * 54;
  const offset = circumference - (safeScore / 100) * circumference;

  return (
    <div
      className={`flex flex-col items-center gap-3 p-6 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border ${theme.cardAccentBorder}`}
    >
      <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
        Loyalty Health
      </h2>

      <div className="relative w-32 h-32">
        <svg viewBox="0 0 120 120" className="w-full h-full -rotate-90">
          <defs>
            <linearGradient id={gradientId} x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stopColor={theme.ringFrom} />
              <stop offset="100%" stopColor={theme.ringTo} />
            </linearGradient>
          </defs>
          <circle cx="60" cy="60" r="54" fill="none" stroke="#e5e7eb" strokeWidth="10" className="dark:stroke-gray-700" />
          <circle
            cx="60"
            cy="60"
            r="54"
            fill="none"
            stroke={`url(#${gradientId})`}
            strokeWidth="10"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            style={{ transition: 'stroke-dashoffset 1s ease-out' }}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-3xl font-bold text-gray-900 dark:text-white">{score}</span>
          <span className="text-xs text-gray-400">/ 100</span>
        </div>
      </div>

      <span className="text-base font-semibold text-gray-800 dark:text-gray-100">{tier}</span>

      {streakDays > 0 && (
        <div className="flex items-center gap-1.5 bg-orange-50 dark:bg-orange-900/30 px-3 py-1.5 rounded-full">
          <span>🔥</span>
          <span className="text-sm font-medium text-orange-600 dark:text-orange-400">
            {streakDays} {pluralize(streakDays, ['день', 'дня', 'дней'])} подряд
          </span>
        </div>
      )}
    </div>
  );
}

function pluralize(n: number, forms: [string, string, string]): string {
  const mod10 = n % 10;
  const mod100 = n % 100;
  if (mod10 === 1 && mod100 !== 11) return forms[0];
  if (mod10 >= 2 && mod10 <= 4 && (mod100 < 10 || mod100 >= 20)) return forms[1];
  return forms[2];
}
