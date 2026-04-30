# T-Loyalty Hub

Единый раздел лояльности экосистемы Т-Банка: кэшбэк-программы (Black, Platinum, All Airlines, Bravo), персонализированные офферы партнёров, геймификация (Loyalty Health Score, тиры, стрики) и четыре ИИ-фичи (Shadow Portfolio, Dynamic Nudging, Cross-Sell Optimizer, Zero-Click).

## Стек

| Слой       | Технология                              |
|------------|-----------------------------------------|
| Frontend   | React 19 + Vite + TypeScript + Tailwind |
| Backend    | Laravel 11 (PHP 8.3) + PostgreSQL + Redis |
| AI Engine  | FastAPI (Python 3.12) — стабы           |
| Infra      | Docker Compose, nginx                   |

```
Browser  ──►  nginx :8080  ──►  /api/*        ──►  PHP-FPM (Laravel)
                              ──►  /            ──►  Vite SPA build
                                                       │
                              Laravel  ──►  PostgreSQL
                                       ──►  Redis (cache)
                                       ──►  ai-engine:8000 (FastAPI stubs)
```

## Запуск

### В GitHub Codespaces

```bash
cd t-loyalty-hub
cp .env.example .env       # опционально — все значения уже с дефолтами
docker compose up --build  # первый запуск ~3–5 минут
```

После сборки откройте `http://localhost:8080` (или forwarded URL Codespaces для порта **8080**). Бэкенд при первом старте сам:

1. Дождётся Postgres
2. Накатит миграции
3. Импортирует CSV из `backend/storage/app/data/` (там уже лежат датасеты)
4. Запустит php-fpm

### Локально (без Codespaces)

```bash
git clone <repo>
cd t-loyalty-hub
docker compose up --build
```

То же самое, требуется только Docker Desktop / Docker Engine + Compose v2.

### Полезные команды

```bash
# Перезапустить импорт CSV вручную
docker compose exec backend php artisan loyalty:import

# Тесты бэкенда (SQLite in-memory).
# ВНИМАНИЕ: всегда указывайте --env=testing, иначе RefreshDatabase
# может выполниться против Postgres, если конфиг закеширован.
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan test --env=testing

# Тесты AI-engine
docker compose exec ai-engine pytest -q

# Логи
docker compose logs -f backend ai-engine

# Заглушить стек
docker compose down            # сохраняет данные
docker compose down -v         # сбрасывает Postgres-том
```

### Без Docker (быстрая итерация)

Бэкенд против SQLite:

```bash
cd t-loyalty-hub/backend
composer install
php artisan test --env=testing       # 17 тестов, ~1с
```

AI-engine изолированно:

```bash
cd t-loyalty-hub/ai-engine
python -m venv .venv && .venv/bin/pip install -r requirements.txt
.venv/bin/pytest -q                   # 5 тестов
.venv/bin/uvicorn app.main:app --reload
```

Frontend dev-сервер с проксированием на запущенный nginx:

```bash
cd t-loyalty-hub/frontend
npm install
VITE_PROXY_TARGET=http://localhost:8080 npm run dev
```

## Структура

```
t-loyalty-hub/
├── backend/              # Laravel 11 — API + бизнес-логика + CSV-импорт
│   ├── app/
│   │   ├── Console/Commands/ImportCsvCommand.php
│   │   ├── Http/Controllers/Api/
│   │   ├── Http/Resources/
│   │   ├── Models/
│   │   └── Services/         # LoyaltyService, AiEngineClient, GamificationService, CsvImportService
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/Feature/        # 17 PHPUnit-сценариев
├── ai-engine/            # FastAPI — заглушки AI-фич
│   ├── app/
│   │   ├── routers/          # shadow_portfolio, dynamic_nudging, cross_sell, zero_click
│   │   ├── schemas/
│   │   └── services/stub_generator.py
│   └── tests/
├── frontend/             # React + Vite + Tailwind
│   ├── src/
│   │   ├── api/client.ts
│   │   ├── components/{ui,loyalty,gamification,ai}/
│   │   ├── pages/{DemoSelector,LoyaltyHub}.tsx
│   │   ├── stores/{userStore,themeStore}.ts
│   │   └── types/
├── docker-compose.yml
├── docker/nginx/default.conf
└── .github/workflows/ci.yml
```

## API

Все эндпоинты под префиксом `/api`. Полная таблица — в `CLAUDE.md` (§9).

```
GET  /api/users                             — список пользователей (демо-селектор)
GET  /api/users/{id}                        — один пользователь
GET  /api/loyalty/summary?user_id=          — сводка кэшбэка по программам
GET  /api/loyalty/history?user_id=          — пагинированная история
GET  /api/loyalty/programs?user_id=         — активные программы
GET  /api/offers?user_id=                   — офферы по сегменту пользователя
GET  /api/gamification/{userId}             — Health Score, тир, стрик
POST /api/gamification/{userId}/visit       — записать визит
GET  /api/ai/shadow-portfolio/{userId}      — AI Shadow Portfolio (стаб)
GET  /api/ai/nudging/{userId}               — Dynamic Nudging (стаб)
GET  /api/ai/cross-sell/{userId}            — Cross-Sell Optimizer (стаб)
GET  /api/ai/zero-click/{userId}            — Zero-Click Loyalty (стаб)
```

При недоступности AI-engine Laravel возвращает корректный fallback с `is_fallback: true` — фронт не падает.

## Тестирование

| Слой       | Команда                              | Тестов |
|------------|--------------------------------------|--------|
| Backend    | `php artisan test`                   | 17     |
| AI-engine  | `pytest -q`                          | 5      |
| Frontend   | `npm run build` (typecheck + bundle) | —      |

CI запускает все три набора автоматически (`.github/workflows/ci.yml`).

## Заметки по архитектуре

- **Кэш Redis** для `loyalty:summary:*`, `gamification:*`, AI-ответов; TTL заданы в `LoyaltyService` / `GamificationService`.
- **Circuit breaker** в `AiEngineClient` — при таймауте/5xx возвращает безопасный fallback.
- **CSV-импорт идемпотентен** — повторный запуск делает upsert по уникальным ключам.
- **Тёмная тема** через класс `.dark` на `<html>`, токены сохраняются в `localStorage`.
- **ErrorBoundary** оборачивает каждый AI-блок отдельно: падение одного виджета не ломает страницу.
