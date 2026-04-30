import { useEffect, useMemo, useState } from 'react';
import {
  Bar,
  BarChart,
  CartesianGrid,
  Cell,
  Legend,
  Pie,
  PieChart,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
  AreaChart,
  Area,
} from 'recharts';
import { useUserStore } from '@/stores/userStore';
import { useViewStore } from '@/stores/viewStore';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { Card } from '@/components/ui/Card';
import { Skeleton } from '@/components/ui/Skeleton';
import { Badge } from '@/components/ui/Badge';
import { ErrorBoundary } from '@/components/ui/ErrorBoundary';
import { api } from '@/api/client';
import { formatRub } from '@/lib/format';
import { getSegmentTheme } from '@/lib/segmentTheme';
import type { CrossSellItem, LoyaltySummary, Nudging, ShadowPortfolio } from '@/types';

interface AggregateState {
  shadow: ShadowPortfolio | null;
  cross: CrossSellItem[];
  nudge: Nudging | null;
  summary: LoyaltySummary | null;
  loading: boolean;
}

const PIE_COLORS = ['#a855f7', '#0ea5e9', '#f59e0b', '#10b981'];

export function Analytics() {
  const user = useUserStore((s) => s.user)!;
  const setView = useViewStore((s) => s.setView);
  const clearUser = useUserStore((s) => s.clear);
  const theme = getSegmentTheme(user.financial_segment);

  const [state, setState] = useState<AggregateState>({
    shadow: null,
    cross: [],
    nudge: null,
    summary: null,
    loading: true,
  });

  useEffect(() => {
    let alive = true;
    setState((s) => ({ ...s, loading: true }));

    Promise.all([
      api.getShadowPortfolio(user.id).then((r) => r.data).catch(() => null),
      api.getCrossSell(user.id).then((r) => r.data.items).catch(() => [] as CrossSellItem[]),
      api.getDynamicNudging(user.id).then((r) => r.data).catch(() => null),
      api.getLoyaltySummary(user.id).then((r) => r.data).catch(() => null),
    ]).then(([shadow, cross, nudge, summary]) => {
      if (!alive) return;
      setState({ shadow, cross, nudge, summary, loading: false });
    });

    return () => {
      alive = false;
    };
  }, [user.id]);

  const annualReal = state.shadow ? state.shadow.real_cashback * 2 : 0; // история ≈6 мес → ×2 = годовой
  const topCross = state.cross[0]?.potential_gain ?? 0;
  const top2Cross = state.cross.slice(0, 2).reduce((s, c) => s + c.potential_gain, 0);
  const annualTotal = annualReal + topCross * 12;

  return (
    <div className={`min-h-screen transition-colors ${theme.pageBg}`}>
      <header className="sticky top-0 z-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur border-b border-gray-100 dark:border-gray-800">
        <div className="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between gap-3">
          <div className="min-w-0 flex items-center gap-3">
            <div className="min-w-0">
              <p className="text-xs text-gray-500 dark:text-gray-400">T-Loyalty Hub</p>
              <p className="text-sm font-semibold text-gray-900 dark:text-white truncate">{user.full_name}</p>
            </div>
            <span
              className={`text-[10px] font-bold tracking-wider px-2 py-1 rounded-full ${theme.badgeBg} ${theme.badgeText} shrink-0`}
            >
              {theme.tierName}
            </span>
          </div>
          <nav className="flex items-center gap-1 rounded-full bg-gray-100 dark:bg-gray-800 p-1">
            <button
              onClick={() => setView('hub')}
              className="text-xs px-3 py-1.5 rounded-full text-gray-700 dark:text-gray-300 hover:bg-white dark:hover:bg-gray-700"
            >
              Главная
            </button>
            <button
              className="text-xs px-3 py-1.5 rounded-full bg-white dark:bg-gray-700 text-gray-900 dark:text-white shadow-sm font-medium"
            >
              Аналитика
            </button>
          </nav>
          <div className="flex items-center gap-2">
            <button
              onClick={clearUser}
              className="text-xs px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700"
            >
              Сменить
            </button>
            <ThemeToggle />
          </div>
        </div>
      </header>

      <main className="max-w-3xl mx-auto px-4 py-4 space-y-4">
        <section
          className={`relative overflow-hidden rounded-3xl p-5 ${theme.heroBg} ${theme.heroText} shadow-lg ${theme.glow}`}
        >
          <div className="absolute -right-8 -top-8 w-40 h-40 rounded-full bg-white/10 blur-2xl pointer-events-none" />
          <div className="relative">
            <p className={`text-[11px] font-semibold uppercase tracking-[0.2em] ${theme.heroAccentText}`}>
              Аналитика · ИИ-прогноз
            </p>
            <h1 className="mt-1 text-xl sm:text-2xl font-bold leading-tight">
              Твоя выгода в Т-Банке за год
            </h1>
            <p className="mt-2 text-3xl sm:text-4xl font-bold">{formatRub(annualTotal)}</p>
            <p className={`mt-1.5 text-xs sm:text-sm opacity-80`}>
              Прогноз на 12 месяцев = текущий кэшбэк {formatRub(annualReal)} +{' '}
              топ-1 cross-sell {formatRub(topCross * 12)}/год
            </p>
          </div>
        </section>

        {state.loading ? (
          <>
            <Skeleton className="h-72 w-full" />
            <Skeleton className="h-72 w-full" />
            <Skeleton className="h-72 w-full" />
          </>
        ) : (
          <>
            <ErrorBoundary>
              <ShadowComparisonChart shadow={state.shadow} />
            </ErrorBoundary>

            <ErrorBoundary>
              <HealthAndOffersChart shadow={state.shadow} />
            </ErrorBoundary>

            <ErrorBoundary>
              <CrossSellChart cross={state.cross} />
            </ErrorBoundary>

            <ErrorBoundary>
              <ProjectionChart annualReal={annualReal} cross={state.cross} />
            </ErrorBoundary>

            <ErrorBoundary>
              <ProgramDistributionChart summary={state.summary} />
            </ErrorBoundary>

            <ErrorBoundary>
              <NudgingCard nudge={state.nudge} />
            </ErrorBoundary>

            <ROISummary annualReal={annualReal} cross={state.cross} top2Cross={top2Cross} />
          </>
        )}
      </main>

      <footer className="max-w-3xl mx-auto px-4 py-6 text-center">
        <p className="text-xs text-gray-400">T-Loyalty Hub · Analytics</p>
      </footer>
    </div>
  );
}

function ChartTooltipFormatter(value: unknown): [string, string] {
  return [formatRub(Number(value)), ''];
}

function ShadowComparisonChart({ shadow }: { shadow: ShadowPortfolio | null }) {
  if (!shadow) return null;
  const data = [
    { name: 'Реальный', value: shadow.real_cashback, fill: '#94a3b8' },
    { name: 'Идеальный (Shadow)', value: shadow.shadow_cashback, fill: '#a855f7' },
  ];

  return (
    <Card>
      <div className="flex items-start justify-between mb-2">
        <div>
          <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Реальный vs Идеальный кэшбэк</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">Источник: Shadow Portfolio AI</p>
        </div>
        <Badge tone="info">PyTorch MLP</Badge>
      </div>
      <div className="h-56">
        <ResponsiveContainer>
          <BarChart data={data} margin={{ top: 16, right: 16, left: 8, bottom: 8 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(148,163,184,0.2)" />
            <XAxis dataKey="name" tick={{ fontSize: 11, fill: 'currentColor' }} />
            <YAxis tick={{ fontSize: 11, fill: 'currentColor' }} tickFormatter={(v) => `${(v / 1000).toFixed(0)}k`} />
            <Tooltip
              cursor={{ fill: 'rgba(168,85,247,0.08)' }}
              contentStyle={{ borderRadius: 12, border: 'none' }}
              formatter={ChartTooltipFormatter}
            />
            <Bar dataKey="value" radius={[10, 10, 0, 0]}>
              {data.map((entry, i) => (
                <Cell key={i} fill={entry.fill} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
      <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
        Разрыв: <strong className="text-fuchsia-600 dark:text-fuchsia-400">{formatRub(shadow.gap)}</strong> · Health
        Score{' '}
        <strong className="text-gray-900 dark:text-white">{shadow.health_score}/100</strong>
      </p>
    </Card>
  );
}

function HealthAndOffersChart({ shadow }: { shadow: ShadowPortfolio | null }) {
  if (!shadow || !shadow.top_offers || shadow.top_offers.length === 0) return null;
  const data = shadow.top_offers.map((o) => ({
    name: o.partner_name,
    cashback: o.cashback_percent,
    relevance: Math.round(o.relevance * 100),
  }));

  return (
    <Card>
      <div className="flex items-start justify-between mb-2">
        <div>
          <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Топ-3 рекомендованных оффера</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            MiniLM ранжирует партнёров по семантическому соответствию твоему профилю
          </p>
        </div>
        <Badge tone="info">MiniLM</Badge>
      </div>
      <div className="h-56">
        <ResponsiveContainer>
          <BarChart layout="vertical" data={data} margin={{ top: 8, right: 16, left: 24, bottom: 8 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(148,163,184,0.2)" />
            <XAxis type="number" tick={{ fontSize: 11, fill: 'currentColor' }} />
            <YAxis
              type="category"
              dataKey="name"
              tick={{ fontSize: 11, fill: 'currentColor' }}
              width={110}
            />
            <Tooltip
              cursor={{ fill: 'rgba(14,165,233,0.08)' }}
              contentStyle={{ borderRadius: 12, border: 'none' }}
              formatter={(v: unknown, key) => [`${v}${key === 'cashback' ? '%' : '%'}`, key === 'cashback' ? 'Кэшбэк' : 'Релевантность']}
            />
            <Legend wrapperStyle={{ fontSize: 11 }} />
            <Bar dataKey="cashback" name="Кэшбэк %" fill="#a855f7" radius={[0, 8, 8, 0]} />
            <Bar dataKey="relevance" name="Релевантность %" fill="#0ea5e9" radius={[0, 8, 8, 0]} />
          </BarChart>
        </ResponsiveContainer>
      </div>
    </Card>
  );
}

function CrossSellChart({ cross }: { cross: CrossSellItem[] }) {
  if (cross.length === 0) return null;
  const data = cross.map((c) => ({
    name: c.product_name,
    gain: c.potential_gain,
    affinity: c.affinity ? Math.round((c.affinity + 1) * 50) : null,
  }));

  return (
    <Card>
      <div className="flex items-start justify-between mb-2">
        <div>
          <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Прирост от продуктов экосистемы</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Two-tower рекомендер ранжирует продукты Т по предсказанной выгоде ₽/мес
          </p>
        </div>
        <Badge tone="info">Two-Tower NN</Badge>
      </div>
      <div className="h-64">
        <ResponsiveContainer>
          <BarChart data={data} margin={{ top: 16, right: 16, left: 8, bottom: 8 }}>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(148,163,184,0.2)" />
            <XAxis dataKey="name" tick={{ fontSize: 10, fill: 'currentColor' }} angle={-15} textAnchor="end" height={50} />
            <YAxis tick={{ fontSize: 11, fill: 'currentColor' }} tickFormatter={(v) => `${(v / 1000).toFixed(1)}k`} />
            <Tooltip
              cursor={{ fill: 'rgba(168,85,247,0.08)' }}
              contentStyle={{ borderRadius: 12, border: 'none' }}
              formatter={ChartTooltipFormatter}
            />
            <Bar dataKey="gain" name="₽/мес" radius={[10, 10, 0, 0]}>
              {data.map((_, i) => (
                <Cell key={i} fill={i === 0 ? '#a855f7' : i === 1 ? '#c084fc' : i === 2 ? '#d8b4fe' : '#e9d5ff'} />
              ))}
            </Bar>
          </BarChart>
        </ResponsiveContainer>
      </div>
      <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
        Топ: <strong className="text-gray-900 dark:text-white">{cross[0]?.product_name}</strong> —{' '}
        {formatRub(cross[0]?.potential_gain ?? 0)}/мес
      </p>
    </Card>
  );
}

function ProjectionChart({ annualReal, cross }: { annualReal: number; cross: CrossSellItem[] }) {
  const monthlyReal = annualReal / 12;
  const top1 = cross[0]?.potential_gain ?? 0;
  const top2 = cross[1]?.potential_gain ?? 0;
  const data = useMemo(() => {
    const months = ['М1', 'М2', 'М3', 'М4', 'М5', 'М6', 'М7', 'М8', 'М9', 'М10', 'М11', 'М12'];
    let cumReal = 0;
    let cumCross = 0;
    return months.map((m, i) => {
      cumReal += monthlyReal;
      cumCross += i >= 1 ? top1 : 0; // первый месяц на подключение
      const cumCrossPlus = cumCross + (i >= 3 ? top2 * (i - 2) : 0);
      return {
        month: m,
        Базовый: Math.round(cumReal),
        'С Cross-Sell': Math.round(cumReal + cumCross),
        'С Cross-Sell+': Math.round(cumReal + cumCrossPlus),
      };
    });
  }, [monthlyReal, top1, top2]);

  return (
    <Card>
      <div className="flex items-start justify-between mb-2">
        <div>
          <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Кумулятивный прогноз ROI на 12 мес</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">
            Накопительный кэшбэк: текущий поток + поэтапное подключение топ-2 продуктов
          </p>
        </div>
        <Badge tone="info">ИИ-прогноз</Badge>
      </div>
      <div className="h-64">
        <ResponsiveContainer>
          <AreaChart data={data} margin={{ top: 16, right: 16, left: 8, bottom: 8 }}>
            <defs>
              <linearGradient id="g1" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#94a3b8" stopOpacity={0.5} />
                <stop offset="95%" stopColor="#94a3b8" stopOpacity={0.05} />
              </linearGradient>
              <linearGradient id="g2" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#a855f7" stopOpacity={0.6} />
                <stop offset="95%" stopColor="#a855f7" stopOpacity={0.05} />
              </linearGradient>
              <linearGradient id="g3" x1="0" y1="0" x2="0" y2="1">
                <stop offset="5%" stopColor="#10b981" stopOpacity={0.6} />
                <stop offset="95%" stopColor="#10b981" stopOpacity={0.05} />
              </linearGradient>
            </defs>
            <CartesianGrid strokeDasharray="3 3" stroke="rgba(148,163,184,0.2)" />
            <XAxis dataKey="month" tick={{ fontSize: 11, fill: 'currentColor' }} />
            <YAxis tick={{ fontSize: 11, fill: 'currentColor' }} tickFormatter={(v) => `${(v / 1000).toFixed(0)}k`} />
            <Tooltip
              contentStyle={{ borderRadius: 12, border: 'none' }}
              formatter={ChartTooltipFormatter}
            />
            <Legend wrapperStyle={{ fontSize: 11 }} />
            <Area type="monotone" dataKey="Базовый" stroke="#94a3b8" fill="url(#g1)" strokeWidth={2} />
            <Area type="monotone" dataKey="С Cross-Sell" stroke="#a855f7" fill="url(#g2)" strokeWidth={2} />
            <Area type="monotone" dataKey="С Cross-Sell+" stroke="#10b981" fill="url(#g3)" strokeWidth={2} />
          </AreaChart>
        </ResponsiveContainer>
      </div>
      <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
        К 12-му месяцу разница базового и расширенного сценария ~
        <strong className="text-emerald-600 dark:text-emerald-400">
          {formatRub(data[11]['С Cross-Sell+'] - data[11]['Базовый'])}
        </strong>
      </p>
    </Card>
  );
}

function ProgramDistributionChart({ summary }: { summary: LoyaltySummary | null }) {
  if (!summary) return null;
  const data = summary.by_currency
    .filter((c) => c.total > 0)
    .map((c) => ({ name: c.currency.toUpperCase(), value: c.total }));
  if (data.length === 0) return null;

  return (
    <Card>
      <div className="flex items-start justify-between mb-2">
        <div>
          <h2 className="text-sm font-semibold text-gray-900 dark:text-white">Распределение по программам</h2>
          <p className="text-xs text-gray-500 dark:text-gray-400">Какая программа лояльности приносит больше всего</p>
        </div>
      </div>
      <div className="h-56">
        <ResponsiveContainer>
          <PieChart>
            <Pie
              data={data}
              dataKey="value"
              nameKey="name"
              cx="50%"
              cy="50%"
              innerRadius={50}
              outerRadius={80}
              paddingAngle={3}
              label={(entry: { name?: string }) => entry.name ?? ''}
            >
              {data.map((_, i) => (
                <Cell key={i} fill={PIE_COLORS[i % PIE_COLORS.length]} />
              ))}
            </Pie>
            <Tooltip contentStyle={{ borderRadius: 12, border: 'none' }} formatter={ChartTooltipFormatter} />
          </PieChart>
        </ResponsiveContainer>
      </div>
    </Card>
  );
}

function NudgingCard({ nudge }: { nudge: Nudging | null }) {
  if (!nudge || !nudge.message) return null;
  const conf = Math.round((nudge.confidence ?? 0) * 100);
  return (
    <Card className="bg-gradient-to-br from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20 border-amber-100 dark:border-amber-900/40">
      <div className="flex items-start justify-between mb-2">
        <div>
          <h2 className="text-sm font-semibold text-amber-800 dark:text-amber-300">Следующий триггер</h2>
          <p className="text-xs text-amber-700/70 dark:text-amber-400/70">Dynamic Nudging AI: предсказание категории + времени</p>
        </div>
        <Badge tone="warning">×{nudge.boost_multiplier}</Badge>
      </div>
      <p className="text-sm text-gray-800 dark:text-gray-100 mb-3">{nudge.message}</p>
      <div className="grid grid-cols-2 gap-2 text-xs text-gray-600 dark:text-gray-300">
        <div>Категория: <strong>{nudge.category}</strong></div>
        <div>Время: <strong>{nudge.trigger_time}</strong></div>
        {nudge.partner_name && (
          <div className="col-span-2">Партнёр: <strong>{nudge.partner_name}</strong> · {nudge.cashback_percent}%</div>
        )}
      </div>
      {nudge.confidence !== null && nudge.confidence !== undefined && (
        <div className="mt-3">
          <div className="flex justify-between text-[10px] text-amber-700 dark:text-amber-400 mb-1">
            <span>Уверенность модели</span>
            <span>{conf}%</span>
          </div>
          <div className="h-1.5 bg-amber-100 dark:bg-amber-900/40 rounded-full overflow-hidden">
            <div
              className="h-full bg-gradient-to-r from-amber-500 to-orange-500"
              style={{ width: `${conf}%` }}
            />
          </div>
        </div>
      )}
    </Card>
  );
}

function ROISummary({
  annualReal,
  cross,
  top2Cross,
}: {
  annualReal: number;
  cross: CrossSellItem[];
  top2Cross: number;
}) {
  const annualCross = top2Cross * 12;
  const total = annualReal + annualCross;
  return (
    <Card className="bg-gradient-to-br from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 border-emerald-100 dark:border-emerald-900/40">
      <h2 className="text-sm font-semibold text-emerald-800 dark:text-emerald-300 mb-3">Итог: твоя выгода за год</h2>
      <div className="grid grid-cols-3 gap-3 text-center">
        <div>
          <p className="text-[10px] uppercase tracking-wider text-emerald-700/80 dark:text-emerald-400/80">Текущий поток</p>
          <p className="text-lg font-bold text-gray-900 dark:text-white mt-1">{formatRub(annualReal)}</p>
        </div>
        <div>
          <p className="text-[10px] uppercase tracking-wider text-emerald-700/80 dark:text-emerald-400/80">+ Cross-Sell топ-2</p>
          <p className="text-lg font-bold text-emerald-700 dark:text-emerald-300 mt-1">{formatRub(annualCross)}</p>
        </div>
        <div>
          <p className="text-[10px] uppercase tracking-wider text-emerald-700/80 dark:text-emerald-400/80">Итого</p>
          <p className="text-lg font-bold text-emerald-900 dark:text-emerald-100 mt-1">{formatRub(total)}</p>
        </div>
      </div>
      <p className="text-xs text-emerald-700/80 dark:text-emerald-400/80 mt-3">
        Прогноз основан на твоей истории кэшбэка и рекомендациях Cross-Sell AI
        {cross.length > 0 && `: ${cross.slice(0, 2).map((c) => c.product_name).join(' + ')}`}.
      </p>
    </Card>
  );
}
