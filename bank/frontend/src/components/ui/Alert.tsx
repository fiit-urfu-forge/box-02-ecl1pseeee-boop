import { AlertTriangle, CheckCircle2, Info, XCircle } from "lucide-react";
import { cn } from "@/lib/cn";

type Variant = "info" | "success" | "warning" | "danger";

const CLASSES: Record<Variant, { container: string; icon: React.ReactNode }> = {
  info: {
    container: "bg-brand-50 text-brand-900 ring-brand-200",
    icon: <Info className="h-5 w-5 text-brand-600" />,
  },
  success: {
    container: "bg-emerald-50 text-emerald-900 ring-emerald-200",
    icon: <CheckCircle2 className="h-5 w-5 text-emerald-600" />,
  },
  warning: {
    container: "bg-amber-50 text-amber-900 ring-amber-200",
    icon: <AlertTriangle className="h-5 w-5 text-amber-600" />,
  },
  danger: {
    container: "bg-rose-50 text-rose-900 ring-rose-200",
    icon: <XCircle className="h-5 w-5 text-rose-600" />,
  },
};

export function Alert({
  variant = "info",
  title,
  children,
  className,
}: {
  variant?: Variant;
  title?: string;
  children?: React.ReactNode;
  className?: string;
}) {
  const cls = CLASSES[variant];
  return (
    <div
      role="alert"
      className={cn(
        "flex gap-3 rounded-2xl ring-1 ring-inset px-4 py-3 animate-fade-in",
        cls.container,
        className,
      )}
    >
      <div className="mt-0.5 shrink-0">{cls.icon}</div>
      <div className="min-w-0 flex-1">
        {title && <div className="font-medium">{title}</div>}
        {children && <div className="text-sm">{children}</div>}
      </div>
    </div>
  );
}
