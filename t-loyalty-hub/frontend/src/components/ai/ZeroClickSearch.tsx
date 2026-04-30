import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/Badge';
import { api } from '@/api/client';
import { ApiError } from '@/api/client';
import type { ZeroClick } from '@/types';

interface Props {
  userId: number;
}

const PRESETS: ReadonlyArray<{ label: string; query: string; hint: string }> = [
  { label: 'заказать пиццу', query: 'заказать пиццу', hint: 'коммерческий' },
  { label: 'купить кроссовки', query: 'купить кроссовки nike', hint: 'коммерческий' },
  { label: 'почистить ковёр', query: 'почистить ковёр', hint: 'коммерческий' },
  { label: 'как починить кран', query: 'как починить кран', hint: 'информационный' },
  { label: 'арендовать яхту в Монако', query: 'арендовать яхту в Монако', hint: 'нет в каталоге' },
];

const DEBOUNCE_MS = 500;
const MIN_QUERY_LENGTH = 3;

export function ZeroClickSearch({ userId }: Props) {
  const [query, setQuery] = useState('');
  const [data, setData] = useState<ZeroClick | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const reqIdRef = useRef(0);

  useEffect(() => {
    const trimmed = query.trim();
    if (trimmed.length < MIN_QUERY_LENGTH) {
      setData(null);
      setError(null);
      setLoading(false);
      return;
    }

    const myReqId = ++reqIdRef.current;
    setLoading(true);
    const handle = window.setTimeout(() => {
      api
        .getZeroClick(userId, trimmed)
        .then((res) => {
          if (reqIdRef.current !== myReqId) return;
          setData(res.data);
          setError(null);
        })
        .catch((e) => {
          if (reqIdRef.current !== myReqId) return;
          setError(e instanceof ApiError ? `Ошибка ${e.status}` : 'Сбой сети');
          setData(null);
        })
        .finally(() => {
          if (reqIdRef.current === myReqId) setLoading(false);
        });
    }, DEBOUNCE_MS);

    return () => window.clearTimeout(handle);
  }, [query, userId]);

  return (
    <div className="rounded-3xl bg-white dark:bg-gray-800 border border-gray-100 dark:border-gray-700 shadow-sm p-4 space-y-3">
      <div className="flex items-center justify-between gap-2">
        <div>
          <p className="text-[10px] font-bold tracking-[0.2em] uppercase text-fuchsia-600 dark:text-fuchsia-400">
            Zero-Click · Глобальный поиск
          </p>
          <p className="text-sm font-semibold text-gray-900 dark:text-white">
            Что ищешь? Кэшбэк подключим сами
          </p>
        </div>
        <Badge tone="info">AI-фоном</Badge>
      </div>

      <div className="relative">
        <span className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
        <input
          type="text"
          value={query}
          onChange={(e) => setQuery(e.target.value)}
          placeholder="например: заказать суши, авиабилеты, как починить кран..."
          className="w-full pl-9 pr-9 py-2.5 rounded-2xl bg-gray-50 dark:bg-gray-900 border border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-fuchsia-500/40"
        />
        {query && (
          <button
            type="button"
            aria-label="Очистить"
            onClick={() => setQuery('')}
            className="absolute right-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700"
          >
            ×
          </button>
        )}
      </div>

      <div className="flex flex-wrap gap-1.5">
        {PRESETS.map((p) => (
          <button
            key={p.label}
            type="button"
            onClick={() => setQuery(p.query)}
            className="text-xs px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-700/60 text-gray-700 dark:text-gray-200 hover:bg-fuchsia-100 dark:hover:bg-fuchsia-900/40 transition-colors"
            title={p.hint}
          >
            {p.label}
          </button>
        ))}
      </div>

      <ZeroClickResult query={query} loading={loading} error={error} data={data} />
    </div>
  );
}

interface ResultProps {
  query: string;
  loading: boolean;
  error: string | null;
  data: ZeroClick | null;
}

function ZeroClickResult({ query, loading, error, data }: ResultProps) {
  if (query.trim().length < MIN_QUERY_LENGTH) {
    return (
      <p className="text-xs text-gray-400 dark:text-gray-500 italic">
        Введи минимум {MIN_QUERY_LENGTH} символа — нейросеть определит интент и сама подберёт оффер.
      </p>
    );
  }

  if (loading) {
    return (
      <div className="rounded-2xl bg-gray-50 dark:bg-gray-900 p-3 text-xs text-gray-500 dark:text-gray-400 flex items-center gap-2">
        <span className="inline-block w-3 h-3 rounded-full bg-fuchsia-500 animate-pulse" />
        mDeBERTa классифицирует интент, MiniLM ищет лучший оффер...
      </div>
    );
  }

  if (error) {
    return (
      <div className="rounded-2xl bg-rose-50 dark:bg-rose-900/30 border border-rose-200 dark:border-rose-800 p-3 text-xs text-rose-700 dark:text-rose-300">
        {error}
      </div>
    );
  }

  if (!data) return null;

  const intent = data.intent ?? (data.activated_offer ? 'COMMERCIAL' : 'INFORMATIONAL');

  if (intent === 'COMMERCIAL' && data.activated_offer) {
    return (
      <div className="rounded-2xl bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 p-3 flex items-start gap-3">
        <div className="text-2xl">✨</div>
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2 mb-1">
            <p className="text-[10px] font-bold tracking-wider uppercase text-emerald-700 dark:text-emerald-300">
              Кэшбэк активирован автоматически
            </p>
            {data.is_stub && <Badge tone="demo">stub</Badge>}
          </div>
          <p className="text-sm font-semibold text-emerald-900 dark:text-emerald-100 truncate">
            {data.activated_offer}
          </p>
          <div className="mt-1.5 flex flex-wrap gap-3 text-[11px] text-emerald-800 dark:text-emerald-200">
            {data.partner_name && <span>Партнёр: <strong>{data.partner_name}</strong></span>}
            {typeof data.match_accuracy === 'number' && (
              <span>Семантическое сходство: <strong>{(data.match_accuracy * 100).toFixed(0)}%</strong></span>
            )}
            {typeof data.probability === 'number' && (
              <span>Уверенность: <strong>{(data.probability * 100).toFixed(0)}%</strong></span>
            )}
          </div>
        </div>
      </div>
    );
  }

  if (intent === 'COMMERCIAL_NO_OFFER') {
    return (
      <div className="rounded-2xl bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 p-3">
        <p className="text-[10px] font-bold tracking-wider uppercase text-amber-700 dark:text-amber-300 mb-1">
          Коммерческий запрос — но партнёра в каталоге пока нет
        </p>
        <p className="text-xs text-amber-800 dark:text-amber-200">
          Ничего не активировано. Семантическое сходство с лучшим оффером:{' '}
          <strong>{((data.match_accuracy ?? 0) * 100).toFixed(0)}%</strong>.
        </p>
      </div>
    );
  }

  return (
    <div className="rounded-2xl bg-sky-50 dark:bg-sky-900/30 border border-sky-200 dark:border-sky-800 p-3">
      <p className="text-[10px] font-bold tracking-wider uppercase text-sky-700 dark:text-sky-300 mb-1">
        Информационный запрос
      </p>
      <p className="text-xs text-sky-800 dark:text-sky-200">
        Это не похоже на покупку — ничего не активируем, чтобы не тратить кэшбэк-окно зря.
      </p>
    </div>
  );
}
