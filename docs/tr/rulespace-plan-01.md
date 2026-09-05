# План RuleSpace 1 — оператор миров правил

**Статус:** каркас PHP сделан, 2026-09-05. Нарезка — [`rule-roadmap.md`](rule-roadmap.md). Канон — [`rule-system.md`](rule-system.md). Кластер — [`rule-plan-01.md`](rule-plan-01.md). Часы — [`versioning-plan-01.md`](versioning-plan-01.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-080`. Наследование состава — snapshot-copy (`rule-system`, не путать с `DEC-002` про ресурс).

Цель: ленивый **`Roleplay/RuleSpace`**. Продуктовая мета пространства (`code`, имя, описание, active) **поверх** `id` часов `rule_space`, не колонки универсального `{t}_space`. Состав и COW — только `IRules`. Без HTTP, Vue, прав User, секций каталога, файла ревизии.

Порядок: **после Rule 1**. PHP Rule ↛ RuleSpace. **PHP RuleSpace → Rule + SmartTable.** Не Character, не Game, не Keyword/Mechanic напрямую, не `open` карт `rule*` кластера.

Не тащить колонки `code`/`owner_id` в `RuleSpaceTable` модуля Rule.

Этот заход = шаг **4** нарезки. Остальное не выкинуто: шаги **5–12** и блок «Позже» в [`rule-roadmap.md`](rule-roadmap.md) (мета/лента, выборочный commit, HTTP `ruleSpace.*`, права, секции, Vue-имя, HTTP Keyword/Mechanic, файл ревизии).

## Термины

| Термин | Смысл |
|---|---|
| Часы space | Строка `rule_space`: `id` + `title`. Не маршрут `/space/{code}`. |
| Мир | Продуктовое пространство: sidecar + тот же `spaceId`. |
| Билет | Пара `(spaceId, revision)` после проверки, что ревизия есть. URL несёт `spaceCode` + `ctx`. |
| Inherit | Атомарно: новый мир + **тот же набор `version_id`**, что у источника (`keep` всех пунктов). Не новые строки `rule_version`. Дальше миры независимы. |
| Tombstone | `put(code, body, active: false)` **в составе**. Omit пункта — правила нет в мире (это не удаление-история). |

## Решения

### 1. Модуль

Путь: `www/mifrial/modules/Roleplay/RuleSpace`. Неймспейс `Mifrial\Roleplay\RuleSpace`. **lazy**: `IRuleSpaceContainer` → group `Roleplay`, name `RuleSpace`. Сосед: `$locator->get(IRuleSpaceContainer::class)->get(IRuleSpaces::class)`.

PSR-4 + Tests в `composer.json`; suite `rulespace` в `phpunit.xml.dist`. Port-boot как у Keyword/Rule. Setup: `getTableClasses()` **только sidecar** `rulespace`. Data-steps пустые. Mysql-тест **сам** поднимает Keyword + Mechanic + `rule*` (как `RuleMysqlTest`), затем sidecar. Не suite `rule` внутри класса. Тест имеет право `open(RuleSpaceTable)` для DDL и assert `title` часов; **Service оператора** карты кластера не открывает.

Порт наружу: `IRuleSpaces`. Фабрика: `IRuleContainer` → `IRules`; `ISmartTableContainer` → `open(RuleSpaceMetaTable)` + `transaction` на inherit. **Не** `IVersionedCatalog` / `openCluster`.

`modules.php` lazy: … Rule, **потом** RuleSpace. FK sidecar → `Rule\Table\RuleSpaceTable` (часы). Класс sidecar — `RuleSpace\Table\RuleSpaceMetaTable` (не `RulespaceTable`: PHP class names case-insensitive, столкновение с `RuleSpaceTable`). Физика `rulespace` (не `rule_space`). `TableSetupOrder` по `reference`.

**DAG PHP:** RuleSpace → Rule + SmartTable. Не Versioning напрямую (часы только через `IRules`). Не User в этом заходе. Kernel ↛ RuleSpace. Не eager `Core/*`.

Ошибки: `RuleSpaceException` extends `MifrialException`. Два листа: `RULESPACE_INVALID`, `RULESPACE_NOT_FOUND`. Наружу не `RULE_*` / `SPACE_*` / `MAP_*` / `UNIQUE_*`. `RuleNotFoundException` → `RULESPACE_NOT_FOUND`; прочий `RuleException` / ST → `RULESPACE_INVALID` (`previous`).

### 2. Sidecar `rulespace`

Не кластер часов. 1:1 с `rule_space`. Продуктовый **id мира = `space_id` часов**, не автоинкремент sidecar (если у строки есть своё `id` — фасад его не отдаёт).

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Строка sidecar; не JSON/`getId()` мира. |
| `space_id` | reference → `RuleSpaceTable`, required, **unique** | Часы. Restrict. |
| `code` | string 255, required, **unique** | Ключ URL `/space/{code}`. Trim; пустой → `RULESPACE_INVALID`. Не `DEC-060` (тот про `ruleCode` на правиле). |
| `name` | string 255, required | Подпись продукта. Trim; пустой → `RULESPACE_INVALID`. Копия в `IRules::addSpace($name)` (`title` часов). |
| `description` | text, required | `''` допустим. |
| `active` | bool, required, default true | Выключен в продукте; строка часов остаётся. Писать `false` — не этот заход. |
| `created_at` | datetime, required, default now | Создание мира. |

Нет `revision` / `rules_count` колонками (считать с билета / среза). Нет inherit-FK: источник только аргумент `add`, не живая связь. `owner_id` на sidecar — [`rulespace-plan-05.md`](rulespace-plan-05.md), не часы Rule.

### 3. Хвост часов: `findLatestRevision`

`IVersionedRepository` сейчас не умеет «последний номер». Список ревизий HTTP — позже; inherit из Vue `inheritFrom: spaceId` = **последняя** ревизия родителя.

В **этом** заходе, в Versioning (тот же кластер, не новый план-файл):

| Метод | Смысл |
|---|---|
| `findLatestRevision(int $spaceId): ?RevisionRecord` | Нет space → `SPACE_NOT_FOUND`. Нет ни одной ревизии → `null`. Иначе строка с максимальным `revision`. |

`IRules::findLatestRevision(int $spaceId): ?RuleRevisionRecord` — обёртка + map DTO (**без** пунктов состава). Пятый метод фасада Rule (лимит 10). Нет space часов → `RULE_NOT_FOUND`. Нет ревизий → `null`.

Реализация часов: `getFirst` ревизий `filter space_id`, `sort revision DESC` (как `RevisionPublisher`), не `MAX()` в Service и не `getList` из RuleSpace.

`IRules::addSpace` остаётся для тестов Rule и для `IRuleSpaces::add`. Сосед продукта (`Character`) не зовёт `IRules` напрямую.

### 4. Фасад `IRuleSpaces`

≤10 public. Черновик не в БД.

| Метод | Смысл |
|---|---|
| `add(string $code, string $name, int $ownerUserId, string $description = '', ?int $inheritFromSpaceId = null): RuleSpaceRecord` | Trim code/name. Owner &lt; 1 / нет user → `RULESPACE_INVALID` (план 5). `inheritFromSpaceId !== null` и `< 1` → `RULESPACE_INVALID`. Дубль `code` → `RULESPACE_INVALID`. Inherit: §5. |
| `get(int $spaceId): RuleSpaceRecord` | Нет sidecar → `RULESPACE_NOT_FOUND`. Сирота часов без sidecar не продукт. |
| `getByCode(string $code): RuleSpaceRecord` | Trim; нет → `RULESPACE_NOT_FOUND`. |
| `getRevision(int $spaceId, int $revision): RuleRevisionSlice` | Прокси `IRules` после проверки sidecar. Нет мира или ревизии → `RULESPACE_NOT_FOUND`. Номер &lt; 1 → `RULESPACE_INVALID`. |
| `findInRevision(int $spaceId, int $revision, string $code): RuleVersionRecord` | Прокси после sidecar. Нет в составе → `RULESPACE_NOT_FOUND`. |
| `commit(int $spaceId, array $entries): RuleRevisionRecord` | Полный состав, прокси `IRules`. Нет sidecar → `RULESPACE_NOT_FOUND`. |

Нет `addSpace` на `IRuleSpaces`. Нет `keep`/`put` фабрик здесь — те же `RuleCommitEntry` модуля Rule.

`RuleSpaceRecord`: `getId()` = `space_id`; code, name, ownerId, description, active, createdAt. **Не** `latestRevision` / `ruleCount` на каждом `get`: это полный срез (`IRules::getRevision` клеит `code`). Номер и счётчик — из `getRevision` / [`rulespace-plan-02.md`](rulespace-plan-02.md) (лента). Не мешок (`DEC-079`).

Билет для Character позже: `(spaceId, revision)` после успешного `getRevision`. Отдельный тип `RuleSpaceTicket` в этом заходе **не** плодить.

### 5. Inherit

Вход: `inheritFromSpaceId`. Сначала sidecar родителя: нет мира → `RULESPACE_NOT_FOUND` (не `findLatest` по голому id часов). Потом `IRules::findLatestRevision`.

Нет ревизии у родителя → новый мир **без** commit (как `add` без inherit).

Есть ревизия: `IRules::getRevision` родителя. `IRules::commit` не принимает пустой list; ревизия часов всегда с ≥1 пунктом. Наследник: `commit(keep(каждый versionId))`, **включая** `active: false`. Не ветка «пустой срез → skip commit».

Одна TX `gateway.transaction`:

1. `IRules::addSpace($name)`;
2. insert sidecar (`space_id`, code, name, `owner_id`, description);
3. при наличии ревизии у родителя — `commit` keep.

Копия **указателей** на экземпляры, не `put` новых тел. Identity `rule` не дублируется.

Внешняя TX допустима: кэш среза часов во время открытой TX не пишется ([`versioning-plan-01.md`](versioning-plan-01.md)); после commit TX `getRevision` собирает с БД. Не оставлять TX через HTTP (HTTP всё равно не этот заход). Обычный `commit` без inherit **не** оборачивать «на всякий случай».

Атомарность: откат TX не оставляет мир без часов / часы без sidecar / inherit без состава, если состав должен был скопироваться.

Два мира, один `version_id` в составах — штатно (`keep`).

### 6. Commit в этом заходе

Только полный list, как `IRules`. Сборка delta / `removedCodes` — шаг **6** нарезки, не этот файл.

Тест шага 4: второй `commit` в мире **меняет** состав (keep части + `put`). Повторный keep того же множества version_id часы режут (`assertCompositionChanged`) → `RULESPACE_INVALID`.

### 7. Тесты

Mysql один класс `RuleSpaceMysqlTest`. Фикстуры правил — `IRules` + Keyword/Mechanic, не SQL.

- `add` без inherit; `get` / `getByCode`; id мира = `space_id`; `title` часов = name.
- дубль `code` / пустой code/name.
- inherit: родитель с двумя правилами → ребёнок rev 1, те же `version_id`, те же `entityId`; `put` в родителе rev 2 не меняет срез ребёнка.
- inherit копирует tombstone (`active: false`) тем же `version_id`.
- inherit от мира без ревизии → ребёнок без `getRevision(..., 1)`.
- inherit от неизвестного id и от id часов без sidecar → `RULESPACE_NOT_FOUND`.
- откат внешней TX inherit: нет sidecar и нет ревизии ребёнка.
- `commit` полного состава во втором мире; `findInRevision`.
- `get` часов-only id без sidecar → `RULESPACE_NOT_FOUND`.
- `IRules::addSpace` без sidecar: `IRuleSpaces::commit` / `get` этого id → `RULESPACE_NOT_FOUND`.

Unit: trim; wrap кодов.

Не phpunit User/Chat. Не HTTP. Не дерево `AbilitySection`.

### 8. Quality

cs/quality `Roleplay/RuleSpace` и узкий хвост Versioning/Rule (`findLatestRevision`). ctor ≤6. `ListQuery` sidecar — Repository/, не Service/.

## Todo

- [x] **clocks-latest** — `IVersionedRepository::findLatestRevision`; обёртка `IRules`; suite `versioning` + `rule`.
- [x] **module** — lazy RuleSpace, sidecar, setup, `modules.php`.
- [x] **facade** — `IRuleSpaces`, inherit TX, wrap.
- [x] **gates** — phpunit `rulespace`; cs/quality.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| `rule_space` | Часы: id + title | code URL |
| `IRules` | состав, identity, latest | миры / inherit / HTTP |
| sidecar `rulespace` | code, name, active | spec правила |
| `IRuleSpaces` | мир + билет + inherit | секции / права / JSON (шаги 5–9 нарезки) |
| HTTP (шаг 7, этот модуль) | `ruleSpace.*` | кластер часов |

## Документы захода

этот файл; [`rule-roadmap.md`](rule-roadmap.md); [`rule-plan-01.md`](rule-plan-01.md); [`versioning-plan-01.md`](versioning-plan-01.md); [`php-coding-standards.md`](php-coding-standards.md).
