# План RuleSpace 2 — мета мира и лента ревизий

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md) шаг **5**. Каркас мира — [`rulespace-plan-01.md`](rulespace-plan-01.md). Кластер — [`rule-plan-01.md`](rule-plan-01.md). Часы — [`versioning-plan-01.md`](versioning-plan-01.md). Канон — [`rule-system.md`](rule-system.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-079`, `DEC-080`.

Цель: дописать оператор **`IRuleSpaces`**: правка имени/описания, выключение мира (`active=false`), список ревизий с `publishedAt` и `ruleCount`. Без HTTP, Vue, прав User, секций, выборочного commit, файла ревизии, смены `code`.

Порядок: **после RuleSpace 1**. PHP Rule ↛ RuleSpace. **PHP RuleSpace → Rule + SmartTable.** Часы только через `IRules`. Оператор по-прежнему не `open` карт `rule*`.

Набросок Vue `Roleplay/RuleSpace` — эскиз UI, не идентичность DTO (`php-coding-standards`). JSON-ключи шага 7 не фиксируем здесь.

Шаги **6–12** и «Позже» в роудмапе не выкинуты.

## Термины

| Термин | Смысл |
|---|---|
| Мета | Поля sidecar: name, description, active. Не состав часов. |
| Лента | Список опубликованных ревизий мира, без пунктов состава. |
| `ruleCount` | Число пунктов состава (`rule_revision_item`), **включая** tombstone (`active: false`). Не число «живых» карточек каталога. |
| `changedCount` | Diff состава к предыдущей ревизии. **Не этот заход** (шаг 6 / HTTP). |

## Решения

### 1. Что не трогаем

Нет колонок на sidecar сверх плана 1. Нет `latestRevision` / `ruleCount` на `RuleSpaceRecord`. Нет `getList` миров (HTTP шаг 7). Нет `activate` / повторного `active=true`. Нет смены `code`: ключ URL после `add` **иммутабелен** (набросок `SpaceUpdateData` code не шлёт; unique не «освобождаем»). Нет delete часов и sidecar. Нет `changedCount` в PHP DTO (не заглушка `0`).

`active=false` не блокирует `get` / `getRevision` / `findInRevision` / `commit` / ленту. Фильтр «только живые миры» — список HTTP шага 7 и права шага 8.

### 2. Хвост часов (тот же кластер Versioning, не новый план-файл)

`IVersionedRepository` сейчас: addSpace / getSpace / commit / getRevision / findLatestRevision → **7** public после захода (лимит 10).

`VersionedRepository` **уже 6 deps**. Не добавлять 7-го. Лента + counts — **`OpenedCluster`, уже в ctor** (как `findLatestRevision`: `getRevisions()` / `getItems()`). **`RevisionLoader` не трогать**: он гидратит срез одной ревизии (версии), не ленту. `updateSpace` — `getSpaces()->update($spaceId, ['title' => …])` после `assertSpaceExists`. Голый ST `update` без assert → `RowNotFound` → Guard → **`SPACE_INVALID`**.

| Метод | Смысл |
|---|---|
| `updateSpace(int $spaceId, string $title): void` | Trim; пусто → `SPACE_INVALID`. Нет space → `SPACE_NOT_FOUND` (**assert**, не ждать ST). Пишет `title`. Не трогает ревизии. |
| `getRevisionList(int $spaceId): array<int, RevisionSummary>` | Сначала `assertSpaceExists`. Нет ревизий → `[]`, **aggregate не звать** (пустой IN запрещён). Иначе `getList` ревизий `filter` `=space_id`, `sort` `revision` DESC, `limit` `ListQuery::MAX_LIMIT` (10000), `cacheTtl` **null**. |

`RevisionSummary` (не мешок, не расширение `RevisionRecord`): `getId()`, `getSpaceId()`, `getRevision()`, `getPublishedAt()`, `getItemCount()`.

`itemCount`: `aggregate` на `getItems()`, `group` `['revision_id']`, `select` **с ключом группы и мерой** (`'revision_id'`, `new CountField('item_count')`), `filter` `'revision_id' => $ids` (IN), `limit` 500, ttl null. Чанки по 500 id с ленты. Порядок рядов aggregate — group ASC, не DESC ленты: **склеить по id**. Нет группы / count &lt; 1 для id с ленты → `SPACE_INVALID`. Не `getRevision` N раз.

`findLatestRevision` не выкидывать и не подменять лентой в inherit.

Потолок 10000 ревизий: отдать новейшие 10000, не `SPACE_INVALID`. Тест не плодит 10000.

`IRules` сейчас 5 → **7**:

| Метод | Смысл |
|---|---|
| `updateSpace(int $spaceId, string $title): void` | Обёртка; пусто/нет space → `RULE_*`. |
| `getRevisionList(int $spaceId): array<int, RuleRevisionSummary>` | Map Summary → Rule. Нет space часов → `RULE_NOT_FOUND`. Пусто → `[]`. |

`RuleRevisionSummary`: те же поля, что у часов, плюс **`getRuleCount()`** (= itemCount). `getId()` строки ревизии — как у `RuleRevisionRecord`. Не `changedCount`. `IRuleSpaces` возвращает **этот** тип (не второй DTO в RuleSpace, не Versioning).

### 3. Фасад `IRuleSpaces`

Сейчас 6 public. Добавляем 3 → **9**. Выборочный commit — **отдельный** метод на том же операторе ([`rulespace-plan-03.md`](rulespace-plan-03.md)), не смена входа `commit` ради капа.

| Метод | Смысл |
|---|---|
| `update(int $spaceId, RuleSpacePatch $patch): RuleSpaceRecord` | Нет sidecar → `RULESPACE_NOT_FOUND`. Пустой patch (нет ключей) → `RULESPACE_INVALID`. |
| `deactivate(int $spaceId): void` | Sidecar `active=false`. Нет мира → `NOT_FOUND`. Уже false — **идемпотентно**, не ошибка. Не зовёт часы. |
| `getRevisionList(int $spaceId): array<int, RuleRevisionSummary>` | Сначала sidecar. Потом `IRules::getRevisionList`. Нет мира → `NOT_FOUND`. Мир без ревизий → `[]`. |

`commit` / inherit / `add` без изменений контракта.

Имя ленты — `getRevisionList`, не `listRevisions` (глагол чтения `get*`).

### 4. Patch мета (`DEC-079`)

`RuleSpacePatch::fromNormalized` + `fields()`. Разрешённые ключи: **`name`**, **`description`**. Неизвестный ключ / не строка → `RULESPACE_INVALID` **в Patch** (HTTP нормализатора нет). Нет `code`, нет `active`.

Фасад: сначала `requireWorld`. Потом:

- нет ключей → `RULESPACE_INVALID`;
- нет ключа — поле не трогаем;
- `name` есть → trim; пусто → `RULESPACE_INVALID`;
- `description` есть → как есть, `''` допустим.

ST `update` только по **PK строки sidecar**. `RuleSpaceRecord::getId()` = **`space_id` часов**, не PK sidecar (другие таблицы). **Запрещено** `records->update($world->getId(), …)`. Пишет только `RuleSpaceRepository`: `getUnique` по `space_id` → `$row['id']` → `update`. Пустой `$values` ST отвергает.

Если в patch есть `name`: одна TX: `IRules::updateSpace` + sidecar. Откат не разводит title часов и name.

Если только `description`: без TX, часы не звать.

После успеха — `requireWorld`.

### 5. Deactivate

Сначала `requireWorld` (нет мира → `NOT_FOUND` даже у пустого patch / deactivate). Потом репозиторий пишет `active` => false по **PK sidecar**, не по `getId()` мира. Часы не звать. Повтор — снова `active=false`.

### 6. Лента на операторе

Не `open(RuleRevisionTable)`. Guard: `RuleNotFound` → `RULESPACE_NOT_FOUND`, прочий Rule/ST → `INVALID`.

Порядок: DESC по номеру. `ruleCount` inherit-ребёнка rev 1 = числу keep-пунктов родителя (включая tombstone).

### 7. Тесты

Расширить `RuleSpaceMysqlTest` (тот же harness, flush slice-кэша как в шаге 1). Плюс узкие Mysql Versioning + Rule на хвост.

Versioning (`vt_note`): `updateSpace`; `getRevisionList` пустой space / две ревизии DESC; `itemCount` совпадает с составом; нет space → `SPACE_NOT_FOUND`.

Rule: обёртка `updateSpace` / `getRevisionList` + `getRuleCount()`.

RuleSpace:

- `update` name: sidecar и `title` часов (тест `open(RuleSpaceTable)` как в шаге 1);
- `update` только description; пустой name; пустой patch;
- `code` после update не меняется;
- `deactivate`: `isActive()===false`; повтор; `getRevision` и `commit` после выключения живут;
- лента: 0 ревизий → `[]`; две публикации → два ряда, номера DESC, `ruleCount`; tombstone входит в счётчик;
- нет sidecar / часы-only id → `NOT_FOUND` на update / deactivate / ленте.

Unit: Patch неизвестный ключ; wrap кодов.

Не HTTP. Не phpunit User.

### 8. Quality

cs/quality: RuleSpace, хвост Versioning (`getRevisionList` + чанки на `VersionedRepository` / private, `RevisionSummary`), хвост Rule. **Не** 7-й аргумент ctor. **Не** раздувать `RevisionLoader`. `ListQuery` / `AggregateQuery` / `CountField` только Repository/, не Service оператора. `RuleSpaces` ~300 строк — не пилить заранее; quality на размер — согласовать.

## Todo

- [x] **clocks-list** — `RevisionSummary`, `updateSpace`, `getRevisionList` на `OpenedCluster` (не Loader); suite `versioning`.
- [x] **rule-wrap** — `IRules` два метода + `RuleRevisionSummary`; suite `rule`.
- [x] **facade** — Patch, update TX, deactivate, лента; suite `rulespace`.
- [x] **gates** — phpcs / quality затронутых деревьев.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| `{t}_space.title` | Подпись часов | URL code |
| sidecar | name / description / active | состав |
| `IVersionedRepository::getRevisionList` | номера + itemCount | product DTO |
| `IRuleSpaces` | мета мира + лента | JSON / права / delta commit |

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`rulespace-plan-01.md`](rulespace-plan-01.md); [`versioning-plan-01.md`](versioning-plan-01.md); [`php-coding-standards.md`](php-coding-standards.md).
