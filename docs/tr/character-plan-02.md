# План Character 2 — каркас модуля и actual storage

**Статус:** каркас PHP сделан, 2026-09-10. `BACKEND_OPEN`. Нарезка — [`character-roadmap.md`](character-roadmap.md). Контракт save — [`character-plan-01.md`](character-plan-01.md) (**здесь не реализуется**). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Unique user / часы: `ReferenceField::forTable`, не class чужого `Table`.

Цель: ленивый **`Roleplay/Character`**, две карты, фасад записи/чтения **без** валидатора, `IRuleSpaces`, Mechanic, HTTP. После зелёного suite: **`BACKEND_OPEN`**. add/replace не называть save; C5 не закрывать.

## Зафиксировано (модель)

- Селектор мира: `space_id` FK `rule_space` + `rules_revision` (номер, не `rule_revision.id`).
- Persist: `choices` (источник) + `sheet` (кэш GET). Валидатор/build едят только choices.
- Optimistic lock: колонка `actual_version`.
- Видимость: гранты секций. JSON-списки кодов + `is_public` (индекс «поля для всех непусты»). Зрители — таблица `character_viewer` + JSON `fields`. Существование чужому = ≥1 секция (public или своя строка зрителя). Владелец видит всё. Игроки/ГМ игры — не Character (C8).
- Нормализация секций в строки `(character_id, section)` **не** делаем, пока не понадобится поиск по секции.

## Что даёт этот заход

Строка в MySQL, round-trip JSON, guard `actual_version` на фасаде. Тесты только через `ICharacters`. Сосед: `$locator->get(ICharacterContainer::class)->get(ICharacters::class)`.

## Что не закрыто

- HTTP `character.*`, Vue, Chat discussion, Game.
- C2 `IRuleSpaces.getRevision` (номер ревизии в C1 не сверять со срезом).
- C3–C5 build/validate/save.
- C6: list, маска GET, HTTP notes/visibility, enum кодов секций как канон PHP.
- Форма `choices`/`sheet` — канон C4; C1 кладёт PHP-array как есть.

## Модуль

Путь: `www/mifrial/modules/Roleplay/Character`. Неймспейс `Mifrial\Roleplay\Character`. **lazy** в `config/modules.php`: `ICharacterContainer` → group `Roleplay`, name `Character`. `module.config.php`: `routes` / `events` `[]`; в карту портов — `ICharacters`. `setup` → `CharacterModuleSetup`: `getTableClasses()` = `CharacterSchema::getTableClasses()` (`character`, `character_viewer`), data-steps `[]`.

Suite `character` в `phpunit.xml.dist`. PSR-4 + `exclude-from-classmap` tests, как Keyword. CLI setup: карты с диска (`loadAllFromDisk`). `ModuleSetupCollectorTest` **не** расширять (Keyword туда не добавляли).

**DAG прод-PHP:** Character → SmartTable + User (`IUserAccounts`). Карты: `owner_id` / `space_id` / `character_viewer.user_id` — `forTable` (`user`, `rule_space`). Сервисы **не** `IRuleSpaces` / `IRules` / Keyword / Mechanic / Chat / Game / Versioning. Kernel ↛ Character. Тесты mysql **могут** `use` `RuleSpaceTable` только чтобы поставить часы (не полный `RuleSchema`, не Keyword/Mechanic).

Ошибки: `CharacterException` extends `ActionException`. Листья: `CHARACTER_INVALID`, `CHARACTER_NOT_FOUND`, `CHARACTER_CONFLICT`. Наружу не коды ST (`MAP_*`, `REFERENCE_CONSTRAINT`, `UNIQUE_*`) и не `USER_*`.

| Источник | Лист |
|---|---|
| `UserNotFoundException` | `CHARACTER_NOT_FOUND` |
| `ReferenceConstraintException` | `CHARACTER_NOT_FOUND` (нет часов; user уже проверен через `IUserAccounts::getById`) |
| `RowNotFoundException` | `CHARACTER_NOT_FOUND` |
| `FieldInvalidException` / `FieldRequiredException` / `MapInvalidException` | `CHARACTER_INVALID` |

Не писать «SQLSTATE 1452» в коде — только исключения ST.

## Зачем колонки

**`space_id` — reference; `rules_revision` — номер.**  
`spaceId` API = id `rule_space` (= `IRuleSpaces.get`). Не PK sidecar `rulespace`. Нет часов → `CHARACTER_NOT_FOUND`. Ревизия — unique `(space_id, revision)` в Rule; Character хранит номер. Срез состава — C2.

**`actual_version`.** Счётчик строки (create = 1, каждый **успешный** replace/`setActive` +1, даже если `active` уже был таким). Не `rules_revision`.

ST `update` только по `id`, без `WHERE actual_version = ?` и без `FOR UPDATE`. Guard C1: одна `gateway.transaction()` → `getById` → не совпал expected → `CHARACTER_CONFLICT` + `getCurrentVersion()`, **без** write → совпал → `update` (поля + version+1 + `updated_at`). Двухклиентный lost update при одинаковом expected — предел ST, не сырой SQL и не новый метод ST в этом заходе. Тесты однопоточные.

**choices vs sheet.** Пересчёт только из choices + срез. `sheet` — проекция GET, не вход валидатора. C4/C5 пишут оба; C5 save принимает choices.

**Видимость (JSON + `is_public`).** Владелец всегда. Чужой видит лист ⇔ есть ≥1 секция в `visibility_fields` **или** в своей строке `character_viewer.fields`.

| Кому | Хранение | Пусто |
|---|---|---|
| Всем (`character.view`) | `visibility_fields` + `is_public` | `[]`, `is_public=false` |
| Список user id | `character_viewer` (`user_id`, `fields`) | нет строк |

Инвариант: `is_public` ↔ после нормализации `visibility_fields` непуст. Клиент `is_public` не шлёт. List (C6): `owner_id = me OR is_public OR EXISTS viewer(user_id = me)` — **без** JSON в WHERE. Строка зрителя только с ≥1 полем.

Секции — enum ~8 кодов (C6 сверяет с каноном). Имя видно, если лист виден. Грант «всем» и грант зрителя могут отличаться; GET чужому — объединение. JSON секций — мешок на строке, не индекс.

## Таблица `character`

Физическое имя `character` (зарезервировано SQL). Только SmartTable, без сырого SQL. DDL: `createTable` / `updateTable`, не `forceUpdateTable`.

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | `characterId`. |
| `owner_id` | `forTable(..., 'user', 'restrict')` required | Владелец. |
| `space_id` | `forTable(..., 'rule_space', 'restrict')` required | Часы мира. |
| `rules_revision` | int, required, min 1 | Номер ревизии. |
| `name` | string 255, required | Trim; пустой → `CHARACTER_INVALID`. |
| `active` | bool, required, default true | Выключен. |
| `actual_version` | int, required, min 1, default 1 | Lock. |
| `choices` | json, required, default `[]` | Build. C1 не валидирует ключи. List **или** object (`{}`). |
| `sheet` | json, required, default `[]` | Кэш. То же. |
| `visibility_fields` | json, required, default `[]` | **Только list** кодов для всех. |
| `is_public` | bool, required, default false, **indexed** | Зеркало непустых полей всем. |
| `owner_notes` | text, required, default `''` | C6. |
| `created_at` | datetime, required, `DateTimeNow` | |
| `updated_at` | datetime, required, `DateTimeNow` | Пишет фасад тем же `DateTime::now()`, что Chat send. |

`CharacterSchema`: два `IOpenedSchema` (как Rule: character, затем viewer). `install`: character, потом viewer. `getTableClasses()`: `CharacterTable`, `CharacterViewerTable`.

IdField обычный (не `IdField::big()`), как Keyword.

`NewCharacter` / `CharacterRecord` — `DEC-079`: Record геттеры; New — `fromNormalized` + `fields()`. Не публичный мешок. `isActive()` / `isPublic()`.

### `character_viewer`

`CharacterViewerTable`. `id`; `character_id` → `CharacterTable::class` **cascade**; `user_id` `forTable` `user` restrict; `fields` json required (list, ≥1 после нормализации). Unique `(character_id, user_id)`. C1 фасад **не** add/remove зрителей (таблица пустая в тестах C1, Schema ставит).

Индексы: FK owner/space/character/user, `is_public`.

Граф mysql-теста: User schema → `createTable` `rule_space` (открыть `RuleSpaceTable` в тесте) → insert часов (`title` required) → Character schema. Drop: viewers, `character`, часы если тест создавал, User. CLI setup: topo `user` + `rule_space` (модуль Rule уже в системе) до Character.

## Фасад `ICharacters` (ровно 4 public)

Не `ICharacter`. Не `ListQuery`. Не HTTP-вид. Не `getList`.

| Метод | Смысл |
|---|---|
| `add(NewCharacter $new): int` | Сначала `IUserAccounts::getById(owner)`. Insert; `actual_version = 1`; `is_public` из полей. Нет user/часов → `NOT_FOUND`. Пустое имя / spaceId меньше 1 / revision меньше 1 → `INVALID`. Не build, не `getRevision`. |
| `get(int $id): CharacterRecord` | Нет строки → `NOT_FOUND`. Без ACL (как Chat `getById`). |
| `replacePayload(...)` | Нет строки → `NOT_FOUND`. Только choices+sheet+version+`updated_at`. Visibility / имя / space / notes / active не трогать. `expectedVersion` меньше 1 → `INVALID`. Guard в TX как § actual_version. |
| `setActive(...)` | Нет строки → `NOT_FOUND`. Тот же TX-guard. Без rebuild. Успех всегда +1 version. |

После `add` в C1 не менять имя, space, revision (миграция — C7; имя — C5/C6).

`NewCharacter`: ownerUserId, spaceId, rulesRevision, name, choices default `[]`, sheet default `[]`, visibilityFields default `[]`, ownerNotes default `''`, active default true. Нет `expectedVersion`, `isPublic`, зрителей.

`CharacterRecord`: геттеры id, ownerId, spaceId, rulesRevision, name, `isActive`, actualVersion, choices, sheet, visibilityFields, `isPublic`, ownerNotes, createdAt, updatedAt (`DateTime` Kernel). Зрителей в get нет.

**Нормализатор**

- name: trim, непустой.
- `choices` / `sheet`: PHP `array` (list или map, в т.ч. `[]`). JSON-скаляр / `null` → `INVALID`. Ключи C1 не проверяет.
- `visibility_fields`: `array_is_list`. Элементы — string; trim; пустой токен → `INVALID`; не string → `INVALID`; **дубль после trim → `INVALID`** (не молча unique). Затем **сортировка** для стабильного GET. Enum секций C1 не сверяет.
- `is_public` только из непустоты этого списка.
- `owner_notes`: string, trim не обязателен как имя (как Chat content — не пустой запрет; `''` ок).

Конфликт: `CharacterConflictException` + `getCurrentVersion(): int`.

Фабрика: gateway ST + `IUserContainer` → `IUserAccounts`. Ctor фасада ≤6 (репозиторий + accounts).

## Тесты

Mysql (`MIFRIAL_CONFIG=test`): User + одна строка `rule_space` → Character.

- add+get: поля, `actual_version === 1`, `is_public === false`;
- add с `visibilityFields: ['race', 'inventory']` → GET `['inventory', 'race']` (sort), `is_public === true`;
- дубль секции → `INVALID`;
- нет user / нет часов → `NOT_FOUND`;
- пустое имя / revision 0 / spaceId 0 / expectedVersion 0 → `INVALID`;
- `choices` скаляр → `INVALID`; `visibility_fields` object / дубль → `INVALID`;
- `replacePayload` верный expected → version 2, новые choices/sheet, **visibility и `is_public` те же**;
- stale replace / stale `setActive` → `CONFLICT`, строка как была;
- `setActive(false)` верный expected → `active` false, version +1, choices те же;
- два add с одним именем — ок;
- replace/`setActive` неизвестного id → `NOT_FOUND`;
- boot: `lazy`, `get(ICharacters)`; у модуля `routes` `[]` (не как Keyword HTTP).

Unit: trim name; `is_public`; разбор New; ключ конфликта.

cs/quality дерева Character. Не suite `rule` / Game.

## Todo

- [x] **module** — lazy, контейнер, setup, autoload, suite `character`.
- [x] **table** — две карты + Schema `install`.
- [x] **facade** — 4 public, wrap ST/User, TX guard.
- [x] **gates** — phpunit `character`; cs/quality.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| `CharacterTable` / `CharacterViewerTable` | `Table/` | карты |
| Record / New | `Dto/` | геттеры / ключи insert |
| `ICharacters` | `Interface/Service/` | сосед |
| `ICharacterContainer` | `Interface/Container/` | слот |
| `Characters` | `Service/` | trim, `is_public`, version guard |
| `CharacterPortFactory` | `Service/` | `new` + User container |
| репозиторий | `Repository/` | records, map FK |
| Schema | `Schema/` | два opened schema; install character → viewer |
| `CharacterModuleSetup` | `Setup/` | карты в CLI |
| листья | `Exception/` | `CHARACTER_*` |

Нормализатор — в `Service/` (не отдельный порт, пока один сток).

## Acceptance C1

- Suite `character` зелёный; cs/quality модуля.
- Нет HTTP, нет `IRuleSpaces` в прод-классах Character.
- `space_id` — reference на `rule_space`; ревизия — int.
- Visibility: JSON списки + `is_public`; таблица зрителей есть, фасад её не пишет.
- Статус линии: `BACKEND_OPEN`.

## Документы захода

этот файл; [`character-plan-01.md`](character-plan-01.md) §7–8; [`character-roadmap.md`](character-roadmap.md) C1; `composer.json` (PSR-4 `Character` + `Character\\Tests`, exclude-from-classmap tests); `phpunit.xml.dist` suite `character`; `config/modules.php`.

## Следующий заход

C2 — срез RuleSpace на save, не HTTP. C1 после слияния остаётся `BACKEND_OPEN`.
