import type {
  ApiEnvelope,
  CrossSellResponse,
  Gamification,
  LoyaltyHistoryItem,
  LoyaltySummary,
  Nudging,
  Offer,
  Paginated,
  ProgramSummary,
  ShadowPortfolio,
  User,
  ZeroClick,
} from '@/types';

const BASE_URL = (import.meta.env.VITE_API_URL as string | undefined) ?? '/api';

class ApiError extends Error {
  status: number;
  constructor(status: number, message: string) {
    super(message);
    this.status = status;
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, params?: Record<string, string | number>): Promise<T> {
  const url = new URL(`${BASE_URL}${path}`, window.location.origin);
  if (params) {
    for (const [k, v] of Object.entries(params)) {
      url.searchParams.set(k, String(v));
    }
  }

  const response = await fetch(url.toString(), {
    headers: { Accept: 'application/json' },
  });

  if (!response.ok) {
    throw new ApiError(response.status, `HTTP ${response.status}: ${path}`);
  }

  return (await response.json()) as T;
}

async function post<T>(path: string): Promise<T> {
  const url = new URL(`${BASE_URL}${path}`, window.location.origin);
  const response = await fetch(url.toString(), {
    method: 'POST',
    headers: { Accept: 'application/json' },
  });
  if (!response.ok) {
    throw new ApiError(response.status, `HTTP ${response.status}: ${path}`);
  }
  return (await response.json()) as T;
}

export const api = {
  getUsers: () => request<ApiEnvelope<User[]>>('/users'),
  getUser: (id: number) => request<ApiEnvelope<User>>(`/users/${id}`),

  getLoyaltySummary: (userId: number) =>
    request<ApiEnvelope<LoyaltySummary>>('/loyalty/summary', { user_id: userId }),
  getLoyaltyHistory: (userId: number, perPage = 20) =>
    request<Paginated<LoyaltyHistoryItem>>('/loyalty/history', { user_id: userId, per_page: perPage }),
  getLoyaltyPrograms: (userId: number) =>
    request<ApiEnvelope<ProgramSummary[]>>('/loyalty/programs', { user_id: userId }),

  getOffers: (userId: number) => request<ApiEnvelope<Offer[]>>('/offers', { user_id: userId }),

  getGamification: (userId: number) => request<ApiEnvelope<Gamification>>(`/gamification/${userId}`),
  recordVisit: (userId: number) => post<ApiEnvelope<Gamification>>(`/gamification/${userId}/visit`),

  getShadowPortfolio: (userId: number) =>
    request<ApiEnvelope<ShadowPortfolio>>(`/ai/shadow-portfolio/${userId}`),
  getDynamicNudging: (userId: number) => request<ApiEnvelope<Nudging>>(`/ai/nudging/${userId}`),
  getCrossSell: (userId: number) => request<ApiEnvelope<CrossSellResponse>>(`/ai/cross-sell/${userId}`),
  getZeroClick: (userId: number, query?: string) =>
    request<ApiEnvelope<ZeroClick>>(
      `/ai/zero-click/${userId}`,
      query && query.trim() ? { query: query.trim() } : undefined,
    ),
} as const;

export { ApiError };
