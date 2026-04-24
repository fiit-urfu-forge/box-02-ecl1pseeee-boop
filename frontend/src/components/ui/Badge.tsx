import { cn } from "@/lib/cn";

type Variant =
  | "neutral"
  | "success"
  | "warning"
  | "danger"
  | "info"
  | "pending";

const CLASSES: Record<Variant, string> = {
  neutral: "bg-slate-100 text-slate-700 ring-slate-200",
  success: "bg-emerald-50 text-emerald-700 ring-emerald-200",
  warning: "bg-amber-50 text-amber-700 ring-amber-200",
  danger: "bg-rose-50 text-rose-700 ring-rose-200",
  info: "bg-brand-50 text-brand-700 ring-brand-200",
  pending: "bg-slate-50 text-slate-600 ring-slate-200",
};

export function Badge({
  variant = "neutral",
  className,
  children,
}: {
  variant?: Variant;
  className?: string;
  children: React.ReactNode;
}) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset",
        CLASSES[variant],
        className,
      )}
    >
      {children}
    </span>
  );
}
