"use client";

import { create } from "zustand";
import { useEffect } from "react";
import { CheckCircle2, XCircle, Info, AlertTriangle, X } from "lucide-react";
import { cn } from "@/lib/cn";

type Variant = "info" | "success" | "warning" | "danger";
type Toast = { id: string; variant: Variant; title: string; body?: string; ttl: number };

type State = {
  items: Toast[];
  push: (t: Omit<Toast, "id" | "ttl"> & { ttl?: number }) => void;
  remove: (id: string) => void;
};

const useToastStore = create<State>((set) => ({
  items: [],
  push: (t) => {
    const id = Math.random().toString(36).slice(2);
    set((s) => ({
      items: [...s.items, { id, ttl: t.ttl ?? 5000, variant: t.variant, title: t.title, body: t.body }],
    }));
  },
  remove: (id) => set((s) => ({ items: s.items.filter((x) => x.id !== id) })),
}));

export const toast = {
  success: (title: string, body?: string) =>
    useToastStore.getState().push({ variant: "success", title, body }),
  error: (title: string, body?: string) =>
    useToastStore.getState().push({ variant: "danger", title, body }),
  info: (title: string, body?: string) =>
    useToastStore.getState().push({ variant: "info", title, body }),
  warning: (title: string, body?: string) =>
    useToastStore.getState().push({ variant: "warning", title, body }),
};

const ICONS = {
  info: <Info className="h-5 w-5 text-brand-600" />,
  success: <CheckCircle2 className="h-5 w-5 text-emerald-600" />,
  warning: <AlertTriangle className="h-5 w-5 text-amber-600" />,
  danger: <XCircle className="h-5 w-5 text-rose-600" />,
} as const;

const RING = {
  info: "ring-brand-200",
  success: "ring-emerald-200",
  warning: "ring-amber-200",
  danger: "ring-rose-200",
} as const;

export function ToastHost() {
  const items = useToastStore((s) => s.items);
  const remove = useToastStore((s) => s.remove);

  return (
    <div
      aria-live="polite"
      className="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4 sm:justify-end sm:pr-6"
    >
      <div className="flex w-full max-w-sm flex-col gap-2">
        {items.map((t) => (
          <ToastItem key={t.id} toast={t} onClose={() => remove(t.id)} />
        ))}
      </div>
    </div>
  );
}

function ToastItem({ toast, onClose }: { toast: Toast; onClose: () => void }) {
  useEffect(() => {
    const t = setTimeout(onClose, toast.ttl);
    return () => clearTimeout(t);
  }, [onClose, toast.ttl]);

  return (
    <div
      className={cn(
        "pointer-events-auto flex items-start gap-3 rounded-2xl bg-white shadow-lift ring-1 ring-inset px-4 py-3 animate-fade-in",
        RING[toast.variant],
      )}
    >
      <div className="mt-0.5 shrink-0">{ICONS[toast.variant]}</div>
      <div className="min-w-0 flex-1">
        <div className="font-medium text-slate-900">{toast.title}</div>
        {toast.body && <div className="mt-0.5 text-sm text-slate-600">{toast.body}</div>}
      </div>
      <button
        type="button"
        aria-label="Закрыть"
        onClick={onClose}
        className="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600"
      >
        <X className="h-4 w-4" />
      </button>
    </div>
  );
}
