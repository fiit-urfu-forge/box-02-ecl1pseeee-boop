import { Component, type ErrorInfo, type PropsWithChildren, type ReactNode } from 'react';

interface State {
  hasError: boolean;
}

interface Props {
  fallback?: ReactNode;
}

export class ErrorBoundary extends Component<PropsWithChildren<Props>, State> {
  state: State = { hasError: false };

  static getDerivedStateFromError(): State {
    return { hasError: true };
  }

  componentDidCatch(error: Error, info: ErrorInfo): void {
    if (import.meta.env.DEV) {
      console.error('ErrorBoundary caught', error, info);
    }
  }

  render() {
    if (this.state.hasError) {
      return (
        this.props.fallback ?? (
          <div className="rounded-2xl bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 p-4 text-sm text-amber-800 dark:text-amber-200">
            Этот блок временно недоступен. Остальной интерфейс работает.
          </div>
        )
      );
    }
    return this.props.children;
  }
}
