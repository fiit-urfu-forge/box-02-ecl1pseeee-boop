import { useEffect } from 'react';
import { useUserStore } from '@/stores/userStore';
import { initTheme } from '@/stores/themeStore';
import { DemoSelector } from '@/pages/DemoSelector';
import { LoyaltyHub } from '@/pages/LoyaltyHub';

function App() {
  const user = useUserStore((s) => s.user);

  useEffect(() => {
    initTheme();
  }, []);

  return user ? <LoyaltyHub /> : <DemoSelector />;
}

export default App;
