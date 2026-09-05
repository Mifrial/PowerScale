# План Versioning 1 — кластер и фасад Space

**Статус:** сделано, 2026-09-05. Нарезка — [`versioning-roadmap.md`](versioning-roadmap.md). Конвейер — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-080`. ST 18 и Cache 1 **сделаны**. Этот заход — код кластера.

Цель: ленивый **`Versioning/Space`** — репозиторий одной сущности со своими часами. Фикстура `vt_note`, не Rule. Без HTTP и Vue.

## Что делаем с лимитом 500

Не чанки в Versioning и не глобальный «все списки по 10k» в HTTP.

1. **ST 18 сначала:** потолок **`getList` = 10000** (страницы Chat/User по-прежнему шлют 20–50). `aggregate` остаётся 500.
2. Тот же потолок закрывает и `IN` по id, и `revision_id = n` (состав — не getByIds).
3. **`addMany`** на запись состава/новых экземпляров в commit (до 10000).
4. **Кэш среза:** `Core/Cache`, ключ ревизии, TTL 30 суток, без тегов ST и без «навсегда».

Пока ST 18 и Cache 1 были блокерами; оба влиты. Чанки 500 в фасаде спейса не писать.

## Термины

| Термин | Смысл |
|---|---|
| Кластер | Пять карт: identity, экземпляр, space, revision, состав. Конверт полей — §2. |
| Identity | Строка `{t}`: стабильный `id`. |
| Экземпляр | Иммутабельная `{t}_version`. |
| Space | Мир этой сущности. Не URL `/space`. |
| Revision | Номер ≥ 1 внутри space; `publishedAt` заморожен. |
| Состав | Строки `{t}_revision_item`: `revision_id` + `version_id`, порядок = `id` вставки. |
| Draft | Только клиент. В MySQL нет. |
| Keep / create / change | Входы `commit`: указать существующий экземпляр / новая identity / новый экземпляр к существующей identity. |

## Решения

### 1. Модуль

Путь: `www/mifrial/modules/Versioning/Space`. Неймспейс `Mifrial\Versioning\Space`. **lazy** в `config/modules.php` как Chat: `ISpaceContainer` → group `Versioning`, name `Space`. Контейнер `SpaceContainer` / `ISpaceContainer`. Сосед: `$locator->get(ISpaceContainer::class)->get(IVersionedCatalog::class)`.

`composer.json` PSR-4 + Tests; `phpunit.xml.dist` suite `versioning`. Setup: ключ `setup` с пустым `getTableClasses()` (фикстура не прод) **или** без ключа, как Cache — не плодить пустой Setup «ради галочки», если collector и так skip. Data-steps нет. Mysql-тест сам `createTable` пяти карт.

Порты: `IVersionedCatalog` (не `IOpenedRecords` наружу). Фабрика `open` ST и `ICacheStore` из локатора, не второй Redis.

**DAG:** Versioning → SmartTable + Cache. Для fail-soft debug кэша фабрика может взять `IRuntimeConfig` с Kernel (как ST) — одно чтение `isDebug()`, не репозиторий Kernel. Не User, не Rule, не Chat. Kernel ↛ Versioning. Не `Core/*` eager.

Ошибки: `SpaceException` extends `MifrialException` (как Cache, **не** `ActionException` — HTTP нет). Коды `SPACE_INVALID`, `SPACE_NOT_FOUND`. Наружу **не** `MAP_*` / `UNIQUE_*` / `CACHE_*` / `TABLE_*`: wrap `SmartTableException` и `CacheException`.

### 2. Кластер любой версионируемой сущности

Один репозиторий = пять Basic-карт. Имена от физического `{t}` identity (у фикстуры `{t}` = `vt_note`). У Rule свой `{t}` ([`rule-plan-01.md`](rule-plan-01.md)), **тот же конверт колонок**.

Версионируемая сущность = **identity + экземпляр**. Часы (space / revision / состав) общие. Нет draft-таблицы. `code` пространства, inherit, секции каталога — не этот кластер (RuleSpace).

`reference` required + **restrict** (`new ReferenceField(..., Target::class, 'restrict')`). InnoDB индексирует FK — не дублировать `indexed` на ту же колонку. Составной unique — `defineUniqueKeys()`. `revision`: `new IntField('revision', required, 1, null)`. Datetime «сейчас» — `default => DateTimeNow::instance()`, не строка `now`. `active` — `BoolField`, default true.

Цикл FK запрещён: `{t}` ← `{t}_version` ← `{t}_revision_item` → `{t}_revision` → `{t}_space`.

DDL в mysql-тесте: `$gateway->open(Fixture::class)->schema()->createTable()`, **не** `ITableCatalog::createTable`. Порядок: `{t}` и `{t}_space` → `{t}_version` и `{t}_revision` → `{t}_revision_item`. Снятие: `schema()->deleteTable()` (не `dropTable` — тот метод у каталога словаря). Перед delete — `exists()`, иначе `TABLE_MISSING`. Обратный порядок FK.

#### 2.1 `{t}` — identity

Стабильный ключ между ревизиями. COW её не переписывает. Фасад плана 1 не `delete` identity (restrict при живых версиях).

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | `entityId`. Не номер ревизии. |

Других **служебных** колонок нет. Домен на identity — только то, что **общее для всех экземпляров** и не должно меняться COW (у Rule — `code`, §2.7). Фикстура плана 1 identity пустая. **`space_id` на identity нет.**

#### 2.2 `{t}_version` — экземпляр

Одна строка = один снимок. Фасад не `update` экземпляр: change → новый `add`.

| Поле | Тип | Required | Default | Смысл |
|---|---|---|---|---|
| `id` | IdField PK | да | — | `versionId` в составе. |
| `entity_id` | reference → `{t}` | да | — | Чья identity. |
| `created_at` | datetime | да | now | Время строки, не селектор персонажа. |
| `active` | bool | да | true | Marker-удаление на **этом** снимке. Если id в составе — строка в срезе есть. |

Служебный конверт — только четыре колонки выше. Дальше в `defineFields()` — **обычные поля сущности** (`title`/`body` у фикстуры; тип/имя/`spec` у Rule). Это не колонка `payload` и не json-мешок Versioning. COW копирует новую строку с новым телом.

#### 2.3 `{t}_space` — пространство репозитория

Часы этой сущности. Не маршрут `/space/{code}`.

| Поле | Тип | Required | Смысл |
|---|---|---|---|
| `id` | IdField PK | да | `spaceId` в commit / getRevision. |
| `title` | string 255 | да | Подпись. Trim; пустой → `SPACE_INVALID`. Не unique. |

Нет `code`, `owner_id`, `active`, `description` — мета RuleSpace ([`rulespace-plan-01.md`](rulespace-plan-01.md)) поверх `id`, не колонки универсального space.

#### 2.4 `{t}_revision` — публикация

| Поле | Тип | Required | Default | Смысл |
|---|---|---|---|---|
| `id` | IdField PK | да | — | `revision_id` состава. |
| `space_id` | reference → `{t}_space` | да | — | Чей номер. |
| `revision` | int min 1 | да | — | Номер внутри space. Unique с `space_id`. |
| `published_at` | datetime | да | now | Иммутабелен после insert. Селектор — `(spaceId, revision)`, не это поле. |

`defineUniqueKeys`: `['space_id', 'revision']`. Номер только фасад (MAX+1). Состав не json-колонка.

#### 2.5 `{t}_revision_item` — состав

Срез = эти `version_id`, не `asOf`.

| Поле | Тип | Required | Смысл |
|---|---|---|---|
| `id` | IdField PK | да | Порядок среза: sort `id` ASC = порядок commit. |
| `revision_id` | reference → `{t}_revision` | да | Публикация. |
| `version_id` | reference → `{t}_version` | да | Экземпляр. Один version — в нескольких ревизиях (`keep`). |

`defineUniqueKeys`: `['revision_id', 'version_id']`. Две identity в одной ревизии режет фасад до insert (§4). Нет `entity_id` и `sort_order` на пункте.

#### 2.6 Фикстура `vt_note`

Не домен: прогон кластера без Rule. Классы в `tests/Fixture/`, в прод-setup нет.

`vt_note` — только `id`. `vt_note_version` — конверт §2.2 плюс:

| Поле | Тип | Required | Смысл |
|---|---|---|---|
| `title` | string 255 | да | Заголовок снимка. |
| `body` | text | да | Текст; `''` допустим. |

Реестр: identity class → пять class-string. Нет записи → `SPACE_INVALID`.

#### 2.7 Как ляжет Rule

См. [`rule-plan-01.md`](rule-plan-01.md), нарезка — [`rule-roadmap.md`](rule-roadmap.md). Тот же конверт. `{t}` = `rule`. Identity: `code` unique. Справочники — модули Keyword и Mechanic, не этот кластер. Тело на version, в т.ч. `Reference` на механику. Резолв по составу. Inherit — RuleSpace.

### 3. Фасад

Движок кластера — generic: `CommitEntry`/`VersionRecord` с картами колонок identity и версии (не `title`/`body` как контракт). Фикстура `vt_note` только в тестах. `open` по реестру и `openCluster(ClusterSpec)` без реестра. Commit из рядов ST.

`IVersionedCatalog`: `open(string $identityClass)`, `openCluster(ClusterSpec)`. Нет class в реестре / identity не definition → `SPACE_INVALID`.

Реестр **инжектится** (карта identity class → пять class-string). Прод-фабрика: пустая карта (vt_note не в setup). Mysql-тест собирает каталог **как GatewayHarness**: свои пять Fixture + `FileCacheStore($temp)`, не обязательный `locator->get` с пустым реестром.

Карты фикстуры — `tests/Fixture/`, не `getTableClasses()` модуля.

Для плана 1 DTO тела — карты колонок в `CommitEntry`, не адаптер notes в `Interface/`. Узкий `INoteSpaces` **не** плодить рядом с `IVersionedRepository`: 4 метода ниже.

`IVersionedRepository` (4 public, лимит 10):

| Метод | Смысл |
|---|---|
| `addSpace(string $title): int` | Trim; пустой title → `SPACE_INVALID`. |
| `getSpace(int $spaceId): SpaceRecord` | Нет строки → `SPACE_NOT_FOUND`. |
| `commit(int $spaceId, array $entries): RevisionRecord` | list `CommitEntry` 1..10000. |
| `getRevision(int $spaceId, int $revision): RevisionSlice` | `revision` меньше 1 → `SPACE_INVALID`. Нет строки → `SPACE_NOT_FOUND`. |

Нет `draft()`, нет `getList` на репозитории в обход среза. Срез — `RevisionSlice`. Поля Record фикстуры = колонки `vt_note_version`, не абстрактный json.

DTO:

- `SpaceRecord`: `getId()`, `getTitle()`.
- `RevisionRecord`: `getId()`, `getSpaceId()`, `getRevision()`, `getPublishedAt()`.
- `VersionRecord`: `getVersionId()`, `getEntityId()`, `isActive()`, `getFields()`, `getCreatedAt()`.
- `RevisionSlice`: ревизия + `getItems(): list<VersionRecord>` в порядке состава.
- `CommitEntry`: фабрики `keep(int $versionId)`, `create(array $identityFields, array $versionFields, bool $active = true)`, `change(int $entityId, array $versionFields, bool $active = true)`. Без `id` / `entity_id` / `created_at` / `active` в картах.

`commit` — `gateway.transaction()`. Если TX **уже** открыта (потребитель вроде Rule пишет ещё и свои строки в той же TX), выполнить работу в ней: не `TRANSACTION_OPEN`, не свой commit/rollback. Кэш среза — **после** успешного commit **внешней** TX (если вызывающий открывал TX — после его commit; иначе после своей). Чтение getRevision без транзакции.

### 4. Алгоритм commit

Вход: `array_is_list` экземпляров `CommitEntry` (не карт), длина 1..10000. Иначе `SPACE_INVALID`. Нет space → `SPACE_NOT_FOUND`. Нет version у `keep` / нет identity у `change` → `SPACE_INVALID` (плохой вход, не `NOT_FOUND`).

Один проход, без черновика в БД:

1. `keep`: нет keep-id — не звать getList. Иначе уникальные id → `getList` filter `id` => list (IN), limit 10000, ttl null. Число рядов ≠ числу **уникальных** id → `SPACE_INVALID`. В состав `version_id`. Identity = `entity_id` ряда.
2. `create`: `add`/`addMany` identity с картой колонок пункта; затем version (`entity_id`, `active` + поля версии; `created_at` можно опустить — default now). В состав новый `version_id`.
3. `change`: identity должна существовать; **всегда** новый version (COW); в состав новый id.
4. Одна identity дважды в составе (keep+change, два keep) → `SPACE_INVALID`. Create всегда новая identity. Keep и change на одну entity — запрет.
5. `keep` version, чей entity уже занят другим пунктом — запрет.
6. Следующий номер: `AggregateQuery` + `Dto\MaxField('revision', 'max_revision')`, `group` `['space_id']`, `select` `['space_id', $max]`, filter `=space_id`, `limit` 1, ttl **null**. `rows()` пусто → 1. Иначе `(int)$row['max_revision'] + 1`. Гонка: `UniqueConstraintException` → **один** повтор MAX+1 в той же TX; второй промах → `SPACE_INVALID`. Не вкладывать второй `gateway.transaction()`.
7. Сравнить множество `version_id` с последней ревизией: `getFirst` filter `=space_id`, sort `revision` DESC (null — первая публикация). Равенство множеств → `SPACE_INVALID`. Порядок не спасает.
8. `add` revision (номер с шага 6; `published_at` default now). Для Record/кэша — `getById` этой строки **внутри TX**. Затем `addMany` пунктов состава (порядок входа).
9. Сначала id identity, потом version с `entity_id`. Несколько version — `addMany`.

Собранный `RevisionSlice` **после** TX — `write` в кэш (не второй getRevision). Отказ write при `!debug` не откатывает commit (данные в БД). Старые ключи не трогать.

### 5. getRevision и кэш

Один кэш на **готовый срез**, не N кэшей на строки.

Ключ **`vs:{identityTable}:{spaceId}:{revision}`** (как [`cache-plan-01.md`](cache-plan-01.md); ST не использует префикс `vs:`). Store не добавляет префикс сам.

Payload — **JSON** ассемблера слайса (unix у datetime), не `serialize` PHP. `write(key, json, ICacheStore::MAX_TTL_SECONDS, [])`.

`!isUsable()`: не звать `read`/`write` (Unusable кидает `CACHE_INVALID`). I/O / битый JSON: без debug — промах и SQL; debug — `SPACE_*`. Fail-soft в Space.

Промах:

1. `getUnique` ревизии: `ListQuery::fromOptions` с filter `=space_id` **и** `=revision`, `limit` 1 (внутри unique станет 2), **без** `offset`/`countTotal`, ttl **null**. Нет строки → `SPACE_NOT_FOUND`.
2. `getList` состава: filter `=revision_id`, `sort` `id` ASC, `limit` 10000, ttl null. `ListResult::rows()`. Пустой состав → `SPACE_INVALID` (не звать IN).
3. `getList` версий: filter `id` => list id (диалект IN, не SQL-строка), ttl null. Склеить **по пунктам состава**: карта `version_id` → ряд, обойти `revision_item`. Нет version в карте → `SPACE_INVALID`. Datetime в ряду — `DateTime` Kernel, в JSON — `toUnix()`.

Попадание: слайс из JSON, без SQL.

Почему не ST `getList`+TTL: тег стола общий на все ревизии. Redis EXPIRE на ключе Space сам выкинет годовалую неиспользуемую ревизию.

План 1: тот же file/redis, что ST, не массив в сервисе как прод. LRU процесса не нужен, если есть store+TTL.

`keep` version из другого спейса того же кластера — норма.

Пропуск identity в новом составе (не `active=false`) — сущности нет в новом мире; старая ревизия не меняется. Tombstone — строка с `active=false` в составе. Оба в плане 1 допустимы.

### 5b. Что ещё ломается, если не учесть

| Риск | Что делать |
|---|---|
| ST ttl на getList состава | Не использовать: тег таблицы общий. |
| Цикл getById на срез | Нет; один ключ Cache. |
| «Навсегда» | TTL 30д + Redis EXPIRE. |
| Безлимит срезов в Redis | Истечение ключа. |
| `UNIQUE_CONSTRAINT` на номер ревизии | §4 шаг 6. |
| `addMany` lastInsertId не подряд при чужом insert | Commit в транзакции; чужой параллельный insert в ту же таблицу — не контракт (план 18). Тест без параллели. |
| Фасад `title`/`body` как универсальный | §3 адаптер фикстуры. |
| Срез без `code` | Identity не в version row; план 4 гидратит `{t}`. |
| Каталог > 10000 | Отказ commit/getList; не чанки в Versioning. |
| Omit vs tombstone | Не путать в тестах: срез 1 не содержит **новый** version с `active=false`. |
| Кэш внутри TX | Только после commit TX. |
| PHP serialize слайса | JSON ассемблер. |
| `IN []` на пустой состав | Не звать getList; существующая ревизия без пунктов — `SPACE_INVALID`. |
| Порядок IN ≠ состав | Склеивать по `revision_item.id`. |
| ST-исключение наружу | Wrap в `SPACE_*`. |
| Фикстура в прод-setup | Реестр инжект; тест сам. |
| Два порта INoteSpaces + IVersionedRepository | Один `IVersionedRepository` в плане 1. |
| `MaxField` без alias | `new MaxField('revision', 'max_revision')`. |
| Кэш write упал после commit | Commit успешен; следующий getRevision — SQL. |
| `getUnique` без filter | `=space_id` и `=revision`. |
| `ITableCatalog::createTable` на PHP-картах | Только `gateway.open(class)->schema()`. |
| `TableCache` / `CacheFailSoft` из ST `Service/` | Свой try/catch на `ICacheStore`; debug → `SPACE_*`, не `CACHE_*`. |
| `add([])` identity | Допустимо: только `IdField`. |
| IN пустой / неполный keep | Не звать `id => []`; неполный список рядов → `SPACE_INVALID`. |
| JSON datetime | `toUnix()`, не `serialize` VO. |
| `getRevision` с номером меньше 1 | `SPACE_INVALID` до SQL. |
| `schema()->dropTable()` | Нет такого метода; `deleteTable()`. |
| Generic walker defineFields | План 1 — note DTO. |
| `ListQuery` в `Service/` | Только `Repository/`. |

### 6. Тесты mysql

Один класс `SpaceMysqlTest` (не раздувать). Схема: create 5 карт в setUp (или транзакция/drop как принято в ST/Chat).

Сценарии:

- два space; commit в A не виден в getRevision B;
- create+commit; getRevision 1; change+commit 2; срез 1 со старым title, срез 2 с новым; те же entityId;
- keep всех + commit → `SPACE_INVALID`;
- вторая ревизия keep+один change — ок;
- `active=false` через change в составе 2; срез 1 без этого tombstone-экземпляра (другой version id);
- нет ревизии 9 → `SPACE_NOT_FOUND`;
- пустой title space / пустой commit → `SPACE_INVALID`;
- unique (space, revision) не обходить с фасада (номер только MAX+1).

Mysql: FileCacheStore в temp; два getRevision — второй без смены данных тот же title (hit). Не обязательный redis.

Unit: trim title; разбор `CommitEntry`; ключ `vs:…`; отказ пустого commit.

### 7. Quality и канон

cs/quality `Versioning/Space`. Commit не один метод на 200 строк: подготовка состава / запись TX / кэш — разные типы, если quality орёт. Фасад и репозиторий ≤10 public. Не phpunit User/Chat.

Канон захода: этот файл; roadmap Versioning; ST 18 уже влит. Architecture/DEC-080 уже есть — не дублировать переименование Vue.

## Todo

- [x] **st-18** — закрыт.
- [x] **cache-1** — `ICacheStore` в дереве.
- [x] **module** — lazy, контейнер, реестр (инжект), autoload, suite `versioning`.
- [x] **fixture** — пять карт в tests/Fixture; mysql create в тесте.
- [x] **facade** — commit/getRevision; JSON-кэш после TX; `SPACE_*`.
- [x] **gates** — phpunit Versioning; cs/quality модуля.

## Не входит

HTTP. Vue. Rule / RuleSpace. Inherit. `code` пространства. `AbilitySection`. Права. Файл ревизии. Never-expire. Чанки 500. `getByIds` на ST. Eager как не-Core. Прод-`getTableClasses`. Override тегов ST.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| Fixture Table | Карта | Часы |
| Registry | class-string кластера | SQL |
| Repository | records + addMany | HTTP |
| `IVersionedRepository` | commit / срез / кэш | Draft |
| Catalog | `open(identity)` | Rule spec |

## Документы захода

этот файл; [`smarttable-plan-18-slice-batch.md`](smarttable-plan-18-slice-batch.md); [`versioning-roadmap.md`](versioning-roadmap.md); [`php-coding-standards.md`](php-coding-standards.md).
