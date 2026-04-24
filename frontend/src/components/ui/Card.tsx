import { cn } from "@/lib/cn";

export function Card({
  className,
  ...rest
}: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("card p-6", className)} {...rest} />;
}

export function CardHeader({
  className,
  ...rest
}: React.HTMLAttributes<HTMLDivElement>) {
  return <div className={cn("mb-4 flex items-center justify-between gap-4", className)} {...rest} />;
}

export function CardTitle({
  className,
  ...rest
}: React.HTMLAttributes<HTMLHeadingElement>) {
  return (
    <h2
      className={cn("text-base font-semibold text-slate-900", className)}
      {...rest}
    />
  );
}

export function CardSubtitle({
  className,
  ...rest
}: React.HTMLAttributes<HTMLParagraphElement>) {
  return <p className={cn("text-sm text-slate-500", className)} {...rest} />;
}
