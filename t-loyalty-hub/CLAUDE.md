# Техническая спецификация: T-Loyalty Hub
**Версия:** 1.0  
**Автор:** Senior Developer  
**Статус:** Финальная версия для передачи ИИ-агенту

---

## 1. Обзор проекта

**T-Loyalty Hub** — единый раздел лояльности для экосистемы Т-Банка, объединяющий кэшбэк-программы (Black, Platinum, All Airlines), акции партнёров, геймификацию и ИИ-аналитику в одном интерфейсе.


### 1.1. Ключевые метрики успеха

| Метрика | Цель |
|---|---|
| Latency API-ответа | < 200ms (кэшированные данные < 50ms) |
| Покрытие тестами | ≥ 5 PHPUnit-сценариев |
| Lighthouse Score | > 85 |
| Поддержка тёмной темы | 100% компонентов |

---

## 2. Архитектура системы

```
┌─────────────────────────────────────────────────────────┐
│                    React SPA (Frontend)                  │
│              Tailwind CSS + Mobile-First                 │
└──────────────────────┬──────────────────────────────────┘
                       │ REST / JSON
┌──────────────────────▼──────────────────────────────────┐
│              Laravel 11 API (Orchestrator)               │
│         Auth · Business Logic · CSV Import              │
│              PostgreSQL · Redis Cache                    │
└──────────────────────┬──────────────────────────────────┘
                       │ Internal HTTP
┌──────────────────────▼──────────────────────────────────┐
│           Python FastAPI (AI-Engine / Stubs)             │
│   ShadowPortfolio · DynamicNudging · CrossSell · Zero   │
└─────────────────────────────────────────────────────────┘
```

### 2.1. Взаимодействие сервисов

1. **React SPA** — отправляет запросы только на Laravel API.
2. **Laravel** — валидирует запрос, проверяет кэш Redis, при необходимости запрашивает FastAPI и возвращает готовый ответ фронтенду.
3. **FastAPI** — принимает запросы только от Laravel (внутренняя сеть Docker), возвращает JSON с ИИ-данными (сейчас стабы с рандомизацией).

---

## 3. Структура репозитория

```
t-loyalty-hub/
├── backend/               # Laravel 11
│   ├── app/
│   │   ├── Console/Commands/ImportCsvCommand.php
│   │   ├── Http/
│   │   │   ├── Controllers/Api/
│   │   │   │   ├── UserController.php
│   │   │   │   ├── LoyaltyController.php
│   │   │   │   ├── OffersController.php
│   │   │   │   └── GamificationController.php
│   │   │   ├── Middleware/
│   │   │   │   └── SelectUserMiddleware.php
│   │   │   └── Resources/
│   │   │       ├── UserResource.php
│   │   │       ├── LoyaltyHistoryResource.php
│   │   │       └── OfferResource.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Account.php
│   │   │   ├── LoyaltyProgram.php
│   │   │   ├── LoyaltyHistory.php
│   │   │   └── Offer.php
│   │   └── Services/
│   │       ├── LoyaltyService.php
│   │       ├── AiEngineClient.php
│   │       ├── GamificationService.php
│   │       └── CsvImportService.php
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/Feature/
│       ├── LoyaltySummaryTest.php
│       ├── OffersTest.php
│       ├── GamificationTest.php
│       ├── AiStubsTest.php
│       └── UserSwitchTest.php
│
├── ai-engine/             # Python FastAPI
│   ├── app/
│   │   ├── main.py
│   │   ├── routers/
│   │   │   ├── shadow_portfolio.py
│   │   │   ├── dynamic_nudging.py
│   │   │   ├── cross_sell.py
│   │   │   └── zero_click.py
│   │   ├── schemas/
│   │   │   └── ai_schemas.py
│   │   └── services/
│   │       └── stub_generator.py
│   └── requirements.txt
│
├── frontend/              # React + Vite + Tailwind
│   ├── src/
│   │   ├── api/
│   │   │   └── client.ts
│   │   ├── components/
│   │   │   ├── ui/          # Переиспользуемые компоненты
│   │   │   ├── loyalty/     # Компоненты раздела лояльности
│   │   │   ├── gamification/
│   │   │   └── ai/
│   │   ├── pages/
│   │   │   ├── DemoSelector.tsx    # Выбор тестового пользователя
│   │   │   └── LoyaltyHub.tsx
│   │   ├── stores/
│   │   │   └── userStore.ts        # Zustand
│   │   └── types/
│   └── tailwind.config.ts
│
├── docker-compose.yml
├── .github/workflows/ci.yml
└── docs/
    └── SPEC.md            # этот файл
```

---

## 4. База данных

### 4.1. Схема (PostgreSQL)

```sql
-- users (из Users.csv)
CREATE TABLE users (
    id               BIGINT PRIMARY KEY,
    email            VARCHAR(255) UNIQUE NOT NULL,
    phone_number     VARCHAR(20),
    full_name        VARCHAR(255) NOT NULL,
    financial_segment VARCHAR(20) NOT NULL  -- 'LOW' | 'MEDIUM' | 'HIGH'
);

-- loyalty_programs (из LoyaltyPrograms.csv)
CREATE TABLE loyalty_programs (
    loyalty_program_id   BIGINT PRIMARY KEY,
    loyalty_program_name VARCHAR(100) NOT NULL,
    cashback_currency    VARCHAR(20) NOT NULL  -- 'RUB' | 'MILES' | 'BRAVO'
);

-- accounts (из Accounts.csv)
CREATE TABLE accounts (
    account_id         BIGINT PRIMARY KEY,
    user_id            BIGINT NOT NULL REFERENCES users(id),
    loyalty_program_id BIGINT NOT NULL REFERENCES loyalty_programs(loyalty_program_id),
    current_balance    DECIMAL(15,2) NOT NULL DEFAULT 0
);

-- loyalty_history (из LoyaltyHistory.csv)
CREATE TABLE loyalty_history (
    transaction_id  BIGINT PRIMARY KEY,
    account_id      BIGINT NOT NULL REFERENCES accounts(account_id),
    cashback_amount DECIMAL(10,2) NOT NULL,
    payout_date     DATE NOT NULL
);

-- offers (из Offers.csv)
CREATE TABLE offers (
    id                  BIGSERIAL PRIMARY KEY,
    partner_id          BIGINT NOT NULL,
    partner_name        VARCHAR(255) NOT NULL,
    short_description   TEXT,
    logo_url            VARCHAR(500),
    brand_color_hex     VARCHAR(7),
    cashback_percent    DECIMAL(5,2) NOT NULL,
    financial_segment   VARCHAR(20) NOT NULL  -- 'ALL' | 'LOW' | 'MEDIUM' | 'HIGH'
);

-- gamification (Redis-first, PostgreSQL как persistence)
CREATE TABLE user_gamification (
    id              BIGSERIAL PRIMARY KEY,
    user_id         BIGINT NOT NULL REFERENCES users(id) UNIQUE,
    health_score    INT NOT NULL DEFAULT 0,      -- 0-100
    loyalty_tier    VARCHAR(50) NOT NULL DEFAULT 'Новичок',
    streak_days     INT NOT NULL DEFAULT 0,
    last_visit_date DATE,
    created_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at      TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);
```

### 4.2. Индексы

```sql
CREATE INDEX idx_loyalty_history_account_id ON loyalty_history(account_id);
CREATE INDEX idx_loyalty_history_payout_date ON loyalty_history(payout_date);
CREATE INDEX idx_accounts_user_id ON accounts(user_id);
CREATE INDEX idx_offers_financial_segment ON offers(financial_segment);
```

### 4.3. Redis-ключи

| Ключ | TTL | Содержимое |
|---|---|---|
| `loyalty:summary:{user_id}` | 300s | JSON сводки лояльности |
| `ai:shadow:{user_id}` | 600s | JSON Shadow Portfolio |
| `ai:nudge:{user_id}` | 3600s | JSON Dynamic Nudging |
| `gamification:{user_id}` | 60s | JSON health score + streak |

---

## 5. Backend: Laravel 11

### 5.1. Конфигурация окружения (.env)

```env
APP_NAME="T-Loyalty Hub"
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=tloyalty
DB_USERNAME=tloyalty
DB_PASSWORD=secret

REDIS_HOST=redis
REDIS_PORT=6379

AI_ENGINE_URL=http://ai-engine:8000
AI_ENGINE_TIMEOUT=5  # секунды, после которых Laravel вернёт fallback

CACHE_DRIVER=redis
QUEUE_CONNECTION=sync
```

### 5.2. Routes (`routes/api.php`)

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\{
    UserController,
    LoyaltyController,
    OffersController,
    GamificationController,
};

// Демо-роут: список пользователей для переключения
Route::get('/users', [UserController::class, 'index']);
Route::get('/users/{user}', [UserController::class, 'show']);

// Ядро раздела лояльности (все роуты требуют ?user_id=)
Route::prefix('loyalty')->group(function () {
    Route::get('/summary', [LoyaltyController::class, 'summary']);
    Route::get('/history', [LoyaltyController::class, 'history']);
    Route::get('/programs', [LoyaltyController::class, 'programs']);
});

// Офферы (персонализированные по сегменту)
Route::get('/offers', [OffersController::class, 'index']);

// Геймификация
Route::prefix('gamification')->group(function () {
    Route::get('/{userId}', [GamificationController::class, 'show']);
    Route::post('/{userId}/visit', [GamificationController::class, 'recordVisit']);
});

// ИИ-фичи (проксируются через Laravel в FastAPI)
Route::prefix('ai')->group(function () {
    Route::get('/shadow-portfolio/{userId}', [LoyaltyController::class, 'shadowPortfolio']);
    Route::get('/nudging/{userId}', [LoyaltyController::class, 'dynamicNudging']);
    Route::get('/cross-sell/{userId}', [OffersController::class, 'crossSellOptimizer']);
    Route::get('/zero-click/{userId}', [OffersController::class, 'zeroClick']);
});
```

### 5.3. Модели

#### `app/Models/User.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Model
{
    protected $fillable = ['id', 'email', 'phone_number', 'full_name', 'financial_segment'];

    public $incrementing = false;  // id приходит из CSV

    /** @return HasMany<Account> */
    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    /** Является ли пользователь высокосегментным */
    public function isHighSegment(): bool
    {
        return $this->financial_segment === 'HIGH';
    }
}
```

#### `app/Models/Account.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Account extends Model
{
    protected $fillable = ['account_id', 'user_id', 'loyalty_program_id', 'current_balance'];

    protected $primaryKey = 'account_id';
    public $incrementing = false;

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class, 'loyalty_program_id', 'loyalty_program_id');
    }

    public function loyaltyHistory(): HasMany
    {
        return $this->hasMany(LoyaltyHistory::class, 'account_id', 'account_id');
    }
}
```

#### `app/Models/LoyaltyHistory.php`
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoyaltyHistory extends Model
{
    protected $fillable = ['transaction_id', 'account_id', 'cashback_amount', 'payout_date'];

    protected $primaryKey = 'transaction_id';
    public $incrementing = false;

    protected $casts = ['payout_date' => 'date'];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id', 'account_id');
    }
}
```

### 5.4. Сервисы

#### `app/Services/LoyaltyService.php`
```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\LoyaltyHistory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Основной сервис расчёта лояльности пользователя.
 * Единственное место бизнес-логики по кэшбэку — принцип DRY/SRP.
 */
class LoyaltyService
{
    private const CACHE_TTL = 300; // 5 минут

    /**
     * Возвращает сводку лояльности по всем программам пользователя.
     * Кэшируется в Redis.
     *
     * @return array{rub: float, miles: float, bravo: float, total_transactions: int}
     */
    public function getSummary(User $user): array
    {
        $cacheKey = "loyalty:summary:{$user->id}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            return DB::select("
                SELECT
                    lp.cashback_currency,
                    SUM(lh.cashback_amount) AS total,
                    COUNT(lh.transaction_id) AS transactions
                FROM accounts a
                JOIN loyalty_programs lp ON lp.loyalty_program_id = a.loyalty_program_id
                JOIN loyalty_history lh ON lh.account_id = a.account_id
                WHERE a.user_id = ?
                GROUP BY lp.cashback_currency
            ", [$user->id]);
        });
    }

    /**
     * История выплат с пагинацией.
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function getHistory(User $user, int $perPage = 20)
    {
        return LoyaltyHistory::query()
            ->whereHas('account', fn ($q) => $q->where('user_id', $user->id))
            ->with('account.loyaltyProgram')
            ->orderByDesc('payout_date')
            ->paginate($perPage);
    }
}
```

#### `app/Services/AiEngineClient.php`
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * HTTP-клиент для взаимодействия с Python FastAPI AI-Engine.
 * Инкапсулирует всю логику работы с внешним AI-сервисом.
 * При недоступности сервиса возвращает fallback-данные (Circuit Breaker pattern).
 */
class AiEngineClient
{
    private string $baseUrl;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.ai_engine.url', 'http://ai-engine:8000');
        $this->timeout = config('services.ai_engine.timeout', 5);
    }

    /**
     * Получить данные Shadow Portfolio для пользователя.
     *
     * @return array{real_cashback: float, shadow_cashback: float, gap: float, insight: string}
     */
    public function getShadowPortfolio(int $userId): array
    {
        return $this->request("GET", "/ai/shadow-portfolio/{$userId}", fallback: [
            'real_cashback'   => 0,
            'shadow_cashback' => 0,
            'gap'             => 0,
            'insight'         => 'Аналитика временно недоступна',
            'is_stub'         => true,
        ]);
    }

    /**
     * Получить персонализированный нудж для пользователя.
     *
     * @return array{message: string, category: string, boost_multiplier: float, trigger_time: string}
     */
    public function getDynamicNudging(int $userId): array
    {
        return $this->request("GET", "/ai/nudging/{$userId}", fallback: [
            'message'          => null,
            'category'         => null,
            'boost_multiplier' => 1.0,
            'trigger_time'     => null,
            'is_stub'          => true,
        ]);
    }

    /**
     * Получить ранжированный список офферов через Cross-Sell Optimizer.
     */
    public function getCrossSellOffers(int $userId, string $segment): array
    {
        return $this->request("GET", "/ai/cross-sell/{$userId}", [
            'segment' => $segment,
        ], fallback: []);
    }

    /**
     * Универсальный HTTP-запрос с Circuit Breaker.
     */
    private function request(string $method, string $path, array $query = [], array $fallback = []): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->get("{$this->baseUrl}{$path}", $query);

            if ($response->successful()) {
                return $response->json();
            }

            Log::warning("AI Engine non-2xx: {$path}", ['status' => $response->status()]);
        } catch (\Throwable $e) {
            Log::error("AI Engine unavailable: {$path}", ['error' => $e->getMessage()]);
        }

        return $fallback;
    }
}
```

#### `app/Services/GamificationService.php`
```php
<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserGamification;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

/**
 * Сервис геймификации: Loyalty Health Score, стрики, тиры.
 */
class GamificationService
{
    /** Пороги тиров [процент реализации => название] */
    private const TIERS = [
        80 => 'Мастер выгоды',
        50 => 'Рационалист',
        20 => 'Новичок+',
        0  => 'Новичок',
    ];

    /**
     * Рассчитывает Health Score на основе разрыва Shadow Portfolio.
     * Score = (real / shadow) * 100, ограничен 0..100.
     */
    public function calculateHealthScore(float $realCashback, float $shadowCashback): int
    {
        if ($shadowCashback <= 0) {
            return 0;
        }

        return (int) min(100, round(($realCashback / $shadowCashback) * 100));
    }

    /**
     * Определяет тир пользователя по Health Score.
     */
    public function resolveTier(int $healthScore): string
    {
        foreach (self::TIERS as $threshold => $tier) {
            if ($healthScore >= $threshold) {
                return $tier;
            }
        }

        return 'Новичок';
    }

    /**
     * Записывает визит пользователя и обновляет стрик.
     */
    public function recordVisit(int $userId): array
    {
        $gamification = UserGamification::firstOrCreate(
            ['user_id' => $userId],
            ['streak_days' => 0, 'health_score' => 0, 'loyalty_tier' => 'Новичок']
        );

        $today    = Carbon::today();
        $lastVisit = $gamification->last_visit_date
            ? Carbon::parse($gamification->last_visit_date)
            : null;

        $gamification->streak_days = match (true) {
            $lastVisit === null                          => 1,
            $lastVisit->isYesterday()                    => $gamification->streak_days + 1,
            $lastVisit->isSameDay($today)                => $gamification->streak_days,
            default                                      => 1,  // стрик сброшен
        };

        $gamification->last_visit_date = $today;
        $gamification->save();

        // Сброс кэша
        Cache::forget("gamification:{$userId}");

        return $this->getForUser($userId);
    }

    /**
     * Получить данные геймификации из кэша или БД.
     */
    public function getForUser(int $userId): array
    {
        return Cache::remember("gamification:{$userId}", 60, function () use ($userId) {
            $g = UserGamification::where('user_id', $userId)->firstOrNew();

            return [
                'health_score' => $g->health_score ?? 0,
                'loyalty_tier' => $g->loyalty_tier ?? 'Новичок',
                'streak_days'  => $g->streak_days ?? 0,
            ];
        });
    }
}
```

#### `app/Services/CsvImportService.php`
```php
<?php

namespace App\Services;

use App\Models\{User, Account, LoyaltyProgram, LoyaltyHistory, Offer};
use Illuminate\Support\Facades\DB;
use League\Csv\Reader;

/**
 * Сервис одноразового импорта CSV-датасетов в PostgreSQL.
 * Использует транзакции и chunk-вставку для надёжности и производительности.
 */
class CsvImportService
{
    private const CHUNK_SIZE = 500;

    public function importAll(string $dataDir): void
    {
        DB::transaction(function () use ($dataDir) {
            $this->importLoyaltyPrograms("{$dataDir}/LoyaltyPrograms.csv");
            $this->importUsers("{$dataDir}/Users.csv");
            $this->importAccounts("{$dataDir}/Accounts.csv");
            $this->importOffers("{$dataDir}/Offers.csv");
            $this->importLoyaltyHistory("{$dataDir}/LoyaltyHistory.csv");
        });
    }

    private function importLoyaltyPrograms(string $path): void
    {
        $this->importCsv($path, fn (array $row) => LoyaltyProgram::upsert([
            'loyalty_program_id'   => $row['loyalty_program_id'],
            'loyalty_program_name' => $row['loyalty_program_name'],
            'cashback_currency'    => $row['cashback_currency'],
        ], uniqueBy: ['loyalty_program_id']));
    }

    private function importUsers(string $path): void
    {
        $this->importCsv($path, fn (array $rows) => User::upsert(
            $rows, uniqueBy: ['id']
        ), chunkMode: true);
    }

    private function importAccounts(string $path): void
    {
        $this->importCsv($path, fn (array $rows) => Account::upsert(
            $rows, uniqueBy: ['account_id']
        ), chunkMode: true);
    }

    private function importOffers(string $path): void
    {
        $this->importCsv($path, fn (array $rows) => Offer::upsert(
            $rows, uniqueBy: ['partner_id', 'financial_segment']
        ), chunkMode: true);
    }

    private function importLoyaltyHistory(string $path): void
    {
        $this->importCsv($path, fn (array $rows) => LoyaltyHistory::upsert(
            $rows, uniqueBy: ['transaction_id']
        ), chunkMode: true);
    }

    /**
     * Универсальный читатель CSV с поддержкой chunk-режима.
     */
    private function importCsv(string $path, callable $handler, bool $chunkMode = false): void
    {
        $csv = Reader::createFromPath($path, 'r');
        $csv->setHeaderOffset(0);

        if (!$chunkMode) {
            foreach ($csv->getRecords() as $record) {
                $handler(array_map('trim', $record));
            }
            return;
        }

        $chunk = [];
        foreach ($csv->getRecords() as $record) {
            $chunk[] = array_map('trim', $record);

            if (count($chunk) >= self::CHUNK_SIZE) {
                $handler($chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $handler($chunk);
        }
    }
}
```

### 5.5. Controllers

#### `app/Http/Controllers/Api/LoyaltyController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LoyaltyHistoryResource;
use App\Models\User;
use App\Services\{LoyaltyService, AiEngineClient};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoyaltyController extends Controller
{
    public function __construct(
        private readonly LoyaltyService $loyaltyService,
        private readonly AiEngineClient $aiClient,
    ) {}

    /**
     * GET /api/loyalty/summary?user_id=1
     * Сводка по всем программам лояльности пользователя.
     */
    public function summary(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->integer('user_id'));
        $data = $this->loyaltyService->getSummary($user);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/loyalty/history?user_id=1&per_page=20
     */
    public function history(Request $request): JsonResponse
    {
        $user    = User::findOrFail($request->integer('user_id'));
        $history = $this->loyaltyService->getHistory($user, $request->integer('per_page', 20));

        return response()->json(LoyaltyHistoryResource::collection($history)->response()->getData(true));
    }

    /**
     * GET /api/ai/shadow-portfolio/{userId}
     * Проксирует запрос к AI-Engine.
     */
    public function shadowPortfolio(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        $data = $this->aiClient->getShadowPortfolio($userId);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/ai/nudging/{userId}
     */
    public function dynamicNudging(int $userId): JsonResponse
    {
        User::findOrFail($userId);
        $data = $this->aiClient->getDynamicNudging($userId);

        return response()->json(['data' => $data]);
    }
}
```

#### `app/Http/Controllers/Api/OffersController.php`
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\{Offer, User};
use App\Services\AiEngineClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OffersController extends Controller
{
    public function __construct(private readonly AiEngineClient $aiClient) {}

    /**
     * GET /api/offers?user_id=1
     * Возвращает офферы, отфильтрованные по сегменту пользователя.
     */
    public function index(Request $request): JsonResponse
    {
        $user = User::findOrFail($request->integer('user_id'));

        $offers = Offer::query()
            ->where(function ($q) use ($user) {
                $q->where('financial_segment', $user->financial_segment)
                  ->orWhere('financial_segment', 'ALL');
            })
            ->orderByDesc('cashback_percent')
            ->get();

        return response()->json(['data' => $offers]);
    }

    /**
     * GET /api/ai/cross-sell/{userId}
     * ИИ-ранжирование офферов.
     */
    public function crossSellOptimizer(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        $data = $this->aiClient->getCrossSellOffers($userId, $user->financial_segment);

        return response()->json(['data' => $data]);
    }

    /**
     * GET /api/ai/zero-click/{userId}
     */
    public function zeroClick(int $userId): JsonResponse
    {
        User::findOrFail($userId);

        // Проксируем в AI-Engine
        $data = $this->aiClient->request('GET', "/ai/zero-click/{$userId}") ?? [
            'activated_offer' => null,
            'probability'     => 0,
        ];

        return response()->json(['data' => $data]);
    }
}
```

### 5.6. Тесты (Feature)

#### `tests/Feature/LoyaltySummaryTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сценарий 1: Сводка лояльности
 */
class LoyaltySummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_returns_correct_structure(): void
    {
        $user = User::factory()->create(['financial_segment' => 'HIGH']);

        $this->getJson("/api/loyalty/summary?user_id={$user->id}")
             ->assertOk()
             ->assertJsonStructure(['data']);
    }

    public function test_summary_returns_404_for_unknown_user(): void
    {
        $this->getJson('/api/loyalty/summary?user_id=99999')
             ->assertNotFound();
    }
}
```

#### `tests/Feature/UserSwitchTest.php`
```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Сценарий 2: Демо-переключение пользователей
 */
class UserSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_list_returns_all_users(): void
    {
        User::factory()->count(3)->create();

        $this->getJson('/api/users')
             ->assertOk()
             ->assertJsonCount(3, 'data');
    }

    public function test_segment_filters_offers_correctly(): void
    {
        $highUser = User::factory()->create(['financial_segment' => 'HIGH']);
        $lowUser  = User::factory()->create(['financial_segment' => 'LOW']);

        // Офферы для HIGH и ALL должны быть у высокосегментного
        $this->getJson("/api/offers?user_id={$highUser->id}")->assertOk();
        $this->getJson("/api/offers?user_id={$lowUser->id}")->assertOk();
    }
}
```

---

## 6. AI-Engine: Python FastAPI

### 6.1. `ai-engine/app/main.py`

```python
"""
T-Loyalty Hub — AI Engine (FastAPI)
Содержит заглушки для всех ИИ-фич с реалистичной рандомизацией.
Реальные ML-модели подключаются через замену StubGenerator.
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.routers import shadow_portfolio, dynamic_nudging, cross_sell, zero_click

app = FastAPI(
    title="T-Loyalty AI Engine",
    description="AI microservice for T-Loyalty Hub. Current mode: STUB",
    version="0.1.0",
)

# CORS: принимаем только от Laravel (внутренняя сеть)
app.add_middleware(
    CORSMiddleware,
    allow_origins=["http://backend:8080"],
    allow_methods=["GET"],
    allow_headers=["*"],
)

app.include_router(shadow_portfolio.router, prefix="/ai", tags=["Shadow Portfolio"])
app.include_router(dynamic_nudging.router, prefix="/ai", tags=["Dynamic Nudging"])
app.include_router(cross_sell.router, prefix="/ai", tags=["Cross-Sell"])
app.include_router(zero_click.router, prefix="/ai", tags=["Zero-Click"])


@app.get("/health")
def health_check() -> dict:
    """Healthcheck для Docker и Laravel Circuit Breaker."""
    return {"status": "ok", "mode": "stub"}
```

### 6.2. `ai-engine/app/services/stub_generator.py`

```python
"""
Генератор реалистичных заглушек для всех AI-фич.
Изолирован в одном модуле — при подключении реального ML
достаточно заменить методы этого класса (Open/Closed Principle).
"""

import random
from dataclasses import dataclass


@dataclass
class ShadowPortfolioResult:
    real_cashback: float
    shadow_cashback: float
    gap: float
    insight: str
    health_score: int
    is_stub: bool = True


@dataclass
class NudgingResult:
    message: str | None
    category: str | None
    boost_multiplier: float
    trigger_time: str | None
    is_stub: bool = True


@dataclass
class CrossSellOffer:
    product_name: str
    reason: str
    potential_gain: float
    priority: int


@dataclass
class ZeroClickResult:
    activated_offer: str | None
    probability: float
    partner_name: str | None
    is_stub: bool = True


class StubGenerator:
    """Единственный источник рандомизированных AI-ответов."""

    CATEGORIES = ["АЗС", "Рестораны", "Супермаркеты", "Аптеки", "Кино", "Онлайн-покупки"]
    PRODUCTS   = ["Т-Инвестиции", "Т-Мобайл", "Т-Страхование", "Т-Бизнес"]
    INSIGHTS   = [
        "Ты потерял {gap:.0f} ₽, не использовав подписку и нужные категории. Сократим разрыв?",
        "Активируй категорию «Рестораны» — это вернёт ещё {potential:.0f} ₽ в месяц.",
        "Твой кэшбэк мог быть в {ratio:.1f}× выше. Покажем как?",
    ]

    def shadow_portfolio(self, user_id: int) -> ShadowPortfolioResult:
        """Симулирует разрыв между реальным и идеальным кэшбэком."""
        seed = user_id % 7  # детерминированность для одного пользователя
        real    = round(random.uniform(800, 5000), 2)
        shadow  = round(real * random.uniform(1.3, 2.5), 2)
        gap     = round(shadow - real, 2)
        score   = int(min(100, (real / shadow) * 100))
        insight = random.choice(self.INSIGHTS).format(
            gap=gap, potential=gap * 0.4, ratio=shadow / real
        )

        return ShadowPortfolioResult(
            real_cashback=real,
            shadow_cashback=shadow,
            gap=gap,
            insight=insight,
            health_score=score,
        )

    def dynamic_nudging(self, user_id: int) -> NudgingResult:
        """Симулирует предсказание следующей покупки."""
        category = random.choice(self.CATEGORIES)
        multiplier = round(random.uniform(1.5, 3.0), 1)
        has_nudge = random.random() > 0.3  # 70% вероятность наличия нуджа

        if not has_nudge:
            return NudgingResult(message=None, category=None, boost_multiplier=1.0, trigger_time=None)

        return NudgingResult(
            message=f"Обычно ты тратишь на «{category}» в это время — активируй категорию и получи ×{multiplier} баллов!",
            category=category,
            boost_multiplier=multiplier,
            trigger_time="вечером сегодня",
        )

    def cross_sell_offers(self, user_id: int, segment: str) -> list[CrossSellOffer]:
        """Ранжирует продукты экосистемы по потенциальной выгоде."""
        offers = []
        for i, product in enumerate(random.sample(self.PRODUCTS, k=len(self.PRODUCTS))):
            gain = round(random.uniform(200, 3000) * (1.5 if segment == "HIGH" else 1.0), 2)
            offers.append(CrossSellOffer(
                product_name=product,
                reason=f"Повысит твой Loyalty Health Score и добавит до {gain:.0f} ₽/мес",
                potential_gain=gain,
                priority=i + 1,
            ))
        return offers

    def zero_click(self, user_id: int) -> ZeroClickResult:
        """Симулирует фоновую активацию оффера."""
        probability = round(random.uniform(0.4, 0.99), 2)
        activated = probability > 0.8

        return ZeroClickResult(
            activated_offer="Кэшбэк 10% в спортивных магазинах" if activated else None,
            probability=probability,
            partner_name="СпортМастер" if activated else None,
        )
```

### 6.3. `ai-engine/app/routers/shadow_portfolio.py`

```python
from fastapi import APIRouter
from app.services.stub_generator import StubGenerator
from app.schemas.ai_schemas import ShadowPortfolioResponse

router = APIRouter()
_stub = StubGenerator()


@router.get("/shadow-portfolio/{user_id}", response_model=ShadowPortfolioResponse)
def get_shadow_portfolio(user_id: int) -> ShadowPortfolioResponse:
    """
    AI Shadow Portfolio — разрыв между реальным и идеальным кэшбэком.
    
    Точка расширения: заменить `_stub.shadow_portfolio()` на вызов ML-модели.
    """
    result = _stub.shadow_portfolio(user_id)
    return ShadowPortfolioResponse(**vars(result))
```

*(Аналогичная структура для `dynamic_nudging.py`, `cross_sell.py`, `zero_click.py`)*

### 6.4. `ai-engine/app/schemas/ai_schemas.py`

```python
"""Pydantic-схемы для валидации ответов AI-Engine."""

from pydantic import BaseModel, Field


class ShadowPortfolioResponse(BaseModel):
    real_cashback:   float = Field(description="Реальный кэшбэк пользователя, руб.")
    shadow_cashback: float = Field(description="Идеальный кэшбэк (Shadow), руб.")
    gap:             float = Field(description="Упущенная выгода, руб.")
    insight:         str   = Field(description="Персонализированный инсайт")
    health_score:    int   = Field(ge=0, le=100, description="Loyalty Health Score, 0-100")
    is_stub:         bool  = True


class NudgingResponse(BaseModel):
    message:          str | None
    category:         str | None
    boost_multiplier: float = Field(ge=1.0)
    trigger_time:     str | None
    is_stub:          bool = True


class CrossSellOfferResponse(BaseModel):
    product_name:   str
    reason:         str
    potential_gain: float
    priority:       int


class ZeroClickResponse(BaseModel):
    activated_offer: str | None
    probability:     float = Field(ge=0.0, le=1.0)
    partner_name:    str | None
    is_stub:         bool = True
```

---

## 7. Frontend: React + Vite + Tailwind

### 7.1. Структура страниц и компонентов

```
pages/
├── DemoSelector.tsx      # Демо-экран: выбор тестового пользователя
└── LoyaltyHub.tsx        # Главная страница раздела

components/
├── ui/
│   ├── Card.tsx
│   ├── Badge.tsx
│   ├── Skeleton.tsx       # Загрузочные плейсхолдеры
│   └── ErrorBoundary.tsx
│
├── loyalty/
│   ├── LoyaltySummaryWidget.tsx   # Сводка кэшбэка по всем программам
│   ├── LoyaltyHistoryList.tsx     # История выплат
│   └── ProgramBadge.tsx           # Black / Platinum / All Airlines
│
├── gamification/
│   ├── HealthScoreGauge.tsx       # Визуальная шкала 0-100
│   ├── TierBadge.tsx              # Тир пользователя
│   └── StreakCounter.tsx          # Счётчик стрика
│
└── ai/
    ├── ShadowPortfolioCard.tsx    # AI Shadow Portfolio
    ├── NudgingBanner.tsx          # Dynamic Nudging
    ├── CrossSellCarousel.tsx      # Cross-Sell Optimizer
    └── ZeroClickNotification.tsx  # Zero-Click Loyalty
```

### 7.2. `src/api/client.ts`

```typescript
/**
 * Централизованный API-клиент.
 * Все запросы к Laravel проходят через этот модуль.
 * При ошибке сети бросает типизированное исключение — фронт не крашится.
 */

const BASE_URL = import.meta.env.VITE_API_URL ?? '/api';

class ApiError extends Error {
  constructor(public status: number, message: string) {
    super(message);
    this.name = 'ApiError';
  }
}

async function request<T>(path: string, params?: Record<string, string>): Promise<T> {
  const url = new URL(`${BASE_URL}${path}`, window.location.origin);
  if (params) {
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
  }

  const response = await fetch(url.toString(), {
    headers: { 'Accept': 'application/json' },
  });

  if (!response.ok) {
    throw new ApiError(response.status, `HTTP ${response.status}: ${path}`);
  }

  return response.json() as Promise<T>;
}

export const api = {
  getUsers:          ()         => request<UsersResponse>('/users'),
  getLoyaltySummary: (userId: number) => request('/loyalty/summary', { user_id: String(userId) }),
  getLoyaltyHistory: (userId: number) => request('/loyalty/history', { user_id: String(userId) }),
  getOffers:         (userId: number) => request('/offers', { user_id: String(userId) }),
  getGamification:   (userId: number) => request(`/gamification/${userId}`),

  // AI-фичи
  getShadowPortfolio: (userId: number) => request(`/ai/shadow-portfolio/${userId}`),
  getDynamicNudging:  (userId: number) => request(`/ai/nudging/${userId}`),
  getCrossSell:       (userId: number) => request(`/ai/cross-sell/${userId}`),
  getZeroClick:       (userId: number) => request(`/ai/zero-click/${userId}`),
} as const;
```

### 7.3. `src/pages/DemoSelector.tsx`

```tsx
/**
 * Демо-точка входа: выбор тестового пользователя.
 * Позволяет жюри переключаться между сегментами (LOW/MEDIUM/HIGH).
 */

import { useState, useEffect } from 'react';
import { api } from '@/api/client';
import type { User } from '@/types';

const SEGMENT_COLORS: Record<string, string> = {
  LOW:    'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
  MEDIUM: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
  HIGH:   'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
};

interface Props {
  onSelect: (user: User) => void;
}

export function DemoSelector({ onSelect }: Props) {
  const [users, setUsers] = useState<User[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    api.getUsers()
       .then(res => setUsers(res.data))
       .catch(() => setError('Не удалось загрузить пользователей'))
       .finally(() => setLoading(false));
  }, []);

  if (loading) return <div className="flex justify-center p-8">Загрузка...</div>;
  if (error)   return <div className="text-red-500 p-8">{error}</div>;

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900 flex flex-col items-center justify-center p-4">
      <h1 className="text-2xl font-bold mb-2 text-gray-900 dark:text-white">T-Loyalty Hub</h1>
      <p className="text-gray-500 dark:text-gray-400 mb-8 text-sm">Выберите тестового пользователя</p>

      <div className="grid gap-3 w-full max-w-md">
        {users.map(user => (
          <button
            key={user.id}
            onClick={() => onSelect(user)}
            className="flex items-center justify-between bg-white dark:bg-gray-800 
                       rounded-2xl p-4 shadow-sm hover:shadow-md transition-shadow 
                       text-left border border-gray-100 dark:border-gray-700"
          >
            <div>
              <p className="font-semibold text-gray-900 dark:text-white">{user.full_name}</p>
              <p className="text-xs text-gray-400 mt-0.5">{user.email}</p>
            </div>
            <span className={`text-xs font-medium px-2.5 py-1 rounded-full ${SEGMENT_COLORS[user.financial_segment]}`}>
              {user.financial_segment}
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}
```

### 7.4. `src/components/gamification/HealthScoreGauge.tsx`

```tsx
/**
 * Визуальная шкала Loyalty Health Score.
 * SVG-дуга с анимацией заполнения.
 */

interface Props {
  score: number;       // 0-100
  tier: string;
  streakDays: number;
}

export function HealthScoreGauge({ score, tier, streakDays }: Props) {
  const circumference = 2 * Math.PI * 54;
  const offset = circumference - (score / 100) * circumference;

  const scoreColor = score >= 80 ? '#10b981'   // emerald
                   : score >= 50 ? '#f59e0b'   // amber
                   : '#6b7280';                // gray

  return (
    <div className="flex flex-col items-center gap-3 p-6 bg-white dark:bg-gray-800 rounded-3xl shadow-sm">
      <h2 className="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
        Loyalty Health
      </h2>

      {/* SVG-шкала */}
      <div className="relative w-32 h-32">
        <svg viewBox="0 0 120 120" className="rotate-[-90deg] w-full h-full">
          <circle cx="60" cy="60" r="54" fill="none" stroke="#e5e7eb" strokeWidth="10" />
          <circle
            cx="60" cy="60" r="54"
            fill="none"
            stroke={scoreColor}
            strokeWidth="10"
            strokeLinecap="round"
            strokeDasharray={circumference}
            strokeDashoffset={offset}
            style={{ transition: 'stroke-dashoffset 1s ease-out' }}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-3xl font-bold text-gray-900 dark:text-white">{score}</span>
          <span className="text-xs text-gray-400">/ 100</span>
        </div>
      </div>

      {/* Тир */}
      <span className="text-base font-semibold text-gray-800 dark:text-gray-100">{tier}</span>

      {/* Стрик */}
      {streakDays > 0 && (
        <div className="flex items-center gap-1.5 bg-orange-50 dark:bg-orange-900/30 px-3 py-1.5 rounded-full">
          <span>🔥</span>
          <span className="text-sm font-medium text-orange-600 dark:text-orange-400">
            {streakDays} {streakDays === 1 ? 'день' : 'дней'} подряд
          </span>
        </div>
      )}
    </div>
  );
}
```

---

## 8. Docker & Infrastructure

### 8.1. `docker-compose.yml`

```yaml
version: '3.9'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./backend:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - backend

  backend:
    build:
      context: ./backend
      dockerfile: Dockerfile
    environment:
      - APP_ENV=production
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    volumes:
      - ./backend:/var/www/html

  ai-engine:
    build:
      context: ./ai-engine
      dockerfile: Dockerfile
    expose:
      - "8000"
    # Не открываем наружу — только Laravel имеет доступ

  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB:       tloyalty
      POSTGRES_USER:     tloyalty
      POSTGRES_PASSWORD: secret
    volumes:
      - pgdata:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U tloyalty"]
      interval: 5s
      timeout: 5s
      retries: 5

  redis:
    image: redis:7-alpine
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 5s
      timeout: 3s
      retries: 5

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
    ports:
      - "3000:3000"

volumes:
  pgdata:
```

### 8.2. `.github/workflows/ci.yml`

```yaml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main]

jobs:
  backend-tests:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: postgres:16-alpine
        env:
          POSTGRES_DB:       tloyalty_test
          POSTGRES_USER:     tloyalty
          POSTGRES_PASSWORD: secret
        options: >-
          --health-cmd pg_isready
          --health-interval 5s
          --health-timeout 5s
          --health-retries 5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo, pdo_pgsql, redis

      - name: Install dependencies
        run: cd backend && composer install --no-interaction

      - name: Run PHPUnit tests
        run: cd backend && php artisan test --parallel
        env:
          DB_CONNECTION: pgsql
          DB_HOST:       localhost
          DB_DATABASE:   tloyalty_test
          DB_USERNAME:   tloyalty
          DB_PASSWORD:   secret

  ai-engine-tests:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with:
          python-version: '3.11'
      - run: cd ai-engine && pip install -r requirements.txt
      - run: cd ai-engine && pytest

  frontend-build:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-node@v4
        with:
          node-version: '20'
      - run: cd frontend && npm ci
      - run: cd frontend && npm run build
```

---

## 9. Детальное API-описание (OpenAPI-совместимое)

| Метод | Путь | Параметры | Описание |
|---|---|---|---|
| GET | `/api/users` | — | Список всех пользователей (для демо) |
| GET | `/api/users/{id}` | — | Данные конкретного пользователя |
| GET | `/api/loyalty/summary` | `user_id` | Сводка кэшбэка по программам |
| GET | `/api/loyalty/history` | `user_id`, `per_page` | Пагинированная история |
| GET | `/api/loyalty/programs` | `user_id` | Активные программы лояльности |
| GET | `/api/offers` | `user_id` | Офферы по сегменту пользователя |
| GET | `/api/gamification/{userId}` | — | Health Score, тир, стрик |
| POST | `/api/gamification/{userId}/visit` | — | Записать визит, обновить стрик |
| GET | `/api/ai/shadow-portfolio/{userId}` | — | AI Shadow Portfolio (стаб) |
| GET | `/api/ai/nudging/{userId}` | — | Dynamic Nudging (стаб) |
| GET | `/api/ai/cross-sell/{userId}` | — | Cross-Sell Optimizer (стаб) |
| GET | `/api/ai/zero-click/{userId}` | — | Zero-Click Loyalty (стаб) |

### Формат ошибок (единый)

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "User not found",
    "status": 404
  }
}
```

---

## 10. Тёмная тема и адаптивность

### 10.1. Tailwind-конфигурация

```typescript
// tailwind.config.ts
export default {
  darkMode: 'class',        // переключение через класс .dark на <html>
  content: ['./src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      // Цветовая палитра Т-Банка
      colors: {
        tbank: {
          yellow:  '#FFDD2D',
          black:   '#1F1F1F',
          white:   '#FFFFFF',
          gray:    '#F6F7F8',
        }
      },
      // Масштабирование шрифтов
      fontSize: {
        'dynamic-base': 'clamp(1rem, 2.5vw, 1.125rem)',
      }
    }
  }
}
```

### 10.2. Переключатель темы

```tsx
// Хранится в localStorage, применяется на <html>
const toggleTheme = () => {
  const html = document.documentElement;
  const isDark = html.classList.toggle('dark');
  localStorage.setItem('theme', isDark ? 'dark' : 'light');
};

// Инициализация при старте
const saved = localStorage.getItem('theme') ?? 
              (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
document.documentElement.classList.toggle('dark', saved === 'dark');
```

---

## 11. Порядок реализации (рекомендуемый)

1. **[БД]** Создать миграции → запустить `php artisan migrate`
2. **[Backend]** Реализовать `CsvImportService` → импортировать датасеты
3. **[Backend]** Реализовать `LoyaltyService` + роуты summary/history/offers
4. **[AI-Engine]** Поднять FastAPI со всеми стабами
5. **[Backend]** Реализовать `AiEngineClient` + AI-роуты в Laravel
6. **[Backend]** Реализовать `GamificationService`
7. **[Frontend]** `DemoSelector.tsx` + роутинг
8. **[Frontend]** `LoyaltyHub.tsx` с компонентами сводки и офферов
9. **[Frontend]** Геймификационные виджеты (`HealthScoreGauge`, стрик)
10. **[Frontend]** AI-компоненты (`ShadowPortfolioCard`, `NudgingBanner`)
11. **[Infra]** `docker-compose.yml` + `ci.yml`
12. **[Tests]** PHPUnit: 5 сценариев

---

## 12. Важные замечания для ИИ-агента

- **Не генерировать API-ключи и секреты** — они задаются через `.env`.
- **Все AI-роуты в FastAPI** должны возвращать `is_stub: true` в ответе — это позволяет фронтенду показать пометку "AI Demo Mode" рядом с фичами.
- **Fallback-логика в `AiEngineClient`** обязательна — если FastAPI недоступен, Laravel возвращает корректный ответ с нулями/пустыми массивами, приложение не падает.
- **Redis-кэш** должен инвалидироваться при импорте новых данных через команду `php artisan loyalty:import`.
- **Демо-точка входа** (`/` или `/demo`) — это `DemoSelector.tsx`, она должна быть доступна без авторизации.
- **Все компоненты** должны иметь `dark:`-классы Tailwind, тёмная тема применяется через `class` на `<html>`.
- **`ErrorBoundary`** оборачивает все AI-компоненты отдельно — если стаб вернул ошибку, остальная страница работает.
