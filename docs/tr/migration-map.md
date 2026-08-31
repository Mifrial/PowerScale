# Карта миграции старого ТР

**Статус:** рабочий реестр миграции, 2026-08-30.

Источник строк — `HEAD:docs/tr/TR.md` (3748 строк). Рабочий `docs/tr/TR.md` является только индексом и не используется как источник утраченного содержания.

Детальный перечень claims находится в [`migration-claims.md`](migration-claims.md).

Статусы:

- `MIGRATE` — переносить подтверждённые детали в текущий документ;
- `HISTORICAL` — переносить с исторической пометкой;
- `OPEN` — остановить спорный фрагмент и запросить решение;
- `DEFERRED` — сохранить как отложенную работу;
- `BACKLOG` — текущая незавершённая функциональность.

Покрытие = classified fragments journal [`../review/tr-reconciliation-2026-08.md`](../review/tr-reconciliation-2026-08.md), не счётчик claims.

## Карта разделов

- **1. Обзор, строки 33–75** → `TR.md`, `architecture.md`, `rule-system.md`, `character-system.md`, `game-system.md`; `MIGRATE` подтверждённые сущности и общую версионность, старые формулировки ревизий — `HISTORICAL`.
- **2. Архитектура, строки 76–311** → `architecture.md`; `MIGRATE` модульную систему, DI, ServiceLocator, SmartTable, Shell, слои, DAG и plugin-контракты.
- **3. Схема БД, строки 312–804** → `architecture.md` и соответствующие доменные документы; переносить только подтверждённые схемы, неподтверждённые backend-части — `OPEN`.
  - файлы, 322–336 → `architecture.md`, `OPEN` storage/access details;
  - auth, users, groups, permissions, 337–399 → отдельный системный раздел, `OPEN` effective-permissions backend;
  - spaces, rules, rule sets, 400–506 → `rule-system.md`, `OPEN` Rule Sets contract;
  - nations, languages, writing systems, 507–548 → `rule-system.md`;
  - games, 549–622 → `game-system.md`;
  - characters, 623–696 → `character-system.md`, старые membership fields — `HISTORICAL`;
  - notifications, 697–722 → отдельный документ или `architecture.md`, `OPEN` event generators;
  - chats and chronicle, 723–804 → `chat-system.md` и `game-system.md`, `OPEN` backend storage details.
- **4. Авторизация и пользователи, строки 805–1036** → `auth-system.md`; `MIGRATE` confirmed user/permission behavior and dialog-based deactivation (`DEC-057`), while other backend permission details remain `OPEN`.
- **5. Пространства, строки 1037–1227** → `rule-system.md`; `MIGRATE` revision, inheritance, SpaceRevision, draft, URL context, publish, stores; selective publish remains required by `DEC-015`.
- **6. Правила, строки 1228–1680** → `rule-system.md`; `MIGRATE` mechanics, all confirmed rule DTOs, dimensional values, keywords, modifiers and specifications; unknown backend semantics — `OPEN`.
- **7. Персонажи, строки 1681–1851** → `character-system.md`; `MIGRATE` creation, editing, calculations, modifiers, inventory, states and routes; old status/storage variants — `HISTORICAL`.
- **8. Игры, строки 1852–1980** → `game-system.md`; `MIGRATE` lifecycle, roles, NPC, tabs, GameTime, combat and known status. Живой контракт сцены — `battleground-system.md` (`REQUIREMENT`, не legacy-диапазон); в legacy battleground был незавершён.
- **9. Chat, строки 1981–2235** → `chat-system.md`; `MIGRATE` host, plugins, attachments, loading, virtualization and frontend behavior; SSE/backend details — `OPEN` or `DEFERRED` where unverified.
- **10. Уведомления, строки 2236–2250** → system document; `MIGRATE` confirmed UI/data contract, unfinished backend generation — `OPEN`.
- **11. Интерфейс, строки 2251–2388** → system document or `architecture.md`; `MIGRATE` current routes/layout/editor patterns; cancelled layout variants — `HISTORICAL`.
- **12. Волны реализации, строки 2389–3253** → `history.md` and release status sections; the historical plan is not a current roadmap.
- **13. Решения с фронта, строки 3254–3526** → `architecture.md` or relevant domain documents; `MIGRATE` confirmed frontend decisions and mark unresolved backend portions `OPEN`.
- **Отложенное, строки 3527–3545** → relevant domain `BACKLOG`/`DEFERRED` sections; do not present as implemented.
- **Сводка решений, строки 3546–3697** → `decisions.md` and `history.md`; reconcile with dated decision journal, without copying duplicate or superseded text.
- **14–15. Очереди релиза, строки 3698–3748** → domain backlog and `TR.md` status index; verify each item against code before assigning `IMPLEMENTED`.

## Обязательные проверки при переносе

- Every `MIGRATE` fragment must have a code, test, frontend-rule or explicit decision source.
- Every old model superseded by a decision must be moved to `history.md`, not repeated as a current contract.
- Unresolved backend schemas and any new contradiction stop the affected migration step and require an explicit question to Андрей. `TR-017` is resolved by `DEC-057`.
- Magic source/path/branch/skill classification remains `DEFERRED` until real export analysis.
- This map does not itself decide disputed contracts.
