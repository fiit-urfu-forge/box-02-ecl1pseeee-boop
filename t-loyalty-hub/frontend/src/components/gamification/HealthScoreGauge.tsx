interface Props {
  score: number;
  tier: string;
  streakDays: number;
}

export function HealthScoreGauge({ score, tier, streakDays }: Props) {
  const circumference = 2 * Math.PI * 54;
  const offset = circumference - (Math.max(0, Math.min(100, score)) / 100) * circumference;

  const scoreColor = score >= 80 ? '#10b981' : score >= 50 ? '#f59e0b' : '#94a3b8';

  return (
    <div className="flex flex-col items-center gap-3 p-6 bg-white dark:bg-gray-800 rounded-3xl shadow-sm border border-gray-100 dark:border-gray-700">
      <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
        Loyalty Health
      </h2>

      <div className="relative w-32 h-32">
        <svg viewBox="0 0 120 120" className="w-full h-full -rotate-90">
          <circle cx="60" cy="60" r="54" fill="none" stroke="#e5e7eb" strokeWidth="10" className="dark:stroke-gray-700" />
          <circle
            cx="60"
            cy="60"
            r="54"
            fill="none"
            stroke={scoreColor}
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
