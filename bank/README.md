# DigitalBank — MVP v1.0

Банковское веб-приложение (Laravel 11 + Next.js 14 + PostgreSQL 16 + Redis 7).
Подробное ТЗ — `Claude_SPEC.docx`. План разработки по этапам — `DEVELOPMENT_STAGES.md`.

## Стек

| Слой       | Технология                       |
|------------|----------------------------------|
| Backend    | Laravel 11 (PHP 8.3)             |
| Frontend   | Next.js 14 + TypeScript + Tailwind |
| БД         | PostgreSQL 16 (`NUMERIC(19,4)`)  |
| Кэш/Очередь| Redis 7                          |
| Web        | Nginx (reverse proxy)            |
| Auth       | Laravel Sanctum (SPA stateful)   |
| Infra      | Docker Compose                   |

## Быстрый старт

```bash
cp .env.example .env
# Заполнить минимум: DB_PASSWORD, REDIS_PASSWORD (если используется), APP_KEY.
# APP_KEY будет сгенерирован позже: `docker compose run --rm app php artisan key:generate`.

docker compose up --build -d
docker compose ps
```

Открыть:
- `http://localhost/`          — фронтенд (Next.js / placeholder до Этапа 10).
- `http://localhost/api/...`   — Laravel API.
- `http://localhost/healthz`   — health nginx.

Остановить: `docker compose down` (данные в volumes сохраняются).

## Структура репозитория

```
.
├── backend/                   Laravel 11 (создаётся на Этапе 2)
├── frontend/                  Next.js 14   (создаётся на Этапе 10)
├── docker/
│   ├── php/                   PHP-FPM image (app/worker/scheduler)
│   ├── nginx/                 Nginx reverse proxy
│   └── frontend/              Node image для Next.js
├── docker-compose.yml
├── .env.example
├── Claude_SPEC.docx           ТЗ
└── DEVELOPMENT_STAGES.md      план работ (11 этапов)
```

## Важные инварианты

1. Секреты — только через `.env`, в код не попадают.
2. Денежные расчёты — `NUMERIC(19,4)` + `brick/money`. `float` запрещён.
3. Любой финансовый поток пишет в `audit_log`.
4. Все мутирующие финансовые запросы требуют `X-Idempotency-Key` (UUID v4).
5. Race condition тест для переводов обязателен перед каждым деплоем.
