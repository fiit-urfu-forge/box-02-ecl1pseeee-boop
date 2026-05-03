# Архитектура T-Loyalty Hub

```mermaid
flowchart TB
    User["👤 Пользователь<br/>телефон / десктоп"]

    subgraph Frontend["🎨 FRONTEND — React + Vite"]
        FE["Mobile-first SPA<br/>Сегментные темы Black / Standard / Старт<br/>Тёмная тема · ErrorBoundary"]
    end

    subgraph Backend["⚙️ BACKEND — Laravel 11 (оркестратор)"]
        L1["Лояльность<br/>кэшбэк, история, офферы"]
        L2["Геймификация<br/>Health Score, тиры, стрики"]
        L3["AI-прокси<br/>Circuit Breaker + fallback"]
    end

    subgraph Storage["💾 ХРАНЕНИЕ"]
        PG[("🗄️ PostgreSQL<br/>users, accounts,<br/>history, offers")]
        RD[("⚡ Redis<br/>кэш с TTL<br/>сводка 5м · нудж 1ч")]
    end

    subgraph AI["🧠 AI-ENGINE — Python / FastAPI<br/>(внутренняя Docker-сеть)"]
        AI1["Shadow Portfolio"]
        AI2["Dynamic Nudging"]
        AI3["Cross-Sell Optimizer"]
        AI4["Zero-Click Loyalty"]
    end

    User -->|HTTPS| Frontend
    Frontend -->|REST / JSON| Backend
    Backend --> PG
    Backend --> RD
    Backend -.->|HTTP, timeout 5s| AI

    classDef frontend fill:#FFDD2D,stroke:#1F1F1F,color:#1F1F1F
    classDef backend fill:#1F1F1F,stroke:#FFDD2D,color:#fff
    classDef storage fill:#F6F7F8,stroke:#9ca3af,color:#1F1F1F
    classDef ai fill:#e0f2fe,stroke:#0284c7,color:#0c4a6e

    class FE frontend
    class L1,L2,L3 backend
    class PG,RD storage
    class AI1,AI2,AI3,AI4 ai
```

## Как это работает простыми словами

1. **Пользователь** открывает приложение в браузере на телефоне или десктопе.
2. **Frontend** показывает интерфейс в стиле Т-Банка и шлёт запросы только в Laravel.
3. **Backend** на Laravel — «дирижёр»: проверяет запрос, идёт в кэш Redis, при необходимости — в PostgreSQL и в AI-Engine, собирает ответ.
4. **PostgreSQL** хранит всё надолго, **Redis** — быстрый кэш на минуты.
5. **AI-Engine** на Python отвечает за все ИИ-фичи. Спрятан во внутренней сети — снаружи к нему не достучаться, только через Laravel.
6. Если AI-Engine падает, Laravel возвращает резервный ответ (Circuit Breaker) — приложение не ломается.

Запуск всей системы — одна команда: `docker-compose up`.
