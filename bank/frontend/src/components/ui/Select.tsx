"use client";

import { forwardRef } from "react";
import { ChevronDown } from "lucide-react";
import { cn } from "@/lib/cn";

type Props = React.SelectHTMLAttributes<HTMLSelectElement> & {
  label?: string;
  hint?: string;
  error?: string;
  options: Array<{ value: string; label: string }>;
};

export const Select = forwardRef<HTMLSelectElement, Props>(function Select(
  { label, hint, error, options, className, id, ...rest },
  ref,
) {
  const autoId = id ?? rest.name ?? Math.random().toString(36).slice(2);
  return (
    <div>
      {label && (
        <label htmlFor={autoId} className="label">
          {label}
        </label>
      )}
      <div className="relative">
        <select
          id={autoId}
          ref={ref}
          aria-invalid={error ? true : undefined}
          className={cn("input appearance-none pr-10", error && "input-error", className)}
          {...rest}
        >
          {options.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        <ChevronDown className="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
      </div>
      {error ? (
        <p className="helper-error">{error}</p>
      ) : hint ? (
        <p className="hint">{hint}</p>
      ) : null}
    </div>
  );
});
