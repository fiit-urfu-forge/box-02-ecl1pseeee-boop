import { cn } from "@/lib/cn";

export function EmptyState({
  icon,
  title,
  body,
  action,
  className,
}: {
  icon?: React.ReactNode;
  title: string;
  body?: string;
  action?: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex flex-col items-center justify-center rounded-2xl border-2 border-dashed border-slate-200 bg-white/50 px-6 py-12 text-center",
        className,
      )}
    >
      {icon && <div className="mb-3 text-slate-400">{icon}</div>}
      <h3 className="text-base font-semibold text-slate-900">{title}</h3>
      {body && <p className="mt-1 max-w-md text-sm text-slate-500">{body}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
