import { create } from 'zustand';
import type { User } from '@/types';

interface UserState {
  user: User | null;
  setUser: (user: User | null) => void;
  clear: () => void;
}

const STORAGE_KEY = 'tloyalty:user';

const initial = ((): User | null => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? (JSON.parse(raw) as User) : null;
  } catch {
    return null;
  }
})();

export const useUserStore = create<UserState>((set) => ({
  user: initial,
  setUser: (user) => {
    if (user) {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    } else {
      localStorage.removeItem(STORAGE_KEY);
    }
    set({ user });
  },
  clear: () => {
    localStorage.removeItem(STORAGE_KEY);
    set({ user: null });
  },
}));
