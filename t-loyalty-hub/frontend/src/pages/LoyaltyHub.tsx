import { useUserStore } from '@/stores/userStore';
import { ErrorBoundary } from '@/components/ui/ErrorBoundary';
import { ThemeToggle } from '@/components/ui/ThemeToggle';
import { GamificationPanel } from '@/components/gamification/GamificationPanel';
import { LoyaltySummaryWidget } from '@/components/loyalty/LoyaltySummaryWidget';
import { LoyaltyHistoryList } from '@/components/loyalty/LoyaltyHistoryList';
import { OffersGrid } from '@/components/loyalty/OffersGrid';
import { ShadowPortfolioCard } from '@/components/ai/ShadowPortfolioCard';
import { NudgingBanner } from '@/components/ai/NudgingBanner';
import { CrossSellCarousel } from '@/components/ai/CrossSellCarousel';
import { ZeroClickNotification } from '@/components/ai/ZeroClickNotification';
import { ZeroClickSearch } from '@/components/ai/ZeroClickSearch';
import { getSegmentTheme } from '@/lib/segmentTheme';

export function LoyaltyHub() {
  const user = useUserStore((s) => s.user)!;
  const clear = useUserStore((s) => s.clear);
  const theme = getSegmentTheme(user.financial_segment);

  return (
    <div className={`min-h-screen transition-colors ${theme.pageBg}`}>
      <header className="sticky top-0 z-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur border-b border-gray-100 dark:border-gray-800">
        <div className="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
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
          <div className="flex items-center gap-2">
            <button
              onClick={clear}
              className="text-xs px-3 py-1.5 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
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
              {theme.label}
            </p>
            <h1 className="mt-1 text-xl sm:text-2xl font-bold leading-tight">{theme.tagline}</h1>
            <p className={`mt-1.5 text-xs sm:text-sm opacity-80`}>
              Персонализация подобрана под твой сегмент. Чем выше тир — тем жирнее офферы.
            </p>
          </div>
        </section>

        <ErrorBoundary>
          <NudgingBanner userId={user.id} />
        </ErrorBoundary>

        <ErrorBoundary>
          <ZeroClickSearch userId={user.id} />
        </ErrorBoundary>

        <ErrorBoundary>
          <ZeroClickNotification userId={user.id} />
        </ErrorBoundary>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
          <ErrorBoundary>
            <GamificationPanel userId={user.id} />
          </ErrorBoundary>

          <ErrorBoundary>
            <LoyaltySummaryWidget userId={user.id} />
          </ErrorBoundary>
        </div>

        <ErrorBoundary>
          <ShadowPortfolioCard userId={user.id} />
        </ErrorBoundary>

        <ErrorBoundary>
          <CrossSellCarousel userId={user.id} />
        </ErrorBoundary>

        <ErrorBoundary>
          <OffersGrid userId={user.id} />
        </ErrorBoundary>

        <ErrorBoundary>
          <LoyaltyHistoryList userId={user.id} />
        </ErrorBoundary>
      </main>

      <footer className="max-w-3xl mx-auto px-4 py-6 text-center">
        <p className="text-xs text-gray-400">T-Loyalty Hub · Demo Mode</p>
      </footer>
    </div>
  );
}
