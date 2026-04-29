import axios, { AxiosError, AxiosRequestConfig } from "axios";
import type { ApiError, ApiResponse, ApiSuccess } from "@/types/api";
import { messageFor } from "./error-messages";

// When NEXT_PUBLIC_API_URL is a relative path ("/api") we want axios to
// hit the same origin the SPA is served from — matters for Codespaces
// forwarded https URLs, where `localhost` isn't reachable from the browser.
const API_BASE = process.env.NEXT_PUBLIC_API_URL || "/api";
const APP_BASE = process.env.NEXT_PUBLIC_APP_URL || "";

/**
 * Shared axios instance. Sanctum stateful mode requires:
 *   - `withCredentials: true` so the session cookie round-trips
 *   - a CSRF-cookie prime request before any mutating call
 *
 * All mutating calls that SPEC marks as idempotent (§7.2) must carry
 * an `X-Idempotency-Key` header; use `newIdempotencyKey()` per form mount.
 */
export const http = axios.create({
  baseURL: API_BASE,
  withCredentials: true,
  timeout: 20_000,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
    "X-Requested-With": "XMLHttpRequest",
  },
});

let csrfPrimed = false;

/**
 * Sanctum's CSRF endpoint sets the `XSRF-TOKEN` cookie. Axios then
 * auto-forwards it as `X-XSRF-TOKEN` on every subsequent request.
 * Call once per session before the first write.
 */
export async function primeCsrf(): Promise<void> {
  if (csrfPrimed) return;
  await axios.get(`${APP_BASE}/sanctum/csrf-cookie`, { withCredentials: true });
  csrfPrimed = true;
}

/** Re-arm CSRF priming after logout. */
export function resetCsrf(): void {
  csrfPrimed = false;
}

export class ApiException extends Error {
  constructor(
    public readonly code: string,
    public readonly userMessage: string,
    public readonly status: number,
    public readonly details?: Record<string, unknown>,
  ) {
    super(userMessage);
    this.name = "ApiException";
  }
}

function unwrap<T>(resp: ApiResponse<T>): T {
  if (resp.success) return resp.data;
  throw new ApiException(
    resp.error.code,
    messageFor(resp.error.code, resp.error.message),
    0,
    resp.error.details,
  );
}

function toApiException(err: unknown): ApiException {
  if (axios.isAxiosError(err)) {
    const e = err as AxiosError<ApiError>;
    const body = e.response?.data;
    if (body && typeof body === "object" && "error" in body) {
      return new ApiException(
        body.error.code,
        messageFor(body.error.code, body.error.message),
        e.response?.status ?? 0,
        body.error.details,
      );
    }
    if (e.response?.status === 419) {
      csrfPrimed = false;
      return new ApiException("FORBIDDEN", "Сессия истекла. Обновите страницу.", 419);
    }
    if (e.code === "ECONNABORTED") {
      return new ApiException(
        "INTERNAL_ERROR",
        "Превышено время ожидания ответа. Попробуйте ещё раз.",
        0,
      );
    }
    return new ApiException(
      "INTERNAL_ERROR",
      "Не удалось связаться с сервером.",
      e.response?.status ?? 0,
    );
  }
  if (err instanceof ApiException) return err;
  return new ApiException("INTERNAL_ERROR", "Неизвестная ошибка.", 0);
}

async function request<T>(cfg: AxiosRequestConfig, mutating = false): Promise<T> {
  if (mutating) await primeCsrf();
  try {
    const resp = await http.request<ApiResponse<T>>(cfg);
    return unwrap(resp.data);
  } catch (err) {
    throw toApiException(err);
  }
}

export function newIdempotencyKey(): string {
  return typeof crypto !== "undefined" && "randomUUID" in crypto
    ? crypto.randomUUID()
    : // Fallback — vanishingly unlikely outside tests.
      `${Date.now().toString(16)}-${Math.random().toString(16).slice(2)}`;
}

// ------------------------------------------------------------------
// High-level helpers used by pages. Keep the surface narrow.
// ------------------------------------------------------------------

export const api = {
  // ---------- auth
  async register(input: {
    first_name: string;
    last_name: string;
    email: string;
    password: string;
    phone?: string;
  }) {
    return request<{ id: string; email: string }>(
      { method: "post", url: "/auth/register", data: input },
      true,
    );
  },
  async login(input: { email: string; password: string }) {
    return request<any>({ method: "post", url: "/auth/login", data: input }, true);
  },
  async logout() {
    const r = await request<{ logged_out: boolean }>(
      { method: "post", url: "/auth/logout" },
      true,
    );
    resetCsrf();
    return r;
  },
  async logoutAll() {
    const r = await request<{ logged_out: boolean }>(
      { method: "post", url: "/auth/logout-all" },
      true,
    );
    resetCsrf();
    return r;
  },

  // ---------- profile
  async profile() {
    return request<any>({ method: "get", url: "/user/profile" });
  },
  async updateProfile(input: { first_name?: string; last_name?: string; phone?: string | null }) {
    return request<any>(
      { method: "patch", url: "/user/profile", data: input },
      true,
    );
  },
  async uploadAvatar(file: File) {
    await primeCsrf();
    const form = new FormData();
    form.append("avatar", file);
    try {
      const resp = await http.request<ApiResponse<any>>({
        method: "post",
        url: "/user/avatar",
        data: form,
        headers: { "Content-Type": "multipart/form-data" },
      });
      return unwrap(resp.data);
    } catch (err) {
      throw toApiException(err);
    }
  },

  // ---------- accounts
  async listAccounts() {
    return request<any[]>({ method: "get", url: "/accounts" });
  },
  async createAccount(input: { currency: "RUB" | "USD"; type: "checking" | "savings" }) {
    return request<any>(
      {
        method: "post",
        url: "/accounts",
        data: input,
        headers: { "X-Idempotency-Key": newIdempotencyKey() },
      },
      true,
    );
  },
  async getAccount(id: string) {
    return request<any>({ method: "get", url: `/accounts/${id}` });
  },

  // ---------- transfers
  async listTransfers(page = 1, perPage = 20) {
    await primeCsrf();
    try {
      const resp = await http.get<ApiResponse<any[]>>(`/transfers`, {
        params: { page, per_page: perPage },
      });
      if (!resp.data.success) throw new Error("unreachable");
      return { data: resp.data.data, pagination: resp.data.pagination! };
    } catch (err) {
      throw toApiException(err);
    }
  },
  async createTransfer(
    idempotencyKey: string,
    input: {
      sender_account_id: string;
      receiver_account_number?: string;
      receiver_account_id?: string;
      amount: string;
      description?: string;
    },
  ) {
    return request<any>(
      {
        method: "post",
        url: "/transfers",
        data: input,
        headers: { "X-Idempotency-Key": idempotencyKey },
      },
      true,
    );
  },
  async getTransfer(id: string) {
    return request<any>({ method: "get", url: `/transfers/${id}` });
  },

  // ---------- SBP (MVP stub)
  async linkPhone(input: { phone: string; account_id: string }) {
    return request<any>(
      {
        method: "post",
        url: "/sbp/link-phone",
        data: input,
        headers: { "X-Idempotency-Key": newIdempotencyKey() },
      },
      true,
    );
  },
  async sbpTransfer(
    idempotencyKey: string,
    input: {
      sender_account_id: string;
      receiver_phone: string;
      amount: string;
      description?: string;
    },
  ) {
    return request<any>(
      {
        method: "post",
        url: "/sbp/transfer",
        data: input,
        headers: { "X-Idempotency-Key": idempotencyKey },
      },
      true,
    );
  },
};
