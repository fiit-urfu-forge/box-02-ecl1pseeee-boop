import { create } from "zustand";
import type { UserProfile } from "@/types/api";

type AuthState = {
  user: UserProfile | null;
  status: "unknown" | "authenticated" | "guest";
  setUser: (user: UserProfile | null) => void;
  reset: () => void;
};

/**
 * Holds the currently-authenticated user in memory. The backend is the
 * source of truth — this store is only a local cache so components can
 * render without re-fetching on every navigation.
 */
export const useAuthStore = create<AuthState>((set) => ({
  user: null,
  status: "unknown",
  setUser: (user) => set({ user, status: user ? "authenticated" : "guest" }),
  reset: () => set({ user: null, status: "guest" }),
}));
