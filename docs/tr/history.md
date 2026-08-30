# История и отменённые варианты

**Статус:** исторический слой, 2026-08-30.

Материалы этого файла объясняют происхождение решений и не являются текущим контрактом.

Актуальные решения хранятся в [`decisions.md`](decisions.md). В частности, `DEC-019` superseded by `DEC-058`; этот файл не дублирует полный список DEC.

## Перенесённые модели

- `active_json / pending_json / draft_json` — старая модель membership; заменена A/L/O/P.
- `dice_result` и `send(text, rolls)` — старая модель Chat; заменена `ChatAttachment[]`.
- `ResourceSpec.initial_value` — ранняя модель ресурса; заменена `auto_add` и `limit`.
- `character_moderation` как отдельная старая схема модерации — не текущий контракт.
- timestamp как внешний номер ревизии и `rules_version_at` — заменены scoped numeric `revision` и `publishedAt`.
- `characteristic_id` как основной способ ссылок — заменён семантическими `*_code`.
- постоянный `moneyLimit` — ранняя трактовка денег; после создания используется баланс, а стартовый бюджет имеет исторический смысл.
- `rules_version_at`/timestamp как внешний version selector — legacy storage; current selector is `(spaceId, revision)` with `publishedAt`.
- `character_moderation` — legacy separate moderation record; current moderation belongs to Game membership and its concurrency contract.
- `event_time`/`sort_order` — legacy chronicle storage; current frontend uses normalized `GameTime` offset.
- `has_ability_tag` / `has_tag` — superseded requirement names; current — `has_ability_keyword` / `has_keyword` (`DEC-017`).
- Dual SQL table `tags` рядом с `keywords` — historical rename; current справочник — `keywords`.
- Асинхронная очередь и progress UI для наследования пространства — отвергнутый вариант выполнения; snapshot copy выполняется атомарной транзакцией-коммитом.
- Версия пространства A.B.C и timestamp `created_at` как внешний selector — заменены `(spaceId, revision)` + `publishedAt`.
- Отменённый layout с отдельным footer / независимым верхним меню — не current UI (`DEC-020`).
- Волны реализации §12 — исторический порядок работ, не текущий roadmap.

## Архитектурные планы

- Старый план, в котором Chat зависел от Game, отменён. Chat — самостоятельный host с plugin-регистрацией.
- Прямые импорты Game во внутренние файлы Rule и Character признаны архитектурным нарушением и исправляются отдельной задачей.
- Battleground не считается завершённой частью абстрактного движения.

## Исторические волны реализации

Старый ТР описывал последовательность:

1. фундамент: Core/Kernel, SmartTable, Auth и Shell;
2. простые CRUD: User, Rule, Notifications;
3. контент: Space, Nation/Language/WritingSystem;
4. персонажи;
5. Game и Chat.

Эта последовательность полезна как объяснение порядка разработки, но не является текущим статусом реализации. В частности, старые утверждения о стаб-страницах Character/Game и «Chat ещё впереди» противоречат позднему frontend-коду.

### Legacy wave map

Старый roadmap распределял работы так:

1. **Foundation** — Core/Kernel, SmartTable, module registration, DI, Auth и Shell;
2. **CRUD** — Users, Groups, Rule catalog, Keywords и Notifications;
3. **Rule content** — Spaces, revisions, publication, Nation/Language/WritingSystem и mechanics;
4. **Character** — creation stages, budgets, versions, inventory, migration и moderation;
5. **Game** — lifecycle, members, NPC, sessions, combat, movement, chronicle, loot и economy;
6. **Chat** — host/plugin registry, commands, roll attachments, macros, inline references и synchronization.

Точная последовательность и даты этих волн находятся в legacy archive и claim registry (`CLM-052`—`CLM-056`). Сохраняются историческими только порядок и старые backlog-формулировки; технический контракт каждого реализованного пункта принадлежит current domain document.

Причина переноса в history: эти модели либо были заменены явными решениями аудита, либо отражали backend design, не подтверждённый текущим frontend. Исторические даты и диапазоны берутся из legacy archive и decision log; history не переопределяет current owner.

## Исторические frontend-решения

В старом ТР отдельно фиксировались решения, которые теперь считаются частью текущих документов:

- httpOnly cookies и CSRF;
- API password policy;
- batch endpoint и `markChatRead`;
- generic Row;
- error boundaries и состояние ошибки профиля;
- `AbortController` и debounce;
- устранение двойного `<v-app>`;
- восстановление чатов после login;
- виртуализация SmartGrid и сообщений;
- per-object permissions;
- draft persistence;
- Chat plugin model и inline-чипы.

Их текущая семантика описана в `architecture.md`, `auth-system.md`, `chat-system.md` и `ui-system.md`; в этом разделе они не дублируются как отдельный контракт.

## Незавершённые варианты

- `N → 1` для нескольких атак против одной защиты пока не реализован.
- Точные backend-таблицы и endpoint экономики не утверждены.
- Полные runtime-механики заклинаний и классификация магического контента ждут реальной выгрузки.
- Старые волны реализации, стаб-страницы и ранние решения монолитного `TR.md` сохраняются для справки в истории Git и не копируются в текущие доменные документы.

Полная карта исходных разделов и диапазонов: [`migration-map.md`](migration-map.md).

Источник датированных решений: [`../review/tr-decisions-2026-08.md`](../review/tr-decisions-2026-08.md).
