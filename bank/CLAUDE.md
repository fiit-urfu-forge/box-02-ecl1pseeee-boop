# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

DigitalBank — banking web app MVP. Laravel 11 (PHP 8.3) backend + Next.js 14 frontend + PostgreSQL 16 + Redis 7, all wired together via `docker compose`.

Two source-of-truth documents live at the repo root and **must be respected when changing behavior**:

- `Claude_SPEC.docx` — the full functional/technical spec. Code comments cite it as §4.1, §6.2, etc. When a comment says "§3.6", read that section before changing related code.
- `DEVELOPMENT_STAGES.md` — 11-stage roadmap with the artefact each stage already produced (test counts, coverage). Stages 1–10 are done; stage 11 (GitLab CI) is pending.

## Architecture

Seven docker services in `docker-compose.yml`:

- `web` (nginx) is the only externally-exposed service on `:80`. It proxies `/api/*` and `/sanctum/*` to `app:9000` (PHP-FPM) and everything else to `frontend:3000` (Next.js).
- `app` runs Laravel under PHP-FPM. `worker` runs `queue:work` over the same image, consuming queues `transfers,notifications,default` from Redis. `scheduler` runs `schedule:work`.
- `db` is PostgreSQL 16 with `--data-checksums`. `redis` runs with `appendonly yes` and `maxmemory-policy allkeys-lru`.

Auth is **Sanctum stateful** (cookie session, not token). The frontend must `GET /sanctum/csrf-cookie` once before the first mutating call (`primeCsrf()` in `frontend/src/lib/api.ts`), and `resetCsrf()` after logout.

### Backend layout (`backend/app/`)

- `Http/Controllers/{Auth,Accounts,Transfers,Sbp,User}` — thin controllers, one per resource group.
- `Http/Middleware/IdempotencyMiddleware.php` — implements §7.2. Required on `transfers.store`, `sbp.transfer`, `sbp.link-phone`, `accounts.store` (matched by route name, not URL).
- `Http/Requests/` — FormRequests carry **all** business validation (min/max amounts, daily limits, same-currency, self-transfer, etc.).
- `Services/TransferService.php` — the atomic balance-move. Wraps the whole transfer in a single DB transaction, locks both accounts via `SELECT … FOR UPDATE` ordered **by account id ASC** to prevent deadlocks, and uses `Brick\Math\BigDecimal` (never PHP float) for arithmetic.
- `Services/AuditLogger.php` — every financial transition writes one row to `audit_log`. The table is protected by DB triggers against UPDATE/DELETE (set up in migration `2026_04_24_180400_create_audit_log_table.php`).
- `Sbp/{SbpGatewayInterface,MockSbpGateway}` — pluggable gateway, mock used for MVP. Webhook signature is HMAC-SHA256 keyed on `SBP_WEBHOOK_SECRET`.
- `Support/{ApiResponse,ErrorCode}` — single source of truth for the API envelope (§6.1). `ApiResponse::fromException` is wired into the global exception handler so controllers never wrap errors manually.
- `Logging/JsonFormatterFactory.php` — structured JSON tap. Bound to channels `transfers`, `auth`, `accounts`, `security` (config in `backend/config/logging.php`).
- `Console/Commands/CleanupIdempotencyKeys.php` — runs hourly via `app/Console/Kernel`-style scheduler registration; TTL is 24h by default.

### Frontend layout (`frontend/src/`)

- `app/(public)/` — `/login`, `/register`, `/verify-email` (no auth gate).
- `app/(app)/` — gated routes: `/dashboard`, `/accounts*`, `/transfers*`, `/sbp`, `/profile`. Layout enforces auth + email verification.
- `lib/api.ts` — single axios instance, `withCredentials: true`. `messageFor(code)` in `lib/error-messages.ts` maps every §6.2 error code to Russian human-readable copy.
- `stores/auth.ts` — Zustand. React Query owns server state (accounts, transfers); the dashboard polls balance every 30s and a `pending`/`processing` transfer detail polls every 4s.
- `types/api.ts` — TypeScript mirror of §6 envelope and resources.

`X-Idempotency-Key` is generated once with `crypto.randomUUID()` per form mount and reset only on success — replaying after a network blip is intentional.

### `NEXT_PUBLIC_API_URL` gotcha

Default is `/api` (same-origin via nginx). Empty/relative is correct for both `localhost` and Codespaces forwarded HTTPS URLs. Setting it to `http://localhost/api` will break the browser session inside Codespaces because `localhost` isn't reachable from the user's machine. Use `${VAR-default}` (single dash) in `docker-compose.yml`, not `${VAR:-default}`, so an explicitly-empty value passes through.

## Common commands

All app commands run inside containers — the host has no PHP/Composer.

```bash
# Bring stack up / down (data persists in volumes)
docker compose up --build -d
docker compose ps
docker compose down

# Backend
docker compose exec app php artisan migrate
docker compose exec app php artisan migrate:fresh --seed
docker compose exec app php artisan key:generate          # if APP_KEY missing
docker compose exec app composer install
docker compose exec app ./vendor/bin/pint                  # format
docker compose exec app ./vendor/bin/phpstan analyse       # larastan L6 (per stage 11 plan)

# Tests
docker compose exec app ./vendor/bin/pest                              # full suite
docker compose exec app ./vendor/bin/pest tests/Feature/TransfersTest.php
docker compose exec app ./vendor/bin/pest --filter="race"              # by test name
docker compose exec app ./vendor/bin/pest --coverage                   # needs pcov ext

# Frontend
docker compose exec frontend npm install
docker compose exec frontend npm run typecheck
docker compose exec frontend npm run lint
docker compose exec frontend npm run build

# One-off scheduled task
docker compose exec app php artisan digitalbank:idempotency:cleanup
docker compose exec app php artisan digitalbank:account:set-status <account-id> <active|frozen|closed>
```

The Pest suite uses **a real Postgres database** named `digitalbank_test` on the same `db` container (see `phpunit.xml`). Mocking the DB is not allowed for integration tests — the race-condition transfer test depends on real `SELECT … FOR UPDATE` semantics. If `digitalbank_test` doesn't exist, create it once: `docker compose exec db createdb -U digitalbank digitalbank_test`.

## Critical invariants (§14 of SPEC)

1. **Money is `NUMERIC(19,4)` in the DB and `Brick\Math\BigDecimal` (via `brick/money`) in PHP.** PHP `float` for money is banned. Migrations use `decimal('amount', 19, 4)`.
2. **Every financial flow writes to `audit_log`.** The table refuses UPDATE/DELETE at the DB level — there is no "fix-up" path; if you need to record a correction, write another row.
3. **Mutating financial endpoints require `X-Idempotency-Key` (UUID v4).** The middleware enforces it by route name; rename a route and you silently disable the requirement, so update `IdempotencyMiddleware::REQUIRED_ROUTES` in lockstep.
4. **Only 2xx responses are cached by the idempotency middleware.** 4xx/5xx must be retryable after fixing input.
5. **Concurrent transfer test is a deploy gate.** Don't ship without `tests/Feature/TransfersTest.php` race cases passing.
6. **Secrets only via `.env`.** Read business config through `config('digitalbank.*')`, never `env()` at runtime, so `php artisan config:cache` keeps working in prod.
7. **Migrations and seeders contain no business logic.**
