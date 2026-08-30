# Сверка ТР с реальным frontend-кодом — 2026-08

**Дата инвентаризации:** 2026-08-30  
**HEAD:** `d60ea1b`  
**Scope:** `draft-front_1.2ds/src` против current-документов `docs/tr/*.md`.  
**Дополнительные источники:** `draft-front_1.2ds/frontend-rules.md`, [`tr-audit-2026-08.md`](tr-audit-2026-08.md), [`tr-reconciliation-2026-08.md`](tr-reconciliation-2026-08.md).

## Назначение и правила

Этот файл фиксирует только расхождения между текущим frontend-кодом и каноническим ТР. Legacy-фрагменты сами по себе не являются доказательством frontend-реализации.

Типы пунктов:

- `TR_GAP` — код поддерживает контракт, но ТР его не описывает или описывает недостаточно;
- `TR_ERROR` — текущая формулировка ТР противоречит коду;
- `CODE_ERROR` — код нарушает уже принятый контракт/правило;
- `CODE_GAP` — в ТР зафиксирован целевой контракт, но код его ещё не выполняет;
- `OPEN_DECISION` — по evidence нельзя выбрать сторону без решения Андрея.

Статус `OPEN` означает, что пункт ещё не обсуждён. После обсуждения пункт получает `DECIDED` с датой и ссылкой на внесённую правку.

## Приоритетный реестр

| ID | Severity | Type | Owner | Status |
| --- | --- | --- | --- | --- |
| RTR-001 | HIGH | CODE_GAP | auth-system.md, ui-system.md | DECIDED |
| RTR-002 | HIGH | TR_GAP | game-system.md, cross-domain.md | DECIDED |
| RTR-003 | HIGH | CODE_GAP | rule-system.md, character-system.md | DECIDED |
| RTR-004 | HIGH | CODE_ERROR | character-system.md, game-system.md | DECIDED |
| RTR-005 | MEDIUM | TR_ERROR | data-model.md, game-system.md | DECIDED |
| RTR-006 | MEDIUM | TR_ERROR | rule-system.md, UI owner | DECIDED |
| RTR-007 | MEDIUM | CODE_GAP | chat-system.md | DECIDED |
| RTR-008 | HIGH | CODE_ERROR | architecture.md | DECIDED |
| RTR-009 | MEDIUM | CODE_ERROR | architecture.md | DECIDED |
| RTR-010 | MEDIUM | TR_GAP | ui-system.md | DECIDED |
| RTR-011 | MEDIUM | TR_GAP | game-system.md | DECIDED |

## Подробные пункты

### RTR-001 — logout ошибочно выглядит маршрутом

**Type:** `TR_ERROR`  
**Evidence:** `Core/Auth/routes.ts:3–26` содержит только `/login`, `/register`, `/forgot-password`, `/reset-password`. Logout реализован как `AuthApi.logout()` через action `auth.logout` в `Core/Auth/Service/AuthApi.ts:23–25`.  
**ТР:** `auth-system.md:55–75` и `ui-system.md:52–55` включают `/logout` в список routes.

**Расхождение:** `/logout` не является frontend route; это authenticated POST/action.

**Варианты:**

1. Убрать `/logout` из route catalog и описать его в списке actions.
2. Оставить `/logout` как логический URL, явно пометив его несуществующим frontend route.

**Решение Андрея (2026-08-30):** logout на frontend должен быть authenticated action `auth.logout`, запускаемый через confirmation dialog; отдельной страницы/route нет. Текущий dialog-flow реализован некорректно и фиксируется как `CODE_GAP`. ТР обновлён; код в этом пункте пока не менялся.

### RTR-002 — EconomyOperation описана, но frontend-контракт отсутствует

**Type:** `TR_GAP`  
**Evidence:** поиск `EconomyOperation` и economy API в `draft-front_1.2ds/src` не находит DTO, service, store или публичных методов `buy/sell/transfer/discard`. `IGameApi.ts:120–200` содержит game/combat/loot/check API, но не economy API.  
**ТР:** `game-system.md:50–88`, `cross-domain.md:44–54` описывают typed operations, buy/sell/transfer/discard и immutable journal.

**Расхождение:** ТР правильно фиксирует целевое backend requirement, но не отделяет его от отсутствующего frontend public contract.

**Варианты:**

1. Явно пометить economy как backend/domain requirement, frontend API — `NOT_IMPLEMENTED`.
2. Удалить economy из current ТР до появления frontend API.
3. Добавить frontend DTO/API/UI в коде.

**Решение Андрея (2026-08-30):** выбран вариант 1 с уточнением границ. `EconomyOperation` остаётся backend/domain requirement. Frontend Economy API/UI помечается `NOT IMPLEMENTED`; существующий `distributeLoot` остаётся отдельным реализованным loot flow и не объявляется полной EconomyOperation. Общие permission, optimistic version, idempotency и atomicity invariants сохраняются. Во время сессии player-мутация направляется в `gameOverlay`, вне сессии — в `actualCharacter`, NPC использует `npc.version`. Current-ТР уточняется, код новых economy operations не добавляется.

### RTR-003 — `ruleId` и `*_code` используются одновременно

**Type:** `CODE_GAP`  
**Evidence:** `RuleRevisionResolverService.ts:13–20,30–50` ищет правило по `rule.code === ruleId || rule.id === ruleId`. Character DTO используют `raceRuleId`, `ruleId`, `sourceRuleId` (`CharacterVersion.ts:10–38`).  
**ТР:** `rule-system.md:48–53` утверждает, что внешние ссылки используют semantic `*_code`, а numeric IDs — только storage keys.

**Расхождение:** фактический frontend допускает два ключа и называет поля `ruleId`; текущая документация требует только code-семантику.

**Варианты:**

1. Признать frontend `ruleId` исторически неточным именем, но зафиксировать, что внешнее значение — rule code.
2. Официально разрешить `id | code` на frontend boundary и описать fallback.
3. Перевести DTO и код только на semantic `*_code`.

**Решение Андрея (2026-08-30):** выбран вариант 3. Все публичные DTO и domain boundaries переходят на явные semantic-поля `*_code` (`ruleCode`, `raceRuleCode`, `sourceRuleCode` и аналогичные). Numeric `id` остаётся только внутренним storage key и не принимается как fallback на публичной границе. Переименованию подлежат DTO, резолверы, моки, fixtures, сервисы и тесты; реализация — `CODE_GAP / implementation OPEN`.

### RTR-004 — CharacterStatus содержит `moderation`

**Type:** `CODE_ERROR`  
**Evidence:** `Character/Enum/CharacterStatus.ts:1` содержит `draft | ready | moderation | needs_fix`; `CHARACTER_STATUS_OPTIONS.ts:9–12` показывает «На модерации». При этом `CreateCharacterData.ts:5–7` прямо говорит, что moderation относится к game membership, а не к Character.  
**ТР:** `character-system.md:58–68` отделяет status персонажа от membership moderation.

**Расхождение:** код хранит moderation в CharacterStatus, хотя документация и комментарии кода объявляют модели раздельными.

**Варианты:**

1. Удалить `moderation` из `CharacterStatus` и оставить moderation только в `GameCharacterMembership`.
2. Сохранить его как UI-совместимый legacy status, но запретить использование для standalone Character.
3. Вернуть moderation в current Character lifecycle и изменить ТР.

**Рекомендация:** вариант 1; перед удалением проверить все consumers и mock fixtures.

**Решение Андрея (2026-08-30):** `CharacterStatus` не является backend lifecycle. Истории `CharacterVersion` нет; есть один `actualCharacter`, browser draft и вычисляемый `needs_fix`. Игра хранит полную immutable-копию `approvedCharacterVersion`; сессия вычисляет `sessionCharacterVersion = resolve(approvedCharacterVersion, gameOverlay)`. После session commit overlay атомарно применяет результат к actual character, после чего active membership автоматически требует модерации. `moderation` в текущем коде будет устранён в рамках реализации этой модели.

### RTR-005 — loot status `acquired` отсутствует в коде

**Type:** `TR_ERROR`  
**Evidence:** `Game/Enum/GameLootStatus.ts:1–6` содержит только `prepared | available | distributed`.  
**ТР:** `data-model.md:337` перечисляет `prepared`, `available`, `acquired`, `distributed`.

**Расхождение:** current schema description содержит статус, которого нет в frontend enum.

**Варианты:**

1. Удалить `acquired` из current ТР.
2. Добавить `acquired` в код и определить его переходы.

**Решение Андрея (2026-08-30):** выбран вариант 1. `acquired` удаляется из current-ТР. Интерес игрока к loot моделируется отдельно через `game_loot_interest`; статус самого loot остаётся `prepared | available | distributed`. Код в этом пункте уже соответствует целевой модели.

### RTR-006 — UI сохраняет legacy-термин `тег`

**Type:** `TR_ERROR`  
**Evidence:** `Rule/routes.ts:61,70` используют названия «Создание тега» и «Редактирование тега»; `AdminDashboard.vue:26` говорит «Справочник тегов».  
**ТР:** `rule-system.md:50–55,151` и `decisions.md:31,36` закрепляют `keyword`.

**Расхождение:** доменный термин current ТР — `keyword`, а пользовательские заголовки frontend используют `тег`.

**Варианты:**

1. Исправить UI-тексты на «признак/keyword».
2. Оставить «тег» как пользовательский термин, а `keyword` считать только техническим.
3. Изменить ТР обратно на `tag`.

**Решение Андрея (2026-08-30):** выбран вариант 1. Пользовательские тексты UI переводятся с «тег» на «признак»/«Признаки», а технический термин `keyword` сохраняется в DTO, документации и доменном контракте. Переименование UI-текстов — `CODE_GAP / implementation OPEN`; current-ТР менять не требуется.

### RTR-007 — Chat sync не выполняет error/retry contract

**Type:** `CODE_GAP`  
**Evidence:** `Messages/Chat/Service/ChatSyncService.ts:49–61` проглатывает polling exceptions; `:74–80` повторяет SSE без observable retry state/backoff.  
**ТР:** `chat-system.md:103–118` уже фиксирует целевое `error/retrying`, cursor preservation и backoff как `CODE_GAP`.

**Расхождение:** нет ошибки ТР; это подтверждённый незавершённый кодовый контракт.

**Варианты:**

1. Реализовать observable error/retry/backoff в ChatSyncService.
2. Ослабить ТР до текущего silent polling.

**Решение Андрея (2026-08-30):** выбран вариант 1. `ChatSyncService` должен публиковать observable-состояния `error`/`retrying`, сохранять cursor при повторе, применять backoff и не проглатывать polling/SSE-ошибки без сигнала UI. Реализация — `CODE_GAP / implementation OPEN`; current-ТР сохраняется.

### RTR-008 — Game нарушает публичные межмодульные границы

**Type:** `CODE_ERROR`  
**Evidence:** `Game/Service/CheckRollService.ts:7–13`, `HitRollService.ts:12,18–26`, `AttackDamageService.ts:1–19`, `ActionEffectService.ts:4,8–9`, `Game/Component/CombatQuickRolls.vue:6–8` импортируют внутренние Rule/Character services, constants или components.  
**ТР:** `architecture.md:70–88` требует public `init.ts`, DTO, stores и plugin boundaries и помечает нарушение как `CODE_GAP`.

**Расхождение:** код нарушает принятый DAG/ownership contract.

**Варианты:**

1. Расширить публичные фасады Rule/Character и перевести импорты на них.
2. Перенести общий UI/сервисы в допустимый модуль.
3. Ослабить архитектурное правило.

**Решение Андрея (2026-08-30):** выбран гибридный вариант. Прикладные модули предоставляют узкие typed capability-фасады через публичный `init.ts`; доменные сервисы получают порты через constructor DI. `ServiceLocator`/module-container используются только в composition root и публичных фасадах для регистрации и выбора real/mock реализаций. Внутренние сервисы не вызывают locator и не получают универсальный container. Общий UI выносится в допустимый модуль только если это действительно общая ответственность. Реализация — `CODE_GAP / implementation OPEN`; архитектурное правило не ослабляется.

### RTR-009 — Character также обходит публичные Rule boundaries

**Type:** `CODE_ERROR`  
**Evidence:** `Character/Service/CharacterEditorService.ts:44,54–65` и `Character/Component/Editor/InventoryItemRow.vue:18` импортируют внутренние Rule API.  
**ТР:** `architecture.md:70–88` требует одинаковое правило для всех прикладных модулей.

**Расхождение:** текущий CODE_GAP описывает Game, но не весь фактический список нарушителей.

**Варианты:**

1. Расширить CODE_GAP в ТР и исправлять Character вместе с Game.
2. Считать Character допустимым privileged consumer и отдельно описать исключение.

**Решение Андрея (2026-08-30):** правило распространяется на весь проект. Все прикладные модули, включая Character, Game и будущие модули, используют public capability-фасады и typed constructor DI; прямой импорт внутренних файлов чужого прикладного модуля запрещён. Исключения — публичные модули Core и plugin registration через публичную точку host-модуля. Character и Game исправляются в рамках единого архитектурного рефакторинга — `CODE_GAP / implementation OPEN`.

### RTR-010 — часть текущих frontend contracts недостаточно отражена в UI-документе

**Type:** `TR_GAP`  
**Evidence:** `Core/User/routes.ts:5–90`, `IUserApi.getUsersByIds`, Chat thread/visibility/speaker contracts и Notification adapter DTO существуют в коде. Основные маршруты описаны, но adapter boundary Notification (`Notification.ts:3–11`) и часть batch/UI acceptance details явно не выделены.  
**ТР:** `ui-system.md:30–39`, `auth-system.md:60–80`, `chat-system.md:41–70`.

**Расхождение:** доменные и backend template models могут выглядеть как один DTO, а batch/adapter boundary не всегда виден читателю.

**Варианты:**

1. Добавить короткий раздел «Frontend adapter boundaries» с exact DTO/API evidence.
2. Оставить это только в evidence registry.

**Решение Андрея (2026-08-30):** выбран вариант 1. В `ui-system.md` добавляются компактные contract cards для Notification adapter, User batch API, Chat host/plugin boundary и UI acceptance details. `AbortSignal` сохраняется как необязательный параметр отмены устаревших async-запросов; он не заменяет error/retry. Полные TypeScript unions в ТР не дублируются. Реализация существующего кода не требуется, пункт закрывает документальный gap.

### RTR-011 — combat/current Game contracts требуют дополнительной детализации

**Type:** `TR_GAP`  
**Evidence:** `IGameApi.ts:38–200`, `GameCombatOverlay.ts:6–30`, `GameDetailPage.vue`, combat/action/DOT tests; текущий код покрывает initiative, overlay, process session, chronicle, loot и multi-target checks.  
**ТР:** `game-system.md:27–40,94–116,148–166` описывает основную семантику, но не все границы `GameCombatOverlay.sheet`, transition payloads и текущего public API.

**Расхождение:** это не неверный контракт, а недостаточная traceability для уже существующего кода.

**Варианты:**

1. Добавить компактные contract cards с public API и ключевыми инвариантами.
2. Оставить детализацию только в коде и evidence registry.

**Решение Андрея (2026-08-30):** выбран вариант 1 в ограниченном объёме. В `game-system.md` добавлены компактные cards для public Game API, `GameCombatOverlay.sheet` и session transitions, без копирования TypeScript unions. Cards описывают только combat/session capability boundaries и инварианты новой модели `approvedCharacterVersion` ↔ `actualCharacter`. Старые A/L/O/P, three-way reconcile и прежние moderation payloads не добавляются; moderation учитывается только как post-session diff approved/actual. Документальный gap закрыт.

## Уже проверенные и не являющиеся новыми пунктами

- Numeric revision и `(spaceId, revision)` согласованы с current docs.
- A/L/O/P, owner notes персонажа и personal notes игры имеют frontend evidence.
- Wide attack, multi-target offers, ActionEffect, movement и DOT уже отражены в `game-system.md`.
- Chat plugin host, attachments, thread и visibility отражены в `chat-system.md`.
- Battleground остаётся backlog и не является пропуском текущего контракта.
- Backend foundation из REC-001 — backend requirement, не frontend gap.
- Snapshot copy — атомарная transaction-коммит; async queue/progress variant заменён.

## Согласованная целевая модель Character/Game

Эта модель согласована Андреем 2026-08-30 и зафиксирована в current-ТР как целевой контракт с открытой реализацией. Старые типы и поля в коде остаются evidence текущего незавершённого состояния и не считаются подтверждённой новой моделью.

### Character

- Персонаж может находиться не более чем в одной игре одновременно.
- В backend у персонажа нет истории версий и нет lifecycle status.
- Backend хранит одно актуальное состояние `actualCharacter`.
- Browser draft хранится только в браузере.
- Backend сохраняет персонажа только после успешной проверки.
- `needs_fix` — результат проверки actual character на его собственной `rulesRevision`, а не состояние в БД.
- Repair при `needs_fix` использует flow миграции, но source и target revision совпадают.

### Game membership

В игре хранится ссылка на персонажа и полная immutable-копия принятого состояния:

```text
GameCharacterEntry
├── gameId
├── characterId
├── status: submitted | active | left
├── approvedCharacterVersion: full snapshot copy | null
├── reviewState: clean | changes_pending | returned
├── returnedAt / returnReason / returnMessageId
└── gameOverlay
```

`submitted` без approved snapshot — новая заявка. `active` с отличием actual character от approved snapshot — принятый персонаж с изменениями, ожидающими модерации; такой персонаж не может быть отклонён как заявка. `left` сохраняется для истории участия и не блокирует вступление в другую игру.

### Session и overlay

```text
sessionCharacter = resolve(approvedCharacterVersion, gameOverlay)
```

Approved snapshot не изменяется во время сессии. Пока сессия активна, любые изменения персонажа записываются только в overlay. Overlay не может изменять:

- owner;
- `characterId`;
- `spaceId`;
- `rulesRevision`;
- permissions;
- visibility.

Overlay действует только пока персонаж находится в игре. При завершении сессии все участники коммитятся одной транзакцией:

```text
resolve all overlays
validate all results
update all actual characters
clear all overlays
mark changed entries for moderation
commit
```

При ошибке одного участника откатывается весь session commit. Изменения actual character во время активной сессии запрещены; session commit должен иметь optimistic guard против устаревшего actual state.

### Moderation

Персонаж находится на модерации, если:

```text
approvedCharacterVersion == null
OR changes(approvedCharacterVersion, actualCharacter)
```

Для сравнения используется существующая функция character/membership diff, а не JSON-хэш и не история версий.

- approve для submitted создаёт active entry и сохраняет approved snapshot;
- approve для active обновляет approved snapshot копией actual character;
- return for rework не удаляет entry, сохраняет причину и отправляет сообщение в character discussion;
- reject application удаляет только ещё не принятую submitted-заявку;
- принятого active-персонажа нельзя отклонить;
- новая сессия блокируется, пока approved snapshot отличается от actual character или actual имеет `needs_fix`.

### Ревизии

- Вне игры владелец может перевести персонажа на разрешённую ревизию.
- В игре персонаж может перейти только на актуальную ревизию этой игры.
- При смене ревизии игры все несовместимые персонажи должны пройти миграцию.
- Миграцию выполняет владелец персонажа.
- После миграции персонаж проходит модерацию.
- До миграции и повторного approve новая сессия заблокирована.

### Выход из игры

Выход разрешён. Если есть активный overlay, он сначала должен быть обработан в рамках завершения сессии. Entry переводится в `left`; оставшееся расхождение actual/approved не даёт права удалить уже принятое участие через reject.

### Решённые design-пункты

| Решение | Статус |
| --- | --- |
| История CharacterVersion не нужна | DECIDED 2026-08-30 |
| Один персонаж — максимум одна игра | DECIDED 2026-08-30 |
| Session overlay не меняет approved snapshot | DECIDED 2026-08-30 |
| Session end — единая транзакция | DECIDED 2026-08-30 |
| Новая сессия блокируется при approved != actual | DECIDED 2026-08-30 |
| Active character нельзя reject | DECIDED 2026-08-30 |
| Migration in-game только на текущую ревизию игры | DECIDED 2026-08-30 |
| Overlay не меняет identity/permissions/visibility/revision | DECIDED 2026-08-30 |
| Выход из игры разрешён | DECIDED 2026-08-30 |

## Дополнительные решения перед реализацией

- Отдельный review snapshot/diff не хранится: для модерации используется существующая функция сравнения approved snapshot и текущего actual character; история персонажа не нужна.
- Смена ревизии игры блокирует запуск следующей сессии только для несовместимых персонажей, а не всю игру целиком.
- Active-персонаж может редактироваться владельцем между сессиями; изменение actual автоматически создаёт расхождение с approved snapshot и требует модерации.

## Порядок обсуждения

Рекомендуемый порядок: `RTR-001` → `RTR-004` → `RTR-003` → `RTR-005` → `RTR-002` → `RTR-008` → `RTR-009` → `RTR-007` → `RTR-006` → `RTR-010` → `RTR-011`.

Каждый пункт обсуждается отдельно. До решения Андрея current ТР и код по этому пункту не изменяются.
