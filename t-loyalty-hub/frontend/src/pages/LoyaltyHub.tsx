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

export function LoyaltyHub() {
  const user = useUserStore((s) => s.user)!;
  const clear = useUserStore((s) => s.clear);

  return (
    <div className="min-h-screen">
      <header className="sticky top-0 z-10 bg-white/80 dark:bg-gray-900/80 backdrop-blur border-b border-gray-100 dark:border-gray-800">
        <div className="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
          <div className="min-w-0">
            <p className="text-xs text-gray-500 dark:text-gray-400">T-Loyalty Hub</p>
            <p className="text-sm font-semibold text-gray-900 dark:text-white truncate">{user.full_name}</p>
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
        <ErrorBoundary>
          <NudgingBanner userId={user.id} />
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
