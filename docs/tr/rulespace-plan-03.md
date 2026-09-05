# План RuleSpace 3 — выборочный commit

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **6**. Мир — [`rulespace-plan-01.md`](rulespace-plan-01.md). Мета/лента — [`rulespace-plan-02.md`](rulespace-plan-02.md). Кластер — [`rule-plan-01.md`](rule-plan-01.md). Канон — [`rule-system.md`](rule-system.md), `DEC-015`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-079`.

Цель: оператор собирает **полный** list `RuleCommitEntry` из базовой ревизии + выбранных `put` и tombstone. Часы по-прежнему принимают только полный состав. Без HTTP, Vue, прав, секций, файла ревизии. Draft не в БД: тела выбранных правил приходят во входе.

Порядок: **после RuleSpace 1** (шаг 5 уже сделан). PHP Rule ↛ RuleSpace. **IRules не расширять.** Сборщик только в RuleSpace. Не `open` карт `rule*`.

Набросок Vue `commitDraft(rules, removedCodes)` — эскиз, не JSON этого захода (шаг 7).

Шаги **7–12** и «Позже» не выкинуты. **`changedCount` в ленте по-прежнему не делаем** (нужны множества `version_id` соседних ревизий, не COUNT): не раздувать Versioning. HTTP шаг 7 поле может omit.

## Термины

| Термин | Смысл |
|---|---|
| Полный list | Как сейчас: keep/put на весь состав, вход в `IRules::commit`. |
| Выбор | Базовая ревизия + выбранные новые/изменённые `put` + `removedCodes` (tombstone). |
| Omit-keep | Code из базы, не в put и не в removed → `keep(versionId)` базы. |
| Tombstone | `put(code, bodyБазы, active: false)`. Старые срезы не трогаем. |
| Ничего не выбрать | Пустые put и пустые removed → все keep → часы `assertCompositionChanged` vs **последняя** ревизия → `RULESPACE_INVALID`. Не commit из `[]`. |

## Решения

### 1. Два входа публикации, не tagged union

Кап «10 public» — сигнал смешения ролей, не запрет десятого сценария. Склеить full и select в один `commit(RuleSpaceCommit)` — как раз смешение: один метод, две оси (готовый состав vs сборка из базы). Соседу хуже, чем +1 метод.

Правильно:

| Метод | Смысл |
|---|---|
| `commit(int $spaceId, array $entries)` | Как шаг 4. Полный list `RuleCommitEntry`. Первая ревизия и тесты. Сигнатуру **не** ломать. |
| `commitSelected(int $spaceId, RuleSpaceSelection $selection)` | Выборочная публикация. Сборка полного list внутри оператора, затем тот же `IRules::commit`. |

Второй порт модуля (`IRuleSpaceRevisions`) не заводить: сосед Character/HTTP должен видеть **один** оператор мира. Сборка list — не второй контейнер, а класс `RuleSpaceCommitAssembler` в `Service/` (ось «как склеить keep/put», не sidecar).

На `IRuleSpaces` будет **10** методов. Sniff считает ещё `__construct` у `RuleSpaces` (11 public) — это тот же сигнал, не приказ резать фасад. Точечный `phpcs:disable` с комментарием: один продуктовый оператор мира; два входа публикации не склеивать в tagged union. Не второй `Interface/` «чтобы пройти 10».

`IRules::commit` без изменений.

### 2. DTO только выбора: `RuleSpaceSelection`

Не обёртка над full. Именованный ctor + геттеры (`baseRevision`, `puts`, `removedCodes`) — не `fromNormalized` мешок колонок ST.

Проверки в ctor:

- `baseRevision < 1` → `INVALID`;
- `$puts` не list / элемент не `RuleCommitEntry` / keep (`versionId !== null`) → `INVALID`;
- дубль code в puts → `INVALID`;
- `$removedCodes` не list строк → `INVALID`; trim; пусто после trim → `INVALID`; дубль; code и в put, и в removed → `INVALID`.

`put(..., active: false)` в `$puts` допустим: клиент публикует **своё** тело как неактивное. `removedCodes` — tombstone **телом базы**, без тела во входе. Это разные входы, не дублировать один code в обоих.

Нет ключа «весь draft». Нет сырого `CommitEntry` часов. `commit([])` по-прежнему `INVALID` на полном входе (как часы). Потолок 10000 пунктов — у часов; собранный select длиннее → `INVALID` до `IRules::commit`.

### 3. Сборка `commitSelected`

Класс **`RuleSpaceCommitAssembler`** в `Service/` (не порт контейнера, не `ListQuery`). `RuleSpaces` зовёт его; не раздувать фасад до 500 строк.

После `requireWorld`:

1. `IRules::getRevision($spaceId, $baseRevision)` — нет ревизии → `NOT_FOUND`; номер &lt; 1 уже в DTO.
2. Индекс базы по `code`.
3. Каждый removed: нет в базе → `INVALID` (не no-op: клиент думал, что пишет tombstone). Тело tombstone — `RuleVersionBody` с полей `RuleVersionRecord` базы (type/name/description/spec/keywords/mechanic/contentStatus), `put(code, body, false)`.
4. Каждый put: замена или новый code (create).
5. Остальные code базы → `keep(versionId)`.
6. Порядок: порядок базы (замена/tombstone на месте), новые code из puts — **в конец**, в порядке `$puts`.
7. Собранный list пуст или >10000 → `INVALID` (пустой на живой ревизии часов не случится: срез ≥1 пункт; защита на всякий случай).
8. `IRules::commit($spaceId, $assembled)` + существующий Guard.

Мир **без** ревизий: только `commit(list)`. `commitSelected` → `getRevision` → `NOT_FOUND`.

`commit(list)` сборщик не зовёт.

### 4. Часы сравнивают с latest, не с base

`assertCompositionChanged` смотрит **последнюю** ревизию space. Base может быть не latest (контекст URL `ctx=N`). Собрали то же множество `version_id`, что у latest → `INVALID`, даже если base старше. Собрали keep из старого среза, отличный от latest → штатный новый номер.

Не сравнивать «выбор vs base» отдельно: часы уже режут no-op vs latest.

### 5. Что не трогаем

Нет методов на `IRules` / Versioning. Нет persist draft. Нет `changedCount`. Нет HTTP `commitDraft`. Нет секций в составе. Spec по `RuleType` не валидируем (OPEN канона).

`commit` полного list остаётся для первой ревизии и тестов.

### 6. Тесты

`RuleSpaceMysqlTest` + unit на Selection/сборщик (без MySQL, фикстура Record/Body).

- Старые сценарии `commit(array)` **без** смены вызова.
- `commitSelected`: база из двух правил; put одного code; второе keep тем же `version_id`; новый номер.
- Tombstone выбранного code; в срезе `active: false`; другой code keep.
- Новый code в puts (не было в базе) → появляется.
- Пустые put+removed (все keep vs **latest**) → `INVALID`.
- `commitSelected` с base старше latest, пустой выбор → **новый номер** (откат к version_id базы). Собрать множество latest из старой базы нельзя: keep только id базы, put даёт новые id.
- removed неизвестного code → `INVALID`.
- code и в put, и в removed → `INVALID`.
- keep внутри `$puts` → `INVALID`.
- `commitSelected` на мир без ревизии → `NOT_FOUND`.
- `commit([])` → `INVALID`.
- Сирота часов по-прежнему `NOT_FOUND`.

Не phpunit User. Не HTTP.

### 7. Quality

cs/quality RuleSpace. Quality «слишком много public» на операторе — disable с комментарием §1, не tagged union и не второй `Interface/`. Assembler без портов в контейнере. ctor RuleSpaces без лишней зависимости (`new` assembler или узкий helper). `IRules` / Versioning не трогать, кроме вызовов.

## Todo

- [x] **dto** — `RuleSpaceSelection` (только выбор).
- [x] **assembler** — omit-keep + tombstone body из Record.
- [x] **commitSelected** — второй метод; `commit(array)` как был.
- [x] **gates** — phpcs / quality; suite `rulespace`.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| `IRules::commit` | полный list часов | выбор / draft |
| `commit` оператора | тот же полный list | сборка из базы |
| `commitSelected` | выбор → полный list | JSON Vue |
| Assembler | keep/put/tombstone | open `rule*` |
| HTTP (шаг 7) | `commitDraft` → `commitSelected` | этот файл |

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`rulespace-plan-01.md`](rulespace-plan-01.md); [`rulespace-plan-02.md`](rulespace-plan-02.md); [`rule-system.md`](rule-system.md); [`php-coding-standards.md`](php-coding-standards.md).
