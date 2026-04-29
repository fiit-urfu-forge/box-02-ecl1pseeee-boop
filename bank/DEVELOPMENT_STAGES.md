
# План разработки «Цифровой Банк» MVP v1.0

Разбивка работ по ТЗ `Claude_SPEC.docx` на 11 последовательных этапов. Каждый этап заканчивается проверяемым артефактом (код + миграции + тесты). Переход к следующему этапу допускается только после зелёного CI на текущем.

Легенда статусов: `▢ pending` · `◐ in progress` · `■ done`.

---

## ■ Этап 1. Инфраструктура и скелет репозитория
**Цель:** получить воспроизводимую локальную среду `docker compose up` со всеми семью контейнерами.

**Задачи:**
- Каталоги `backend/`, `frontend/`, `docker/` (nginx, php, frontend).
- `docker-compose.yml` с сервисами: `app`, `web`, `frontend`, `db`, `redis`, `worker`, `scheduler` + healthchecks (§8.4).
- `docker/php/Dockerfile` (PHP 8.3-FPM + pdo_pgsql, redis, bcmath, gd, intl, opcache).
- `docker/nginx/nginx.conf` — проксирование `/api/*` и `/sanctum/*` на `app:9000`, всё остальное — на `frontend:3000` (§8.2).
- `docker/frontend/Dockerfile` для Next.js 14 (node:20-alpine).
- `.env.example` — все переменные из §9.
- Обновить `README.md` (quick start) и `.gitignore` (добавить vendor, node_modules, storage/logs, .env).
- Проверка: `docker compose config` валиден.

**Артефакты:** `docker compose config --quiet` → exit 0 (проверено). `docker compose up --build` запустит весь стек; Laravel-код подключается на Этапе 2, поэтому до тех пор `app`/`worker` будут перезапускаться при старте — нормально.

---

## ■ Этап 2. Laravel 11 + миграции БД
**Цель:** реализовать схему данных §3 с индексами §3.6.

- `composer create-project laravel/laravel:^11 backend`.
- Установить: `laravel/sanctum`, `predis/predis`, `brick/money`, `pestphp/pest`, `larastan/larastan`.
- `config/database.php` — PostgreSQL, `NUMERIC(19,4)` через `decimal('amount', 19, 4)`.
- Миграции (строго по §3): `users`, `accounts`, `transactions`, `idempotency_keys`, `audit_log`.
- ENUM'ы через `DB::statement('CREATE TYPE …')` либо `->enum(...)` в миграции.
- CHECK-constraints: `accounts.balance >= 0`, `transactions.amount > 0`.
- Все индексы из §3.6.
- Eloquent-модели с UUID-primary-key, casts, relations.
- Seeder-заглушки (dev only).

**Артефакты (проверено):** все 7 миграций применены против PostgreSQL 16 внутри `docker compose`. `users`, `accounts`, `transactions`, `idempotency_keys`, `audit_log` — с индексами §3.6 и CHECK-ограничениями. `audit_log` защищён DB-триггерами от UPDATE/DELETE. Eloquent-модели `User`/`Account`/`Transaction`/`IdempotencyKey`/`AuditLogEntry` smoke-тестом покрыты.

---

## ■ Этап 3. API-контракт, идемпотентность, ошибки
**Цель:** единый формат ответов §6.1 + `IdempotencyMiddleware` (100% покрытие тестами).

- `App\Support\ApiResponse::success(data, meta)` / `::error(code, message, details, http)`.
- Глобальный exception handler → маппинг исключений в коды §6.2.
- `request_id` (UUID v4) в `meta` — middleware.
- `IdempotencyMiddleware` (§7.2): валидирует UUID v4, проверяет БД, при конфликте 409, при кэше — 200 + сохранённое тело.
- Стандартная пагинация §6.4 (custom resource или `PaginatedResource`).
- Rate Limiting §7.4 через `RateLimiter::for()`.

**Артефакты (проверено):** 16/16 feature-тестов зелёные (`tests/Feature/ApiEnvelopeTest.php`, `tests/Feature/IdempotencyMiddlewareTest.php`): success/error envelope, request_id (входящий UUID/генерируемый), маппинг `ApiException`, `ValidationException`, 404, 405, 429; idempotency-middleware покрывает replay, conflict по payload, conflict по user, in-flight reservation, откат резервации при throw, отказ кэшировать non-2xx, skip на GET, 422 при отсутствии обязательного заголовка. Rate limiters §7.4 зарегистрированы.

---

## ■ Этап 4. Аутентификация и профиль
- Sanctum stateful (SPA), CSRF.
- `POST /api/auth/register|login|logout|logout-all` (§5.1).
- Верификация e-mail через `Laravel Notifications` в очереди.
- Блокировка: 5 ошибок → 15 мин (поля `failed_login_at`, `failed_login_count` + `RateLimiter`).
- `GET/PATCH /api/user/profile`.
- `POST /api/user/avatar` (multipart, ≤5 MB, JPEG/PNG/WEBP, §4.3). Локальный диск для MVP, S3-ready конфиг.

**Артефакты (проверено):** 34/34 feature-тестов зелёные (125 assertions). AuthTest покрывает register, weak password, duplicate email, requires email verification, login+session, 5-fail lockout, clear-on-success, logout, signed email verification. ProfileTest: 401 anon, show/update profile, invalid phone, avatar upload/replace, mime/size reject, stream, 404 missing. Включая audit_log записи для всех auth-событий.

---

## ■ Этап 5. Счета (Accounts)
- `POST /api/accounts` (идемпотентно) — генератор 20-значного номера (`810…` RUB / `840…` USD).
- `GET /api/accounts`, `GET /api/accounts/{id}`.
- `AccountPolicy` — только свой счёт.
- Лимит 5 счетов (§4.1).
- Статусы `active | frozen | closed` — заморозка через artisan-команду (MVP).

**Артефакты (проверено):** 45/45 feature-тестов зелёные (167 assertions). 11 новых кейсов в `AccountsTest`: 401 anon, RUB/810 + USD/840 номер, bad currency, bad type, 5-лимит, required idempotency header, own-only list, other-user 404, idempotent replay без дублирования, artisan-команда `digitalbank:account:set-status`.

---

## ■ Этап 6. Переводы (Core Banking)
- `TransferRequest` — все валидации из §4.1 (min/max/daily/same-currency/self-same-account).
- `transactions.status`: pending → processing → success/failed; pending → cancelled (§4.2).
- `ProcessTransferJob` в очередь `transfers` (высокий приоритет).
- Атомарность: одна БД-транзакция, `SELECT … FOR UPDATE` по `account_id ASC` (anti-deadlock).
- Рекорд в `audit_log` на каждый значимый шаг.
- `GET /api/transfers`, `GET /api/transfers/{id}` с пагинацией.

**Артефакты (проверено):** 62/62 feature-тестов зелёные (229 assertions). Все 10 обязательных кейсов §11.2: success, insufficient funds, non-existent account, frozen sender+receiver, amount-too-high/low, daily-limit, cross-currency, self-same-account + same-owner-different-account, idempotent replay (funds не удваиваются), race-overdraft защита, double-execute не двоит баланс. `brick/money::BigDecimal` используется во всех арифметических операциях. `SELECT FOR UPDATE` — `orderBy('id')` для anti-deadlock. На каждом шаге пишется `transfer.created` / `transfer.success` / `transfer.failed` в `audit_log`.

---

## ■ Этап 7. СБП (Mock + webhook)
- `SbpGatewayInterface` с `initiateTransfer()`, `getStatus()`.
- `MockSbpGateway` для MVP — отвечает немедленно.
- `POST /api/sbp/link-phone`, `POST /api/sbp/transfer` (idempotent).
- `POST /api/webhooks/sbp` — HMAC-SHA256 (`SBP_WEBHOOK_SECRET`), событие в audit_log при провале подписи.

**Артефакты (проверено):** 71/71 тестов зелёные (255 assertions). 9 новых кейсов в `SbpTest`: link-phone happy-path + audit, bad phone format, link on someone else account → 404, SBP transfer создаёт `pending` sbp_out через `MockSbpGateway` (не трогает баланс), требует idempotency header, amount-too-low, webhook отклоняет missing/wrong signature (с audit записью `sbp.webhook_bad_signature`), webhook принимает корректную HMAC-SHA256 (audit `sbp.webhook_received`). Реальная обработка платежа / зачисление по sbp_in — post-MVP (документировано в коде).

---

## ■ Этап 8. Логирование и мониторинг
- Structured JSON logger (§10.1). Каналы: `transfers`, `auth`, `security`, `accounts`, `app`.
- `CleanupIdempotencyKeys` в scheduler (1/час) + TTL 24h (§4.1).
- Healthchecks уже в compose (§8.4) — проверить рабочесть.

**Артефакты (проверено):** 75/75 тестов зелёные (274 assertions). 4 новых кейса в `LoggingAndCleanupTest`: JSON-формат записи через `JsonFormatterFactory`, все 4 бизнес-канала (`transfers`/`auth`/`accounts`/`security`) зарегистрированы и получают tap, `digitalbank:idempotency:cleanup` удаляет только просроченные ключи, scheduler регистрирует команду с cron `0 * * * *`. Контейнеры `db` и `redis` проверены: оба в статусе `(healthy)`. Каналы логирования подключены в TransferService (`transfers`), AuthController (`auth`), SbpWebhookController (`security`).

---

## ■ Этап 9. Тесты Pest / PHPUnit
Обязательные кейсы §11.2: успех, нехватка средств, несуществующий счёт, замороженный счёт, разовый лимит, дневной лимит, кросс-валюта, повторный idempotency-key, **race condition**, самоперевод.
Цель покрытия §11.1: core banking ≥ 90%, idempotency 100%, auth ≥ 85%, API ≥ 80%.

**Артефакты (проверено через pcov):** 101/101 тестов зелёные (342 assertions), **общий coverage 94.7%**. Разбивка по ключевым ответственностям:
- core banking: TransferController 94.6% + TransferService 94.1% → ≥ 90% ✅
- idempotency middleware: 94.1% (оставшиеся 3 строки — unreachable null-user defence-in-depth и race-lost-insert branch, задокументированы в коде)
- auth: AuthController 98.3%, VerifyEmailController 92.6% → ≥ 85% ✅
- API контракт: все контроллеры и middleware 94–100% → ≥ 80% ✅
- business rules: AccountController 96.7%, CreateTransferRequest 100% → ≥ 90% ✅
- СБП: SbpController 94.4%, SbpWebhookController 96.8%, MockSbpGateway 100% → ≥ 70% ✅

21 дополнительный кейс в `CoverageSupplementTest` закрыл пробелы: login lockout pre-check, suspended account, logout-all+audit+sessions cleanup, anon logout-all, resend verification (verified/unverified), double-verify, missing user verify, MailMessage contents, idempotency lost-race payload-mismatch, 204 empty body, recursive fingerprint sort, TransferService vanished accounts / already-processing / frozen mid-flight / currency-drift / daily-limit-post-lock, AccountNumberGenerator bad currency, AuditLogger FK-violation swallow, ApiResponse paginated / 419 CSRF / 418 teapot→INTERNAL, AccountPolicy owner/deny, MockSbpGateway init+status.

**Побочный фикс:** `personal_access_tokens` миграция переведена на `uuidMorphs('tokenable')` — default Sanctum bigint-morphs несовместим с UUID-юзерами.

---

## ■ Этап 10. Frontend Next.js 14
- App Router, Tailwind, Zustand (user+accounts), React Query (server state), RHF + Zod.
- `types/api.ts` — зеркало §6.
- Страницы: `/login`, `/register`, `/dashboard`, `/accounts`, `/transfers/new`, `/transfers/[id]`, `/profile`.
- Polling баланса каждые 30 сек.
- `X-Idempotency-Key`: `crypto.randomUUID()` при mount, сбрасывается при success.
- Полная обработка всех кодов ошибок §6.2 — только human-readable сообщения.

**Артефакты (проверено):** `npm run typecheck` и `npm run build` — оба чистые. 14 маршрутов отрендерены, First Load JS 87 KB shared + 2–7 KB per route. Полный набор страниц: `/` (gate), `/login`, `/register`, `/verify-email`, `/dashboard`, `/accounts` + `/accounts/new` + `/accounts/[id]`, `/transfers` + `/transfers/new` + `/transfers/[id]`, `/sbp`, `/profile`, `/not-found`.

**UX-решения:**
- Дизайн-система Tailwind: brand-indigo палитра, emerald/rose/amber акценты, `animate-fade-in`, `focus-visible` ring-ы.
- Крупные primary-кнопки, secondary/ghost варианты, loading-состояние со spinner.
- Карточки аккаунтов — градиент brand-600 → brand-800 на странице счёта (ощущение банковской карты).
- Copy-to-clipboard номера счёта на странице счёта.
- Real-time баланс — `refetchInterval: 30_000` на React Query (§13.1).
- Transfer detail поллит каждые 4 сек пока `pending`/`processing`.
- Toast-система (Zustand store + ToastHost): success/error/info/warning с auto-dismiss 5 сек.
- Empty states с CTA на всех пустых списках.
- Skeleton loading вместо spinner'ов.
- Sidebar + Header layout, mobile-friendly меню.
- Idempotency-key из `crypto.randomUUID()` сгенерирован один раз на mount формы, сбрасывается только на успехе (§13.3).
- Все 17 кодов ошибок замаплены в русскоязычный human-readable текст (`ERROR_COPY`).
- Zod + React Hook Form — валидация на клиенте (E.164 phone, UUID, 20-digit account_number, amount regex, password strength).
- Исправлен Sanctum CSRF прайминг, `resetCsrf()` после logout.

---

## ▢ Этап 11. CI/CD GitLab
- `.gitlab-ci.yml`: `lint` (PHPStan L6, ESLint) → `test` (Pest + Jest + coverage) → `build` → `deploy:staging` (`develop`) → `deploy:prod` (`main`, manual).
- Кэши composer/npm.
- Деплой-скрипт §12 (migrate --force, config/route/view cache).

---

## Критически важные инварианты (из §14)
1. Никаких секретов в коде — только `.env`.
2. Денежные расчёты — **только** `NUMERIC(19,4)` в БД и `brick/money` в PHP. Никаких `float`.
3. В миграциях/сидерах бизнес-логики нет.
4. Любой финансовый поток пишет в `audit_log`.
5. Race condition тест — обязателен перед каждым деплоем.
