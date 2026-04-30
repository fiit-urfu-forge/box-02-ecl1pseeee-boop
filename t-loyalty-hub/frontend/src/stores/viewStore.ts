import { create } from 'zustand';

export type View = 'hub' | 'analytics';

interface ViewState {
  view: View;
  setView: (v: View) => void;
}

export const useViewStore = create<ViewState>((set) => ({
  view: 'hub',
  setView: (view) => set({ view }),
}));
