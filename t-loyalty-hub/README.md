# T-Loyalty Hub

Единый раздел лояльности для экосистемы Т-Банка. В одном интерфейсе живут кэшбэк-программы (Black, Platinum, All Airlines, Bravo), персонализированные офферы партнёров, геймификация (Loyalty Health Score, тиры, стрики дней) и набор ИИ-фич: Shadow Portfolio, Dynamic Nudging, Cross-Sell Optimizer и Zero-Click Loyalty.

Zero-Click работает на реальном NLP-пайплайне — в нём связка из multilingual MiniLM (семантический поиск по офферам) и mDeBERTa zero-shot classifier (различает коммерческие и информационные запросы). Остальные ИИ-блоки на детерминированных стабах с per-user сидом, чтобы демо-показ был воспроизводимым.

---

## Стек

| Слой       | Технология                                                                |
|------------|---------------------------------------------------------------------------|
| Frontend   | React 19 + Vite + TypeScript + Tailwind, Zustand                          |
| Backend    | Laravel 11 (PHP 8.3), PostgreSQL 16, Redis 7                              |
| AI Engine  | FastAPI (Python 3.12), sentence-transformers, transformers, PyTorch (CPU) |
| Infra      | Docker Compose, nginx                                                     |

```
Browser ─► nginx :8080 ─► /api/*  ─► PHP-FPM (Laravel) ─► PostgreSQL
                       └► /       ─► Vite SPA build    ─► Redis
                                                       └► ai-engine:8000 (FastAPI)
```

Внешний URL — только nginx. Backend, AI-engine и БД сидят в внутренней сети Compose.

---

## Что нужно поставить

- **Docker Engine 24+** и **Docker Compose v2** (`docker compose version` должно работать)
- ~6 ГБ свободного места (образ ai-engine с torch + кеш HuggingFace весов ~1.5 ГБ; pgdata)
- Свободные порты: `8080` (web), опционально `5432` / `6379` если будете прокидывать Postgres/Redis наружу

Локальные PHP / Node / Python ставить не нужно — всё крутится в контейнерах.

---

## Быстрый старт

```bash
git clone <repo-url>
cd t-loyalty-hub
cp .env.example .env       # все значения уже с дефолтами, можно не править
docker compose up --build  # первый билд: ~5–8 минут (тащит torch и образы)
```

Когда увидите в логах строки вида:

```
nginx-1     | start worker process
backend-1   | Starting Laravel Octane / php-fpm
ai-engine-1 | Application startup complete.
```

— открывайте `http://localhost:8080`. Это страница демо-селектора пользователей: жмёте на любого, попадаете в раздел лояльности.

При первом старте бэкенд автоматически:

1. Дожидается готовности Postgres и Redis (через healthcheck).
2. Накатывает миграции.
3. Импортирует CSV-датасеты из `backend/storage/app/data/` (там лежит `Users.csv`, `Accounts.csv`, `LoyaltyPrograms.csv`, `LoyaltyHistory.csv`, `Offers.csv`).
4. Поднимает php-fpm.

После этого фронт начинает успешно ходить в API.

---

## Структура

```
t-loyalty-hub/
├── backend/                       # Laravel 11 API + бизнес-логика + CSV-импорт
│   ├── app/
│   │   ├── Console/Commands/ImportCsvCommand.php
│   │   ├── Http/Controllers/Api/
│   │   ├── Http/Resources/
│   │   ├── Models/
│   │   └── Services/              # LoyaltyService, AiEngineClient, GamificationService, CsvImportService
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/Feature/             # 17 PHPUnit-сценариев
├── ai-engine/                     # FastAPI — стабы + реальный Zero-Click ML
│   ├── app/
│   │   ├── routers/               # shadow_portfolio, dynamic_nudging, cross_sell, zero_click
│   │   ├── schemas/
│   │   └── services/
│   │       ├── stub_generator.py
│   │       └── zero_click_ai.py   # mDeBERTa + MiniLM, ленивая загрузка моделей
│   └── tests/
├── frontend/                      # React + Vite + Tailwind
│   └── src/
│       ├── api/client.ts
│       ├── components/{ui,loyalty,gamification,ai}/
│       ├── pages/{DemoSelector,LoyaltyHub}.tsx
│       ├── stores/{userStore,themeStore}.ts
│       └── types/
├── data/                          # CSV-датасеты, монтируются в ai-engine
├── docker/nginx/default.conf
├── docker-compose.yml
└── .github/workflows/ci.yml
```

---

## API

Всё под префиксом `/api`. Эндпоинты, требующие пользователя, принимают либо `?user_id=`, либо `{userId}` в пути.

| Метод | Путь                                        | Описание                                |
|-------|---------------------------------------------|-----------------------------------------|
| GET   | `/api/users`                                | Список пользователей для демо-селектора |
| GET   | `/api/users/{id}`                           | Один пользователь                       |
| GET   | `/api/loyalty/summary?user_id=`             | Сводка кэшбэка по программам            |
| GET   | `/api/loyalty/history?user_id=&per_page=`   | Пагинированная история выплат           |
| GET   | `/api/loyalty/programs?user_id=`            | Активные программы лояльности           |
| GET   | `/api/offers?user_id=`                      | Офферы, отфильтрованные по сегменту     |
| GET   | `/api/gamification/{userId}`                | Health Score, тир, стрик                |
| POST  | `/api/gamification/{userId}/visit`          | Записать визит, обновить стрик          |
| GET   | `/api/ai/shadow-portfolio/{userId}`         | Shadow Portfolio (стаб)                 |
| GET   | `/api/ai/nudging/{userId}`                  | Dynamic Nudging (стаб)                  |
| GET   | `/api/ai/cross-sell/{userId}`               | Cross-Sell Optimizer (стаб)             |
| GET   | `/api/ai/zero-click/{userId}?query=`        | Zero-Click: ML при наличии `query`, иначе стаб |

Если AI-engine упал или таймаутит — Laravel возвращает безопасный fallback с `is_fallback: true`, фронт не падает.

### Zero-Click — формат ответа

```jsonc
GET /api/ai/zero-click/1?query=заказать пиццу додо
{
  "data": {
    "intent": "COMMERCIAL",            // COMMERCIAL | INFORMATIONAL | COMMERCIAL_NO_OFFER
    "activated_offer": "Кэшбэк 7% — Додо Пицца",
    "partner_name": "Додо Пицца",
    "cashback_percent": 7,
    "match_accuracy": 0.78,
    "probability": 0.78,
    "query": "заказать пиццу додо",
    "is_stub": false
  }
}
```

Без `query` отдаётся прежний детерминированный стаб (`is_stub: true`).

> **Первый запрос с `query` идёт ~30–90 секунд** — за это время скачиваются веса моделей (~400 МБ) в named volume `hf_cache`. Все последующие запросы укладываются в < 1 с. Перезапуск контейнера не сбрасывает кеш весов.

---

## Полезные команды

```bash
# Перезапустить импорт CSV вручную
docker compose exec backend php artisan loyalty:import

# Тесты бэкенда (SQLite in-memory).
# ВАЖНО: всегда передавайте --env=testing, иначе при закешированном
# конфиге RefreshDatabase может выполниться против Postgres.
docker compose exec backend php artisan config:clear
docker compose exec backend php artisan test --env=testing

# Тесты AI-engine
docker compose exec ai-engine pytest -q

# Хвостить логи
docker compose logs -f backend ai-engine

# Заглушить стек, оставить данные
docker compose down

# Снести том Postgres
docker compose down -v

# Снести кеш HuggingFace-весов (повторно прогреется на следующем запросе)
docker volume rm t-loyalty-hub_hf_cache
```

### Тестирование Zero-Click ML напрямую

```bash
# Без выхода наружу контейнера
docker compose exec ai-engine python -c "
import urllib.request, urllib.parse, json
q = urllib.parse.quote('заказать пиццу додо')
r = urllib.request.urlopen(f'http://localhost:8000/ai/zero-click/1?query={q}', timeout=120).read()
print(json.dumps(json.loads(r), ensure_ascii=False, indent=2))
"

# Через Laravel (порт 8080 проброшен наружу)
curl 'http://localhost:8080/api/ai/zero-click/1?query=кроссовки%20найк'

# Информационный запрос — модель не активирует оффер
curl 'http://localhost:8080/api/ai/zero-click/1?query=как%20починить%20кран'
```

---

## Без Docker (если хочется итерировать быстрее)

Бэкенд на SQLite, без Postgres/Redis:

```bash
cd backend
composer install
php artisan test --env=testing       # 17 тестов, ~1 с
```

AI-engine изолированно:

```bash
cd ai-engine
python -m venv .venv
.venv/bin/pip install -r requirements.txt
.venv/bin/pytest -q                   # 5 тестов на стаб-роуты
.venv/bin/uvicorn app.main:app --reload
# затем: GET http://localhost:8000/ai/zero-click/1?query=...
# (CSV ожидается в /app/data/Offers.csv — либо положите рядом и
#  выставите OFFERS_CSV_PATH=./data/Offers.csv)
OFFERS_CSV_PATH=../data/Offers.csv .venv/bin/uvicorn app.main:app --reload
```

Frontend dev-сервер с проксированием на поднятый nginx:

```bash
cd frontend
npm install
VITE_PROXY_TARGET=http://localhost:8080 npm run dev
# открыть http://localhost:5173
```

---

## Тестирование

| Слой       | Команда                              | Тестов |
|------------|--------------------------------------|--------|
| Backend    | `php artisan test --env=testing`     | 17     |
| AI-engine  | `pytest -q`                          | 5      |
| Frontend   | `npm run build` (typecheck + bundle) | —      |

GitHub Actions гоняет все три набора на каждый push в `main` и каждый PR — см. `.github/workflows/ci.yml`.

---

## Заметки по архитектуре

- **Redis-кэш** для `loyalty:summary:*` (TTL 5 минут), `gamification:*` (TTL 60 с) и ответов AI-стабов. TTL заданы в соответствующих сервисах.
- **Circuit Breaker** в `AiEngineClient` — при таймауте или 5xx от FastAPI возвращает безопасный fallback с заполненными нулями полями. Никаких полу-разваленных страниц на фронте.
- **CSV-импорт идемпотентен** — повторный `php artisan loyalty:import` делает upsert по уникальным ключам, дубликатов не будет.
- **Тёмная тема** через класс `.dark` на `<html>`, выбор сохраняется в `localStorage`. Все компоненты содержат `dark:`-варианты Tailwind-классов.
- **ErrorBoundary** оборачивает каждый AI-виджет независимо — упавший Shadow Portfolio не уронит Cross-Sell.
- **Zero-Click ML** грузит веса моделей лениво при первом вызове с `query`, под mutex'ом, чтобы под нагрузкой не было дублирующейся загрузки. Healthcheck не зависит от готовности моделей — контейнер сразу `healthy`.

---

## Траблшутинг

**`docker compose up` падает на пуле образов / `torch`**
Сборка `ai-engine` тянет ~1 ГБ wheel'ов. Если интернет нестабильный — повторите `docker compose build ai-engine`, кеш слоёв уцелеет.

**`pq: database "tloyalty" does not exist` на старте бэкенда**
Postgres не успел подняться. Compose-healthcheck должен был отработать, но если стартанули вручную в неправильном порядке — `docker compose down -v && docker compose up`.

**`/api/ai/zero-click/...` отвечает `503 Offers dataset not mounted`**
Не примонтировалась `./data` в `ai-engine`. Проверьте, что вы запускали из директории `t-loyalty-hub/`, а не откуда-то ещё, и что `./data/Offers.csv` существует.

**Первый запрос с `query` отвалился по таймауту**
Laravel-клиент по умолчанию ждёт 5 с (см. `AI_ENGINE_TIMEOUT` в `.env`). Перед демо разогрейте модель напрямую — `docker compose exec ai-engine ... ?query=test`. После прогрева ответы возвращаются за < 1 с.

**Хочется свежий стейт БД**
`docker compose down -v` сбросит том `pgdata`, на следующем `up` миграции и импорт CSV прокатятся заново.
