"use client";

import { forwardRef } from "react";
import { cn } from "@/lib/cn";

type Props = React.InputHTMLAttributes<HTMLInputElement> & {
  label?: string;
  hint?: string;
  error?: string;
  leftAdornment?: React.ReactNode;
  rightAdornment?: React.ReactNode;
};

export const Input = forwardRef<HTMLInputElement, Props>(function Input(
  { label, hint, error, leftAdornment, rightAdornment, className, id, ...rest },
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
        {leftAdornment && (
          <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">
            {leftAdornment}
          </span>
        )}
        <input
          id={autoId}
          ref={ref}
          aria-invalid={error ? true : undefined}
          aria-describedby={error ? `${autoId}-error` : hint ? `${autoId}-hint` : undefined}
          className={cn(
            "input",
            error && "input-error",
            leftAdornment && "pl-10",
            rightAdornment && "pr-10",
            className,
          )}
          {...rest}
        />
        {rightAdornment && (
          <span className="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
            {rightAdornment}
          </span>
        )}
      </div>
      {error ? (
        <p id={`${autoId}-error`} className="helper-error">
          {error}
        </p>
      ) : hint ? (
        <p id={`${autoId}-hint`} className="hint">
          {hint}
        </p>
      ) : null}
    </div>
  );
});
