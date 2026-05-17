# Smart Kanban — Спецификация для Claude Code

> **Назначение документа:** полная техническая спецификация для написания production-grade кода.  
> Claude Code должен следовать каждому разделу точно и полностью, не пропуская ни одного требования.

---

## 0. Контекст продукта

**Smart Kanban** — это real-time канбан-система уровня Jira/Trello с event-driven архитектурой, AI-автоматизацией и Telegram-ботом. Продукт решает три ключевые боли команд:

1. **Тимлиды** тратят до 40 минут после каждого митинга на ручное создание карточек → AI протоколирует созвон и создаёт таски автоматически.
2. **Разработчики** теряют контекст при работе с большими задачами и багами → AI декомпозирует задачи, бот принимает баги через скриншот.
3. **Менеджеры** вручную мониторят статусы и пингуют команду → AI присылает ежедневную выжимку, бот напоминает о дедлайнах.

**Целевая аудитория:**
- **Пользователи:** разработчики, дизайнеры, тестировщики
- **Администраторы:** тимлиды, project-менеджеры, scrum-мастера

---

## 1. Технологический стек

### Backend
| Компонент | Технология | Обоснование |
|-----------|-----------|-------------|
| Runtime | **Node.js 20 LTS** | Event loop идеален для real-time |
| Framework | **Fastify 4** | Быстрее Express, встроенная валидация через JSON Schema |
| WebSocket | **Socket.IO 4** | Надёжный fallback, namespaces, rooms |
| База данных | **PostgreSQL 16** | ACID, JSONB для гибких полей |
| ORM | **Prisma** | Type-safe, миграции, удобная работа с relation |
| Очередь | **BullMQ** (Redis) | RabbitMQ-совместимая семантика, built-in retry/backoff, dead-letter |
| Кэш/PubSub | **Redis 7** | Bull, Socket.IO adapter, rate limiting |
| AI | **Anthropic Claude API** (claude-sonnet-4-20250514) | Декомпозиция, выжимка дня |
| Telegram | **Telegraf 4** | Telegram Bot API |
| Аутентификация | **JWT + Refresh Token** (jose) | Stateless, масштабируемо |
| Валидация | **Zod** | Runtime validation + TypeScript inference |
| Логирование | **Pino** | Структурированные JSON-логи |
| Тесты | **Vitest + Supertest** | Юнит + интеграционные тесты |

### Frontend
| Компонент | Технология |
|-----------|-----------|
| Framework | **React 18 + TypeScript** |
| Build | **Vite 5** |
| State | **Zustand** (глобальный) + **TanStack Query v5** (серверный) |
| UI Kit | **shadcn/ui** + **Tailwind CSS 3** |
| DnD | **@dnd-kit/core** (доступный, без legacy) |
| WebSocket | **Socket.IO client** |
| Формы | **React Hook Form + Zod** |
| Роутинг | **TanStack Router** |
| Анимации | **Framer Motion** |

### Инфраструктура
| Компонент | Технология |
|-----------|-----------|
| Контейнеризация | **Docker + Docker Compose** |
| Reverse proxy | **Nginx** (SSL termination, gzip) |
| CI/CD | **GitHub Actions** |
| ENV | **.env + dotenv-safe** (проверка обязательных переменных при старте) |

---

## 2. Архитектура системы

### 2.1 Общая схема

```
┌─────────────────────────────────────────────────────┐
│                    КЛИЕНТЫ                          │
│  React SPA          Telegram Bot       External API │
└────────┬──────────────────┬────────────────┬────────┘
         │ HTTP/WS          │ Webhooks       │ REST
         ▼                  ▼                ▼
┌─────────────────────────────────────────────────────┐
│                  NGINX (Reverse Proxy)              │
└────────────────────────┬────────────────────────────┘
                         ▼
┌─────────────────────────────────────────────────────┐
│              FASTIFY API SERVER                     │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────┐  │
│  │  Routes  │  │Socket.IO │  │  Event Emitter   │  │
│  └────┬─────┘  └────┬─────┘  └────────┬─────────┘  │
│       │              │                │             │
│  ┌────▼──────────────▼────────────────▼─────────┐  │
│  │              Service Layer                    │  │
│  │  TaskService │ BoardService │ AutomationSvc   │  │
│  └────┬─────────────────────────────────────────┘  │
│       │                                             │
│  ┌────▼────────────────────┐  ┌──────────────────┐  │
│  │     Prisma ORM          │  │   BullMQ Workers │  │
│  └────┬────────────────────┘  └────────┬─────────┘  │
└───────┼────────────────────────────────┼────────────┘
        ▼                                ▼
┌───────────────┐              ┌──────────────────────┐
│  PostgreSQL   │              │        Redis         │
│  (primary DB) │              │  (Queue + PubSub)    │
└───────────────┘              └──────────────────────┘
```

### 2.2 Event-Driven Flow

```
Пользователь перетаскивает карточку
        │
        ▼
HTTP PATCH /tasks/:id/move
        │
        ▼
TaskService.move()
   ├── Обновление в PostgreSQL (транзакция)
   ├── Emit события в EventBus
   │       │
   │       ├── AutomationService подписан → проверяет правила →
   │       │   если тег "Баг" → передвигает в топ колонки
   │       │   если статус "Done" → отправляет уведомление
   │       │
   │       └── NotificationService → пушит в очередь BullMQ
   │
   ├── Socket.IO emit('task:moved', payload) → все клиенты в room
   │
   └── BullMQ: job в очередь task_events (для надёжной доставки)
              │
              ▼
         Worker обрабатывает job:
         ├── Telegram уведомление (если нужно)
         └── Webhook к внешним системам
```

### 2.3 Структура монорепозитория

```
smart-kanban/
├── apps/
│   ├── api/                    # Fastify backend
│   │   ├── src/
│   │   │   ├── config/         # Конфиги (env, redis, db)
│   │   │   ├── modules/        # Feature modules
│   │   │   │   ├── auth/
│   │   │   │   ├── boards/
│   │   │   │   ├── tasks/
│   │   │   │   ├── columns/
│   │   │   │   ├── automation/
│   │   │   │   ├── notifications/
│   │   │   │   ├── queue/
│   │   │   │   └── ai/
│   │   │   ├── shared/
│   │   │   │   ├── errors/     # Кастомные ошибки
│   │   │   │   ├── middleware/ # Auth, rate-limit, etc
│   │   │   │   ├── events/     # EventBus
│   │   │   │   └── utils/
│   │   │   ├── socket/         # Socket.IO handlers
│   │   │   ├── workers/        # BullMQ workers
│   │   │   └── app.ts          # Fastify instance
│   │   ├── prisma/
│   │   │   ├── schema.prisma
│   │   │   └── migrations/
│   │   ├── tests/
│   │   └── package.json
│   │
│   ├── web/                    # React frontend
│   │   ├── src/
│   │   │   ├── components/
│   │   │   │   ├── ui/         # shadcn/ui базовые
│   │   │   │   ├── board/      # KanbanBoard, Column, TaskCard
│   │   │   │   ├── task/       # TaskModal, TaskForm
│   │   │   │   ├── automation/ # AutomationRuleBuilder
│   │   │   │   └── layout/     # Sidebar, Header, Notifications
│   │   │   ├── stores/         # Zustand stores
│   │   │   ├── hooks/          # Custom hooks
│   │   │   ├── api/            # API client + React Query
│   │   │   ├── socket/         # Socket.IO client setup
│   │   │   ├── types/          # Shared TypeScript types
│   │   │   └── pages/          # Route pages
│   │   └── package.json
│   │
│   └── bot/                    # Telegram bot
│       ├── src/
│       │   ├── commands/       # /start, /tasks, /summary
│       │   ├── handlers/       # Photo handler (bug from screenshot)
│       │   ├── scenes/         # Multi-step dialogs
│       │   └── bot.ts
│       └── package.json
│
├── packages/
│   └── shared/                 # Shared types между apps
│       ├── src/
│       │   ├── types/          # Task, Board, Column interfaces
│       │   ├── schemas/        # Zod schemas
│       │   └── constants/      # Shared constants
│       └── package.json
│
├── docker/
│   ├── nginx/
│   │   └── nginx.conf
│   └── postgres/
│       └── init.sql
├── docker-compose.yml
├── docker-compose.prod.yml
└── README.md
```

---

## 3. База данных (Prisma Schema)

```prisma
generator client {
  provider = "prisma-client-js"
}

datasource db {
  provider = "postgresql"
  url      = env("DATABASE_URL")
}

model User {
  id             String    @id @default(cuid())
  email          String    @unique
  name           String
  passwordHash   String    @map("password_hash")
  role           UserRole  @default(MEMBER)
  telegramId     String?   @unique @map("telegram_id")
  telegramChatId String?   @map("telegram_chat_id")
  avatarUrl      String?   @map("avatar_url")
  createdAt      DateTime  @default(now()) @map("created_at")
  updatedAt      DateTime  @updatedAt @map("updated_at")

  ownedBoards  Board[]         @relation("BoardOwner")
  boardMembers BoardMember[]
  assignedTasks Task[]         @relation("TaskAssignee")
  createdTasks  Task[]         @relation("TaskCreator")
  notifications Notification[]
  refreshTokens RefreshToken[]
  activityLogs  ActivityLog[]

  @@map("users")
}

model Board {
  id          String   @id @default(cuid())
  name        String
  description String?
  slug        String   @unique
  isPublic    Boolean  @default(false) @map("is_public")
  ownerId     String   @map("owner_id")
  createdAt   DateTime @default(now()) @map("created_at")
  updatedAt   DateTime @updatedAt @map("updated_at")

  owner        User             @relation("BoardOwner", fields: [ownerId], references: [id])
  members      BoardMember[]
  columns      Column[]
  tasks        Task[]
  rules        AutomationRule[]
  activityLogs ActivityLog[]

  @@map("boards")
}

model BoardMember {
  id       String          @id @default(cuid())
  boardId  String          @map("board_id")
  userId   String          @map("user_id")
  role     BoardMemberRole @default(MEMBER)
  joinedAt DateTime        @default(now()) @map("joined_at")

  board Board @relation(fields: [boardId], references: [id], onDelete: Cascade)
  user  User  @relation(fields: [userId], references: [id], onDelete: Cascade)

  @@unique([boardId, userId])
  @@map("board_members")
}

model Column {
  id        String   @id @default(cuid())
  boardId   String   @map("board_id")
  name      String
  color     String?
  position  Int
  wipLimit  Int?     @map("wip_limit")
  isDefault Boolean  @default(false) @map("is_default")
  createdAt DateTime @default(now()) @map("created_at")
  updatedAt DateTime @updatedAt @map("updated_at")

  board Board  @relation(fields: [boardId], references: [id], onDelete: Cascade)
  tasks Task[]

  @@unique([boardId, position])
  @@map("columns")
}

model Task {
  id          String       @id @default(cuid())
  boardId     String       @map("board_id")
  columnId    String       @map("column_id")
  title       String
  description String?
  priority    TaskPriority @default(MEDIUM)
  status      TaskStatus   @default(TODO)
  position    Int
  tags        String[]     @default([])
  dueDate     DateTime?    @map("due_date")
  assigneeId  String?      @map("assignee_id")
  creatorId   String       @map("creator_id")
  lockedBy    String?      @map("locked_by")
  lockedAt    DateTime?    @map("locked_at")
  metadata    Json?
  createdAt   DateTime     @default(now()) @map("created_at")
  updatedAt   DateTime     @updatedAt @map("updated_at")

  board          Board           @relation(fields: [boardId], references: [id], onDelete: Cascade)
  column         Column          @relation(fields: [columnId], references: [id])
  assignee       User?           @relation("TaskAssignee", fields: [assigneeId], references: [id])
  creator        User            @relation("TaskCreator", fields: [creatorId], references: [id])
  checklistItems ChecklistItem[]
  comments       Comment[]
  activityLogs   ActivityLog[]

  @@index([boardId, columnId, position])
  @@index([assigneeId])
  @@index([dueDate])
  @@map("tasks")
}

model ChecklistItem {
  id        String   @id @default(cuid())
  taskId    String   @map("task_id")
  text      String
  done      Boolean  @default(false)
  position  Int
  createdAt DateTime @default(now()) @map("created_at")

  task Task @relation(fields: [taskId], references: [id], onDelete: Cascade)

  @@map("checklist_items")
}

model Comment {
  id        String   @id @default(cuid())
  taskId    String   @map("task_id")
  authorId  String   @map("author_id")
  text      String
  createdAt DateTime @default(now()) @map("created_at")
  updatedAt DateTime @updatedAt @map("updated_at")

  task Task @relation(fields: [taskId], references: [id], onDelete: Cascade)

  @@map("comments")
}

model AutomationRule {
  id         String            @id @default(cuid())
  boardId    String            @map("board_id")
  name       String
  isActive   Boolean           @default(true) @map("is_active")
  trigger    AutomationTrigger
  conditions Json
  actions    Json
  createdAt  DateTime          @default(now()) @map("created_at")
  updatedAt  DateTime          @updatedAt @map("updated_at")

  board Board @relation(fields: [boardId], references: [id], onDelete: Cascade)

  @@map("automation_rules")
}

model Notification {
  id        String           @id @default(cuid())
  userId    String           @map("user_id")
  type      NotificationType
  title     String
  body      String
  payload   Json?
  isRead    Boolean          @default(false) @map("is_read")
  createdAt DateTime         @default(now()) @map("created_at")

  user User @relation(fields: [userId], references: [id], onDelete: Cascade)

  @@index([userId, isRead])
  @@map("notifications")
}

model ActivityLog {
  id        String         @id @default(cuid())
  boardId   String         @map("board_id")
  taskId    String?        @map("task_id")
  userId    String         @map("user_id")
  action    ActivityAction
  diff      Json?
  createdAt DateTime       @default(now()) @map("created_at")

  board Board  @relation(fields: [boardId], references: [id], onDelete: Cascade)
  task  Task?  @relation(fields: [taskId], references: [id], onDelete: SetNull)
  user  User   @relation(fields: [userId], references: [id])

  @@index([boardId, createdAt])
  @@map("activity_logs")
}

model IncomingTask {
  id           String             @id @default(cuid())
  source       String
  rawPayload   Json               @map("raw_payload")
  status       IncomingTaskStatus @default(PENDING)
  dedupHash    String             @unique @map("dedup_hash")
  enrichedData Json?              @map("enriched_data")
  error        String?
  attempts     Int                @default(0)
  createdAt    DateTime           @default(now()) @map("created_at")
  processedAt  DateTime?          @map("processed_at")

  @@index([status, createdAt])
  @@map("incoming_tasks")
}

model RefreshToken {
  id        String   @id @default(cuid())
  userId    String   @map("user_id")
  token     String   @unique
  expiresAt DateTime @map("expires_at")
  createdAt DateTime @default(now()) @map("created_at")

  user User @relation(fields: [userId], references: [id], onDelete: Cascade)

  @@map("refresh_tokens")
}

enum UserRole {
  ADMIN
  MEMBER
}

enum BoardMemberRole {
  OWNER
  ADMIN
  MEMBER
  VIEWER
}

enum TaskPriority {
  CRITICAL
  HIGH
  MEDIUM
  LOW
}

enum TaskStatus {
  TODO
  IN_PROGRESS
  REVIEW
  DONE
  ARCHIVED
}

enum AutomationTrigger {
  TASK_CREATED
  TASK_MOVED
  TASK_UPDATED
  TASK_ASSIGNED
  DUE_DATE_APPROACHING
  TAG_ADDED
}

enum NotificationType {
  TASK_ASSIGNED
  TASK_COMMENTED
  TASK_DUE_SOON
  TASK_OVERDUE
  AUTOMATION_TRIGGERED
  DAILY_SUMMARY
  SYSTEM
}

enum ActivityAction {
  TASK_CREATED
  TASK_UPDATED
  TASK_MOVED
  TASK_DELETED
  TASK_ASSIGNED
  TASK_COMMENTED
  COLUMN_CREATED
  COLUMN_UPDATED
  RULE_TRIGGERED
}

enum IncomingTaskStatus {
  PENDING
  PROCESSING
  DONE
  FAILED
  DUPLICATE
}
```

---

## 4. API — Полный список эндпоинтов

Все эндпоинты, кроме `/auth/*`, требуют заголовка `Authorization: Bearer <access_token>`.

### Auth
```
POST   /api/auth/register
POST   /api/auth/login
POST   /api/auth/refresh
POST   /api/auth/logout
GET    /api/auth/me
```

### Boards
```
GET    /api/boards
POST   /api/boards
GET    /api/boards/:boardId          # Полный state: board + columns + tasks
PATCH  /api/boards/:boardId
DELETE /api/boards/:boardId
GET    /api/boards/:boardId/members
POST   /api/boards/:boardId/members
DELETE /api/boards/:boardId/members/:userId
GET    /api/boards/:boardId/activity
```

### Columns
```
GET    /api/boards/:boardId/columns
POST   /api/boards/:boardId/columns
PATCH  /api/boards/:boardId/columns/:columnId
DELETE /api/boards/:boardId/columns/:columnId
POST   /api/boards/:boardId/columns/reorder
```

### Tasks
```
GET    /api/boards/:boardId/tasks    # С фильтрами: priority, assignee, tag, dueDate
POST   /api/boards/:boardId/tasks
GET    /api/tasks/:taskId
PATCH  /api/tasks/:taskId
DELETE /api/tasks/:taskId
POST   /api/tasks/:taskId/move       # Body: { columnId, position }
POST   /api/tasks/:taskId/lock
POST   /api/tasks/:taskId/unlock
GET    /api/tasks/:taskId/comments
POST   /api/tasks/:taskId/comments
DELETE /api/tasks/:taskId/comments/:commentId
PATCH  /api/tasks/:taskId/checklist
```

### Automation
```
GET    /api/boards/:boardId/rules
POST   /api/boards/:boardId/rules
PATCH  /api/boards/:boardId/rules/:ruleId
DELETE /api/boards/:boardId/rules/:ruleId
POST   /api/boards/:boardId/rules/:ruleId/toggle
POST   /api/boards/:boardId/rules/:ruleId/test
```

### AI
```
POST   /api/ai/decompose
       Body:     { taskId: string }
       Response: { checklistItems: string[] }

POST   /api/ai/daily-summary
       Body:     { boardId: string }
       Response: { summary: string }

POST   /api/ai/analyze-bug
       Body:     { description?: string, imageBase64?: string }
       Response: { title, description, priority, tags }
```

### Queue (Incoming Tasks)
```
POST   /api/queue/tasks              # Внешний API — принять задачу в очередь
GET    /api/queue/tasks              # Просмотр очереди (только admin)
POST   /api/queue/tasks/:id/approve
POST   /api/queue/tasks/:id/reject
```

### Notifications
```
GET    /api/notifications
POST   /api/notifications/read-all
PATCH  /api/notifications/:id/read
```

---

## 5. WebSocket события (Socket.IO)

### Подключение и комнаты
```typescript
// Клиент подключается с токеном
const socket = io('/boards', { auth: { token: accessToken } })

// Присоединиться к доске
socket.emit('board:join', { boardId })
socket.emit('board:leave', { boardId })
socket.emit('presence:viewing', { boardId, taskId: string | null })
```

### Серверные события (server → clients в room)
```typescript
// Задачи
'task:created'    { task: Task }
'task:updated'    { taskId: string, changes: Partial<Task> }
'task:moved'      { taskId: string, fromColumnId: string, toColumnId: string, position: number }
'task:deleted'    { taskId: string }
'task:locked'     { taskId: string, lockedBy: { id: string, name: string } }
'task:unlocked'   { taskId: string }

// Колонки
'column:created'  { column: Column }
'column:updated'  { columnId: string, changes: Partial<Column> }
'column:deleted'  { columnId: string }
'column:reordered' { columns: { id: string, position: number }[] }

// Уведомления и присутствие
'notification:new' { notification: Notification }
'presence:joined'  { userId: string, name: string, avatarUrl: string }
'presence:left'    { userId: string }
'presence:viewing' { userId: string, taskId: string | null }
```

### Важно: оптимистичные обновления
Клиент должен игнорировать серверные события, которые сам же и вызвал. Для идентификации использовать `socket.id` — бэкенд прокидывает его в поле `originSocketId` каждого события.

---

## 6. Automation Engine

### Структура правила
```typescript
interface AutomationRule {
  trigger: AutomationTrigger;
  conditions: Condition[];   // Все условия — AND
  actions: Action[];         // Выполняются последовательно
}

interface Condition {
  field: 'tag' | 'priority' | 'columnId' | 'assigneeId' | 'dueDate';
  operator: 'equals' | 'contains' | 'not_equals' | 'is_empty' | 'before' | 'after';
  value: string | string[];
}

interface Action {
  type:
    | 'move_to_column'
    | 'move_to_top'
    | 'set_priority'
    | 'add_tag'
    | 'assign_to'
    | 'notify_user'
    | 'send_telegram';
  params: Record<string, unknown>;
}
```

### Три предустановленных правила (создаются через seed)

**Правило 1: Тег "баг" → в топ колонки**
```json
{
  "name": "Баги — в приоритет",
  "trigger": "TAG_ADDED",
  "conditions": [{ "field": "tag", "operator": "contains", "value": "баг" }],
  "actions": [{ "type": "move_to_top", "params": {} }]
}
```

**Правило 2: Задача в Done → уведомить создателя**
```json
{
  "name": "Задача завершена",
  "trigger": "TASK_MOVED",
  "conditions": [{ "field": "columnId", "operator": "equals", "value": "{{done_column_id}}" }],
  "actions": [{ "type": "notify_user", "params": { "target": "creator", "message": "Ваша задача завершена!" }}]
}
```

**Правило 3: Дедлайн через 24ч → Telegram**
```json
{
  "name": "Напоминание о дедлайне",
  "trigger": "DUE_DATE_APPROACHING",
  "conditions": [{ "field": "dueDate", "operator": "before", "value": "24h" }],
  "actions": [{ "type": "send_telegram", "params": { "target": "assignee" }}]
}
```

### AutomationService — требования к реализации
```typescript
class AutomationService {
  async processEvent(event: TaskEvent): Promise<void> {
    // 1. Получить активные правила для boardId из кэша Redis (TTL 60s)
    // 2. Фильтровать по trigger
    // 3. Для каждого правила: проверить все conditions (AND логика)
    // 4. Выполнить actions последовательно
    // 5. Записать ActivityLog { action: RULE_TRIGGERED, diff: { ruleName, taskId } }
    // 6. НИКОГДА не кидать необработанную ошибку — поймать, залогировать, продолжить
  }
}
```

---

## 7. BullMQ Queue

### Названия очередей (константы)
```typescript
const QUEUES = {
  TASK_EVENTS:     'task-events',
  INCOMING_TASKS:  'incoming-tasks',
  NOTIFICATIONS:   'notifications',
  AI_JOBS:         'ai-jobs',
  DEADLINE_CHECKER: 'deadline-checker',
} as const
```

### IncomingTask Pipeline — строго в таком порядке
```
POST /api/queue/tasks
   │
   ▼
1. Zod валидация входных данных
   │
   ▼
2. Дедупликация: SHA256(title + source + floor(unixtime / 3600))
   Если hash уже есть в БД → статус DUPLICATE → вернуть 409 с existing taskId
   │
   ▼
3. Сохранить IncomingTask в БД (статус PENDING)
   │
   ▼
4. Добавить BullMQ job в очередь 'incoming-tasks'
   │
   ▼
5. Worker:
   ├── AI обогащение: определить priority и tags через Claude API
   ├── Бизнес-валидация (заголовок не пустой, source в whitelist)
   └── Создать Task в БД (или поставить AWAITING_APPROVAL для ручной проверки)
```

### BullMQ defaults
```typescript
const defaultJobOptions: DefaultJobOptions = {
  attempts: 3,
  backoff: { type: 'exponential', delay: 2000 },
  removeOnComplete: { count: 100 },
  removeOnFail:     { count: 50 },
}
```

### Cron jobs (BullMQ repeatable)
```typescript
// Каждые 15 минут — проверка дедлайнов
deadlineQueue.add('check', {}, { repeat: { pattern: '*/15 * * * *' } })

// Каждый день в 09:00 — утренние напоминания
// Каждый день в 18:00 — ежедневная AI-выжимка менеджерам
```

---

## 8. AI Интеграция (Anthropic API)

### 8.1 Декомпозиция задачи
```typescript
async decomposeTask(task: { title: string; description?: string }): Promise<string[]> {
  const response = await anthropic.messages.create({
    model: 'claude-sonnet-4-20250514',
    max_tokens: 1000,
    system: `Ты — опытный технический менеджер. Декомпозируй задачу разработки
на конкретные шаги. Отвечай ТОЛЬКО JSON-массивом строк, без markdown.
Максимум 8 шагов. Каждый шаг — атомарная подзадача.`,
    messages: [{
      role: 'user',
      content: `Задача: "${task.title}"\n${task.description ?? ''}`
    }]
  })
  // Парсинг + fallback если JSON невалидный
}
```

### 8.2 Ежедневная выжимка
```typescript
async generateDailySummary(boardId: string): Promise<string> {
  // Собрать за последние 24ч:
  // - ActivityLog (что изменялось)
  // - Tasks с dueDate < now+48h
  // - Tasks в одной колонке > 3 дней (застрявшие)
  
  // Промпт на русском языке, ответ структурированный:
  // "✅ Завершено: ...\n⚡ В процессе: ...\n🚨 Риски: ...\n📅 Скоро дедлайн: ..."
}
```

### 8.3 Анализ бага (текст + опционально изображение)
```typescript
async analyzeBug(params: {
  description?: string;
  imageBase64?: string;
}): Promise<{ title: string; description: string; priority: TaskPriority; tags: string[] }> {
  // Если есть imageBase64 — передать как image content block
  // Если нет — только текстовый анализ
  // Ответ: JSON без markdown обёрток
}
```

---

## 9. Telegram Bot

### Команды
```
/start    — Привязка аккаунта (magic link через email)
/tasks    — Мои задачи на сегодня (список с кнопками)
/summary  — Запросить AI-выжимку дня
/boards   — Список моих досок
/help     — Справка
```

### Обработчик фото (создание бага)
```typescript
bot.on('photo', async (ctx) => {
  // 1. Проверить что пользователь привязан (telegramId → userId)
  // 2. Скачать фото в base64
  // 3. AI анализ через /api/ai/analyze-bug
  // 4. Показать превью карточки с inline keyboard:
  //    [✅ Создать баг] [✏️ Изменить приоритет] [❌ Отмена]
  // 5. При подтверждении — POST /api/queue/tasks
  // 6. Ответить ссылкой на созданную карточку
})
```

### Уведомления
```typescript
// Отправлять через Telegram когда:
// - Задача назначена на пользователя
// - Дедлайн через 24ч и через 2ч
// - Ежедневная выжимка (в 18:00 менеджерам)
// - Автоматизация сработала и action = 'send_telegram'
```

---

## 10. Frontend

### 10.1 KanbanBoard
```typescript
// components/board/KanbanBoard.tsx
//
// - DnD через @dnd-kit/core с SortableContext для каждой колонки
// - Оптимистичные обновления: drag → немедленно обновить UI → отправить запрос → откатить при ошибке
// - WIP-лимит: если tasks.length > column.wipLimit → колонка подсвечивается красным
// - Skeleton loading при первой загрузке
// - Аватары онлайн-пользователей в шапке доски (presence)
// - Кнопка "Добавить колонку" в конце ряда
```

### 10.2 TaskCard
```typescript
// components/board/TaskCard.tsx
//
// Отображает:
// - Заголовок (обрезать после 2 строк)
// - Цветную метку приоритета (CRITICAL=красная, HIGH=оранжевая, MEDIUM=синяя, LOW=серая)
// - Теги (pill-стиль, максимум 3 видимых + "+N")
// - Прогресс чек-листа: "3/7 ✓"
// - Аватар исполнителя
// - Дедлайн: красный если просрочен, оранжевый если < 24ч, серый в остальных случаях
// - Иконка замка 🔒 если lockedBy !== null
// - При hover: появляются кнопки [Редактировать] [Удалить] [Быстрое перемещение]
```

### 10.3 TaskModal
```typescript
// components/task/TaskModal.tsx
//
// При открытии:
// - POST /api/tasks/:id/lock (показать spinner пока блокируется)
// При закрытии (onClose, Escape, click outside):
// - POST /api/tasks/:id/unlock
//
// Функциональность:
// - Inline-редактирование заголовка (contenteditable, сохранение по blur/Enter)
// - Markdown-редактор описания (простой, без излишеств)
// - Checklist с drag-and-drop пунктов, добавление нового пункта
// - Выбор assignee из членов доски (dropdown с аватарами)
// - DatePicker для дедлайна
// - Мультиселект тегов (с возможностью создать новый)
// - Комментарии (список + форма добавления)
// - Кнопка "🤖 Разбить через AI" → запрос → показать превью → [Принять все] [Редактировать]
// - История изменений (свернута по умолчанию, раскрыть по клику)
```

### 10.4 AutomationRuleBuilder
```typescript
// components/automation/AutomationRuleBuilder.tsx
//
// No-code визуальный конструктор в формате:
// "Когда [ TRIGGER ▾ ] и [ УСЛОВИЕ ▾ ] [ ЗНАЧЕНИЕ ] → [ ДЕЙСТВИЕ ▾ ] [ ПАРАМЕТРЫ ]"
//
// Каждый блок — стилизованный <select> или combobox
// Кнопка "+ Добавить условие" (несколько conditions, AND)
// Кнопка "+ Добавить действие"
// Preview-строка снизу: человекочитаемое описание правила
// [Сохранить] [Тестировать] [Отмена]
```

### 10.5 Zustand Store
```typescript
// stores/boardStore.ts

interface BoardStore {
  board:          Board | null
  columns:        Column[]
  tasks:          Record<string, Task>           // taskId → Task
  tasksByColumn:  Record<string, string[]>       // columnId → taskId[]
  onlineUsers:    Record<string, OnlineUser>     // userId → { name, avatarUrl, viewingTaskId }

  // Sync actions (вызываются из Socket.IO хендлеров)
  applyTaskCreated:   (task: Task) => void
  applyTaskUpdated:   (taskId: string, changes: Partial<Task>) => void
  applyTaskMoved:     (taskId: string, toColumnId: string, position: number) => void
  applyTaskDeleted:   (taskId: string) => void
  applyTaskLocked:    (taskId: string, lockedBy: UserMeta) => void
  applyTaskUnlocked:  (taskId: string) => void
  applyColumnUpdated: (columnId: string, changes: Partial<Column>) => void

  // Optimistic actions (вызываются до HTTP запроса)
  optimisticMoveTask: (taskId: string, toColumnId: string, position: number) => void
  revertTaskMove:     (taskId: string, fromColumnId: string, position: number) => void
}
```

---

## 11. Middleware и безопасность

### Auth Middleware
```typescript
// 1. Извлечь Bearer токен из Authorization header
// 2. Верифицировать JWT (jose)
// 3. Проверить userId в кэше Redis (TTL 60s), при miss — запрос в БД
// 4. Прикрепить user к request.user
// 5. 401 если токен невалидный или истёк
```

### Rate Limiting (@fastify/rate-limit)
```
/api/auth/*         → 10 req/min per IP
/api/ai/*           → 20 req/min per userId
/api/queue/tasks    → 100 req/min per API key
Остальные           → 300 req/min per userId
```

### Безопасность
```typescript
// - @fastify/helmet — HTTP security headers
// - @fastify/cors — разрешить только CORS_ORIGINS из .env
// - Никогда не логировать: password, token, api_key, secret
// - Все SQL через Prisma (zero raw SQL с конкатенацией строк)
// - Входные данные валидировать через Zod на КАЖДОМ эндпоинте
```

---

## 12. Обработка ошибок

### Иерархия ошибок
```typescript
class AppError extends Error {
  constructor(
    public readonly statusCode: number,
    public readonly code: string,
    message: string,
    public readonly details?: unknown
  ) { super(message) }
}

class NotFoundError    extends AppError { constructor(r: string) { super(404, 'NOT_FOUND', `${r} not found`) }}
class ConflictError    extends AppError { constructor(m: string)  { super(409, 'CONFLICT', m) }}
class ForbiddenError   extends AppError { constructor(m = 'Access denied') { super(403, 'FORBIDDEN', m) }}
class ValidationError  extends AppError { constructor(d: unknown) { super(400, 'VALIDATION_ERROR', 'Validation failed', d) }}
class UnauthorizedError extends AppError { constructor() { super(401, 'UNAUTHORIZED', 'Authentication required') }}
```

### Формат всех ошибок (JSON)
```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Task not found",
    "details": null,
    "requestId": "clxyz123"
  }
}
```

---

## 13. Логирование

```typescript
// Использовать Pino с уровнями:
// info  — бизнес-события (задача создана, правило сработало)
// warn  — ожидаемые ошибки (дубликат в очереди, невалидный запрос)
// error — критические ошибки (БД недоступна, AI вернул невалидный JSON)

// Каждый запрос логировать:
{ method, path, statusCode, duration_ms, requestId, userId? }

// RequestId генерировать в middleware, прокидывать через AsyncLocalStorage
// Pino transport в production: JSON в stdout (для сбора через docker logs)
// Pino transport в development: pino-pretty для читаемого вывода
```

---

## 14. Тесты — обязательный минимум

### Unit тесты (Vitest)
```
AutomationService
  ✓ срабатывает при TAG_ADDED + conditions совпадают
  ✓ не срабатывает если conditions не совпадают
  ✓ выполняет все actions последовательно
  ✓ не падает если один action кинул ошибку (логирует, продолжает)

QueueService
  ✓ принимает задачу и создаёт IncomingTask
  ✓ отклоняет дубликат (одинаковый dedupHash) с ConflictError
  ✓ worker обогащает задачу через AI перед созданием

AI Service parsers
  ✓ корректно парсит валидный JSON массив от Claude
  ✓ возвращает fallback если Claude вернул невалидный JSON
  ✓ возвращает fallback если Claude вернул markdown-обёртку

TaskService
  ✓ move() корректно пересчитывает position при перемещении внутри колонки
  ✓ move() корректно пересчитывает position при перемещении между колонками
```

### Интеграционные тесты (Supertest)
```
POST /api/auth/login         → 200 + accessToken + refreshToken
POST /api/auth/login (wrong) → 401

POST /api/boards/:id/tasks   → 201 + task создан в БД
POST /api/tasks/:id/move     → 200 + positions обновлены + Socket.IO emit вызван

POST /api/queue/tasks        → 201 + IncomingTask создан
POST /api/queue/tasks (dup)  → 409 + { error: { code: 'CONFLICT' } }

POST /api/tasks/:id/lock     → 200 + task.lockedBy установлен
POST /api/tasks/:id/lock     → 409 если уже заблокирована другим
```

---

## 15. Docker Compose

```yaml
version: '3.9'

services:
  postgres:
    image: postgres:16-alpine
    environment:
      POSTGRES_DB: smart_kanban
      POSTGRES_USER: ${POSTGRES_USER}
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${POSTGRES_USER}"]
      interval: 5s
      timeout: 5s
      retries: 5
    restart: unless-stopped

  redis:
    image: redis:7-alpine
    command: redis-server --appendonly yes --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data
    healthcheck:
      test: ["CMD", "redis-cli", "-a", "${REDIS_PASSWORD}", "ping"]
      interval: 5s
      timeout: 5s
      retries: 5
    restart: unless-stopped

  api:
    build:
      context: ./apps/api
      dockerfile: Dockerfile
      target: production
    environment:
      DATABASE_URL: postgresql://${POSTGRES_USER}:${POSTGRES_PASSWORD}@postgres:5432/smart_kanban
      REDIS_URL: redis://:${REDIS_PASSWORD}@redis:6379
      JWT_SECRET: ${JWT_SECRET}
      JWT_REFRESH_SECRET: ${JWT_REFRESH_SECRET}
      ANTHROPIC_API_KEY: ${ANTHROPIC_API_KEY}
      TELEGRAM_BOT_TOKEN: ${TELEGRAM_BOT_TOKEN}
      BOT_SECRET: ${BOT_SECRET}
      PORT: 3001
      NODE_ENV: production
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    restart: unless-stopped

  web:
    build:
      context: ./apps/web
      dockerfile: Dockerfile
      args:
        VITE_API_URL: ${API_URL}
        VITE_WS_URL: ${WS_URL}
    restart: unless-stopped

  bot:
    build:
      context: ./apps/bot
      dockerfile: Dockerfile
    environment:
      TELEGRAM_BOT_TOKEN: ${TELEGRAM_BOT_TOKEN}
      API_URL: http://api:3001
      BOT_SECRET: ${BOT_SECRET}
    depends_on:
      - api
    restart: unless-stopped

  nginx:
    image: nginx:alpine
    volumes:
      - ./docker/nginx/nginx.conf:/etc/nginx/nginx.conf:ro
    ports:
      - "80:80"
    depends_on:
      - api
      - web
    restart: unless-stopped

volumes:
  postgres_data:
  redis_data:
```

---

## 16. .env.example (полный список)

```bash
# ── Database ──────────────────────────────────────────
DATABASE_URL=postgresql://kanban_user:password@localhost:5432/smart_kanban
POSTGRES_USER=kanban_user
POSTGRES_PASSWORD=strong_password_here
POSTGRES_DB=smart_kanban

# ── Redis ─────────────────────────────────────────────
REDIS_URL=redis://:redis_password@localhost:6379
REDIS_PASSWORD=redis_password_here

# ── JWT ───────────────────────────────────────────────
JWT_SECRET=minimum_32_chars_random_string_here_replace_me
JWT_REFRESH_SECRET=another_32_chars_random_string_here_replace
JWT_ACCESS_EXPIRES=15m
JWT_REFRESH_EXPIRES=7d

# ── API ───────────────────────────────────────────────
PORT=3001
NODE_ENV=development
CORS_ORIGINS=http://localhost:3000

# ── AI ────────────────────────────────────────────────
ANTHROPIC_API_KEY=sk-ant-...

# ── Telegram ──────────────────────────────────────────
TELEGRAM_BOT_TOKEN=123456:ABC-DEF...
BOT_SECRET=internal_secret_for_bot_to_api_calls
WEBHOOK_URL=https://yourdomain.com/api/telegram-webhook

# ── Frontend (Vite) ───────────────────────────────────
VITE_API_URL=http://localhost:3001
VITE_WS_URL=http://localhost:3001

# ── URLs (для docker-compose) ─────────────────────────
API_URL=http://localhost:3001
WS_URL=http://localhost:3001
```

---

## 17. Seed данные

```typescript
// apps/api/prisma/seed.ts
// Всё создавать через upsert (идемпотентно)

// Users:
// admin@demo.com  / Demo1234!  (role: ADMIN)
// tanya@demo.com  / Demo1234!  (role: MEMBER) — PM персона
// dmitry@demo.com / Demo1234!  (role: MEMBER) — Dev персона

// Board: "Smart Kanban Demo"
// Columns (в порядке position):
//   0: Backlog
//   1: To Do          (isDefault: true)
//   2: In Progress    (wipLimit: 3)
//   3: Review
//   4: Done

// Tasks: минимум 15 разнообразных задач:
//   - 3 задачи с тегом "баг" (для демо автоматизации)
//   - 2 задачи с dueDate = now+1day (для демо дедлайн-трекинга)
//   - 3 задачи с чек-листом (для демо прогресса)
//   - Задачи распределены по всем колонкам
//   - Разные приоритеты: CRITICAL, HIGH, MEDIUM, LOW

// AutomationRules: 3 правила из раздела 6 (все isActive: true)
```

---

## 18. Архитектурные принципы — СТРОГО ОБЯЗАТЕЛЬНО

### Разделение слоёв
```
Route Handler → валидация Zod → Service → Repository (Prisma) → PostgreSQL
                                   ↓
                              EventBus → AutomationService
                                       → NotificationService
                                   ↓
                              Socket.IO → broadcast to room
```
Бизнес-логика ТОЛЬКО в Service. Route handler только валидирует и вызывает сервис.

### TypeScript
- `strict: true` в tsconfig.json
- Никаких `any`. Неизвестный тип → `unknown` + type guard
- Zod schema = single source of truth для всех DTO
- Discriminated unions для состояний вместо boolean флагов

### Асинхронность
- Везде `async/await`
- Все промисы обёрнуты в try/catch или обрабатываются через error middleware
- Несколько связанных изменений БД = одна Prisma транзакция

### Производительность
- Pagination на всех списочных эндпоинтах (cursor-based для activity log)
- Никаких N+1 запросов — использовать Prisma `include` и `select`
- Кешировать в Redis: состояние доски (TTL 30s), инвалидировать при изменениях
- Правила автоматизации кешировать в Redis (TTL 60s)

### Надёжность
- BullMQ jobs с retry (3 попытки, exponential backoff)
- Dead-letter queue для failed jobs
- Graceful shutdown: дождаться завершения текущих jobs перед остановкой
- Health check эндпоинт GET /health: { status, db, redis, uptime }

---

## 19. Порядок реализации (строго соблюдать)

1. Монорепо структура, docker-compose, .env.example
2. Prisma schema + миграция + seed
3. Fastify app bootstrap (plugins: cors, helmet, swagger, pino)
4. Auth модуль (register, login, refresh, me, middleware)
5. Boards + Columns CRUD
6. Tasks CRUD + move + lock/unlock
7. EventBus (AsyncEventEmitter внутри процесса)
8. Socket.IO setup + rooms + real-time события
9. AutomationEngine (processEvent + все actions)
10. BullMQ setup + IncomingTask pipeline (с дедупликацией)
11. Cron jobs (deadline checker, daily summary scheduler)
12. AI интеграция (decompose, summary, analyze-bug)
13. Notification система (создание + real-time доставка)
14. Telegram Bot (команды + photo handler)
15. Frontend: BoardsPage → KanbanBoard → TaskCard → TaskModal
16. Frontend: AutomationRuleBuilder + NotificationsPanel
17. README.md
18. Тесты

---

## 20. Definition of Done — чеклист готовности

- [ ] `docker-compose up -d` поднимает все сервисы без ошибок
- [ ] `docker-compose exec api npx prisma migrate deploy` проходит успешно
- [ ] `docker-compose exec api npm run seed` создаёт demo данные
- [ ] Можно открыть http://localhost:3000, зарегистрироваться, войти
- [ ] Drag-and-drop карточки работает, изменение видно во втором браузере <1 секунды
- [ ] При открытии карточки в двух вкладках — вторая видит иконку блокировки
- [ ] Добавление тега "баг" к карточке → автоматически поднимает её в топ колонки
- [ ] POST /api/queue/tasks дважды с одним контентом → второй возвращает 409
- [ ] Кнопка "🤖 Разбить через AI" → создаёт чек-лист за <10 секунд
- [ ] GET /health возвращает { status: "ok", db: "ok", redis: "ok" }
- [ ] Telegram /start команда отвечает приветствием
- [ ] `npm test` в apps/api проходит без ошибок
- [ ] README содержит Quick Start, схему архитектуры, описание всех .env переменных
- [ ] Swagger UI доступен на /documentation

---

## 21. Дизайн-система и UI-стиль — СТРОГО ОБЯЗАТЕЛЬНО

> Весь frontend приложения должен быть выполнен в едином визуальном стиле, определённом в эталонном файле `promptboard-landing.html` (Auth/Landing страница). Claude Code обязан перенести все дизайн-токены, компоненты и эстетику из этого файла на ВСЕ остальные экраны приложения: канбан-доску, модалки задач, настройки, уведомления, профиль и т.д.

---

### 21.1 Дизайн-токены (единый источник правды)

Вынести в `apps/web/src/styles/tokens.css` и импортировать глобально:

```css
:root {
  /* ── Цвета фона ── */
  --bg-base:        #0d0e11;   /* Основной фон страницы */
  --bg-surface:     rgba(255, 255, 255, 0.03);  /* Карточки, панели */
  --bg-surface-hover: rgba(255, 255, 255, 0.05);
  --bg-input:       rgba(255, 255, 255, 0.04);
  --bg-tab-active:  rgba(255, 255, 255, 0.08);
  --bg-badge:       rgba(255, 255, 255, 0.04);

  /* ── Бордеры ── */
  --border-subtle:  rgba(255, 255, 255, 0.08);  /* Карточки */
  --border-input:   rgba(255, 255, 255, 0.10);
  --border-input-focus: rgba(139, 92, 246, 0.60);
  --border-badge:   rgba(255, 255, 255, 0.07);
  --border-tab:     rgba(255, 255, 255, 0.07);

  /* ── Текст ── */
  --text-primary:   #ffffff;
  --text-muted:     rgba(255, 255, 255, 0.45);
  --text-subtle:    rgba(255, 255, 255, 0.25);
  --text-label:     rgba(255, 255, 255, 0.55);
  --text-tab:       rgba(255, 255, 255, 0.40);

  /* ── Акцентный градиент (фиолетовый → циан) ── */
  --accent-from:    #8b5cf6;
  --accent-to:      #06b6d4;
  --accent-mid:     #0ea5e9;
  --gradient-accent: linear-gradient(135deg, #8b5cf6 0%, #0ea5e9 100%);
  --gradient-text:   linear-gradient(90deg, #8b5cf6, #06b6d4);

  /* ── Свечение ambient ── */
  --glow-violet:    rgba(139, 92, 246, 0.12);
  --glow-cyan:      rgba(6, 182, 212, 0.08);
  --glow-violet-sm: rgba(139, 92, 246, 0.06);

  /* ── Скругления ── */
  --radius-sm:   8px;
  --radius-md:   10px;
  --radius-lg:   12px;
  --radius-xl:   16px;
  --radius-2xl:  20px;
  --radius-full: 100px;

  /* ── Тени кнопок ── */
  --shadow-btn-primary:       0 0 0 1px rgba(139,92,246,0.30), 0 4px 24px rgba(139,92,246,0.25);
  --shadow-btn-primary-hover: 0 0 0 1px rgba(139,92,246,0.50), 0 8px 32px rgba(139,92,246,0.35);

  /* ── Типографика ── */
  --font-base: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', sans-serif;
  --font-mono: 'JetBrains Mono', 'Fira Code', monospace;
}
```

---

### 21.2 Глобальные базовые стили

```css
/* apps/web/src/styles/global.css */

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body, #root {
  min-height: 100vh;
  background: var(--bg-base);
  color: var(--text-primary);
  font-family: var(--font-base);
  -webkit-font-smoothing: antialiased;
  overflow-x: hidden;
}

/* Скроллбар в стиле темы */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.1); border-radius: 3px; }
::-webkit-scrollbar-thumb:hover { background: rgba(255,255,255,0.18); }

/* Выделение текста */
::selection { background: rgba(139,92,246,0.3); color: #fff; }
```

---

### 21.3 Ambient Glow — фоновые свечения

На КАЖДОЙ странице приложения должны присутствовать три `position: fixed` декоративных пятна. Вынести в переиспользуемый компонент `<AmbientGlow />`:

```tsx
// apps/web/src/components/ui/AmbientGlow.tsx
export function AmbientGlow() {
  return (
    <>
      <div style={{
        position: 'fixed', borderRadius: '50%', filter: 'blur(120px)',
        pointerEvents: 'none', zIndex: 0,
        width: 600, height: 600,
        background: 'radial-gradient(circle, rgba(139,92,246,0.12) 0%, transparent 70%)',
        top: -150, left: -100,
      }} />
      <div style={{
        position: 'fixed', borderRadius: '50%', filter: 'blur(120px)',
        pointerEvents: 'none', zIndex: 0,
        width: 500, height: 500,
        background: 'radial-gradient(circle, rgba(6,182,212,0.08) 0%, transparent 70%)',
        bottom: -100, right: -80,
      }} />
      <div style={{
        position: 'fixed', borderRadius: '50%', filter: 'blur(120px)',
        pointerEvents: 'none', zIndex: 0,
        width: 300, height: 300,
        background: 'radial-gradient(circle, rgba(139,92,246,0.06) 0%, transparent 70%)',
        top: '50%', right: '30%',
        transform: 'translateY(-50%)',
      }} />
    </>
  )
}
```

Все рабочие страницы (доска, профиль, настройки) рендерят `<AmbientGlow />` с `position: relative; z-index: 1` для контента поверх.

---

### 21.4 Компонент: Карточка (GlassCard)

Переиспользуемый glassmorphism-контейнер. Использовать для: панелей, модалок, боковых панелей, виджетов.

```tsx
// apps/web/src/components/ui/GlassCard.tsx
interface GlassCardProps {
  children: React.ReactNode
  className?: string
  glow?: boolean   // внутреннее фиолетовое свечение (как на auth)
  padding?: string
}

// Стиль:
// background: var(--bg-surface)
// backdrop-filter: blur(12px)
// border: 1px solid var(--border-subtle)
// border-radius: var(--radius-2xl)
// position: relative; overflow: hidden
//
// Если glow=true — добавить ::before с
// radial-gradient(ellipse at top left, rgba(139,92,246,0.06) 0%, transparent 60%)
```

---

### 21.5 Компонент: Кнопки

```tsx
// Три варианта, все наследуют base-стили:
// border-radius: var(--radius-lg)
// font-weight: 600
// transition: transform 0.15s, opacity 0.15s, box-shadow 0.15s
// :active → transform: scale(0.98)

// PRIMARY — градиентная
// background: var(--gradient-accent)
// color: #fff
// box-shadow: var(--shadow-btn-primary)
// :hover → box-shadow: var(--shadow-btn-primary-hover), opacity: 0.95
// Shimmer-эффект при hover через ::after с translateX(-100% → 100%)

// SECONDARY — приглушённая
// background: rgba(255,255,255,0.05)
// border: 1px solid rgba(255,255,255,0.12)
// color: rgba(255,255,255,0.75)
// :hover → background: rgba(255,255,255,0.08), color: rgba(255,255,255,0.9)

// GHOST — минималистичная (для иконок, вторичных действий)
// background: transparent
// color: var(--text-muted)
// :hover → background: rgba(255,255,255,0.04), color: var(--text-primary)
```

---

### 21.6 Компонент: Поля ввода (Input)

```tsx
// Стиль полей:
// background: var(--bg-input)
// border: 1px solid var(--border-input)
// border-radius: var(--radius-md)
// padding: 12px 14px 12px 38px  (38px если есть иконка слева)
// color: var(--text-primary)
// font-size: 14px
// ::placeholder → color: rgba(255,255,255,0.2)
// :focus → border-color: var(--border-input-focus), background: rgba(139,92,246,0.05)
//
// Иконка слева: position absolute, left 13px, top 50%, color rgba(255,255,255,0.25)
//   при :focus → color rgba(139,92,246,0.7)
//
// Применять к: поиску, фильтрам на доске, полям в модалках задач,
//   инпутам настроек, полям создания колонок
```

---

### 21.7 Компонент: Таб-переключатель

```tsx
// Контейнер:
// background: rgba(255,255,255,0.04)
// border: 1px solid var(--border-tab)
// border-radius: var(--radius-md) + 2px
// padding: 4px
// display: flex; gap: 4px

// Каждый таб:
// border-radius: var(--radius-md) - 1px
// color: var(--text-tab)
// :hover (не active) → color: rgba(255,255,255,0.65)

// Активный таб:
// background: var(--bg-tab-active)
// color: var(--text-primary)

// Применять к: переключению Login/Register, фильтрам на доске
//   (Все / Мои / Просроченные), вкладкам в настройках
```

---

### 21.8 Компонент: Бейдж / Тег

```tsx
// Пилюля с точкой:
// background: var(--bg-badge)
// border: 1px solid var(--border-badge)
// border-radius: var(--radius-full)
// padding: 5px 12px
// font-size: 12px
// color: rgba(255,255,255,0.5)
// Dot: width/height 5px, border-radius 50%, background: #06b6d4

// Теги приоритета — переопределить цвет dot:
// CRITICAL → dot background: #ef4444
// HIGH     → dot background: #f97316
// MEDIUM   → dot background: #06b6d4
// LOW      → dot background: rgba(255,255,255,0.3)

// Применять к: тегам на карточках задач, лейблам приоритета,
//   статус-индикаторам, счётчикам уведомлений
```

---

### 21.9 Акцентный градиент на тексте

Использовать на: заголовках секций, названии продукта, счётчиках, подсветке активных элементов.

```css
.gradient-text {
  background: var(--gradient-text);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}
```

Применять к: логотипу в сайдбаре, заголовку активной доски, числам метрик в дашборде.

---

### 21.10 Логотип (компонент)

```tsx
// apps/web/src/components/ui/Logo.tsx
// Марка: 32×32px div с gradient-accent background, border-radius: var(--radius-sm)
// SVG внутри — три горизонтальные полоски убывающей непрозрачности (0.9 / 0.6 / 0.35)
// Название "PromptBoard" рядом: font-size 16px, font-weight 600, letter-spacing -0.3px
// Варианты: size="sm" (24px марка) | size="md" (32px) | size="lg" (40px)
// В сайдбаре использовать size="md", в мобильной шапке — size="sm"
```

---

### 21.11 Применение стиля по экранам

#### Канбан-доска (`/board/:id`)
- Фон страницы: `var(--bg-base)` + `<AmbientGlow />`
- Шапка доски (название, кнопки): `<GlassCard>` без внутреннего glow, padding 12px 20px
- Колонки: `<GlassCard>` с border-radius `var(--radius-xl)`, min-width 280px
- Заголовок колонки: font-size 13px, font-weight 600, color `var(--text-muted)`, UPPERCASE, letter-spacing 0.5px
- WIP-лимит превышен → border колонки: `rgba(239,68,68,0.4)`, внутренний glow красный
- Карточка задачи: `<GlassCard>` с padding 14px 16px, hover → border-color `rgba(255,255,255,0.14)`, cursor grab
- Кнопка "Добавить задачу" в колонке: `btn-ghost` с иконкой `+`, цвет `var(--text-subtle)`, :hover → `var(--text-muted)`
- Кнопка "Добавить колонку": `btn-secondary` в конце ряда

#### Модалка задачи
- Overlay: `rgba(0,0,0,0.7)` с backdrop-filter blur(4px)
- Сама модалка: `<GlassCard glow>` с max-width 640px, border-radius `var(--radius-2xl)`
- Заголовок редактируется inline — при фокусе подсвечивается `var(--border-input-focus)`
- Кнопка "🤖 Разбить через AI": `btn-primary` с градиентом

#### Сайдбар
- Фон: `rgba(255,255,255,0.02)`, border-right: `1px solid var(--border-subtle)`
- Ширина: 240px (collapsed: 56px)
- Активный пункт: background `rgba(139,92,246,0.12)`, border-left `2px solid var(--accent-from)`, color `var(--text-primary)`
- Неактивный: color `var(--text-muted)`, :hover → background `rgba(255,255,255,0.04)`
- Логотип вверху: `<Logo size="md" />`

#### Шапка (Header)
- Фон: `rgba(13,14,17,0.8)` + backdrop-filter blur(12px) — "плавающая" шапка
- Border-bottom: `1px solid var(--border-subtle)`
- Поиск: `<Input>` со стилями из п.21.6
- Аватар пользователя: градиентный border (`var(--gradient-accent)`) 2px

#### Страница настроек
- Левая навигация: как сайдбар (вертикальные табы)
- Секции: `<GlassCard>` с заголовком секции и описанием
- Все поля: `<Input>` из п.21.6
- Конструктор правил автоматизации: тёмные `<select>`-дропдауны в том же стиле что и Input

#### Уведомления (панель)
- Появляется как drawer справа с `backdrop-filter: blur(12px)`
- Фон: `var(--bg-surface)` + border-left `1px solid var(--border-subtle)`
- Каждое уведомление: горизонтальная карточка с цветным dot по типу
- Непрочитанное: background `rgba(139,92,246,0.06)`, border-left `2px solid var(--accent-from)`

#### Пустые состояния (Empty states)
- Иконка: 48px, color `var(--text-subtle)`
- Заголовок: `var(--text-muted)`, font-size 15px
- Текст: `var(--text-subtle)`, font-size 13px
- Кнопка действия: `btn-primary`

---

### 21.12 Анимации и переходы

```css
/* Стандартные значения — не отклоняться */
--transition-fast:   0.15s ease;
--transition-normal: 0.20s ease;
--transition-slow:   0.30s ease;

/* Применять:
   - Hover на карточках, кнопках, инпутах → var(--transition-fast)
   - Открытие модалки, drawer → var(--transition-normal) + opacity + translateY(8px → 0)
   - Shimmer на primary кнопке → 0.6s ease (translateX)
   - Drag карточки → Framer Motion layoutId для плавного перемещения
   - Появление уведомления → slide-in справа 0.25s ease
*/
```

---

### 21.13 Tailwind — переопределить конфиг

Если используется Tailwind, добавить кастомные токены в `tailwind.config.ts`:

```ts
export default {
  theme: {
    extend: {
      colors: {
        'accent-violet': '#8b5cf6',
        'accent-cyan':   '#06b6d4',
        'accent-blue':   '#0ea5e9',
        'surface':       'rgba(255,255,255,0.03)',
        'surface-hover': 'rgba(255,255,255,0.05)',
      },
      borderColor: {
        'subtle': 'rgba(255,255,255,0.08)',
        'input':  'rgba(255,255,255,0.10)',
      },
      backdropBlur: {
        'glass': '12px',
      },
      backgroundImage: {
        'gradient-accent': 'linear-gradient(135deg, #8b5cf6 0%, #0ea5e9 100%)',
        'gradient-text':   'linear-gradient(90deg, #8b5cf6, #06b6d4)',
      },
    },
  },
}
```

---

### 21.14 Запрещено (антипаттерны)

Claude Code НИКОГДА не должен использовать в этом проекте:

- `background: white` или `background: #fff` — только тёмные поверхности
- `color: #333` / `color: black` — только CSS-переменные или rgba(255,255,255,...)
- Светлые темы, `prefers-color-scheme: light` переопределения
- Solid цветные фоны для карточек (синие, зелёные блоки) — только glassmorphism
- Box-shadow с тёмными цветами (например `box-shadow: 0 2px 8px rgba(0,0,0,0.3)`) — только фиолетовые glow тени
- Bootstrap, Material UI, Ant Design компоненты — только shadcn/ui кастомизированные под тему
- Rounded corners > `var(--radius-2xl)` (20px) кроме `--radius-full` для пилюль
- Жирные бордеры > 1px (исключение: 2px для активных элементов сайдбара и featured карточек)

---

### 21.15 Эталонный файл

Файл `promptboard-landing.html` является **единственным эталоном дизайна**. При любом сомнении в стилях — ориентироваться на этот файл. Все компоненты должны визуально ощущаться как часть одного продукта с этой страницей.
