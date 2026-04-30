import { useEffect } from 'react';
import { useUserStore } from '@/stores/userStore';
import { useViewStore } from '@/stores/viewStore';
import { initTheme } from '@/stores/themeStore';
import { DemoSelector } from '@/pages/DemoSelector';
import { LoyaltyHub } from '@/pages/LoyaltyHub';
import { Analytics } from '@/pages/Analytics';

function App() {
  const user = useUserStore((s) => s.user);
  const view = useViewStore((s) => s.view);

  useEffect(() => {
    initTheme();
  }, []);

  if (!user) return <DemoSelector />;
  return view === 'analytics' ? <Analytics /> : <LoyaltyHub />;
}

export default App;
