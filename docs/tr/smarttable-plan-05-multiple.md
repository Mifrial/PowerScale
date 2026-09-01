# План 5 — multiple (mfv)

**Статус:** сделано, 2026-09-01. Канон — [`smarttable.md`](smarttable.md) § Multiple и § getList. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Список — [`smarttable-plan-04-getlist.md`](smarttable-plan-04-getlist.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: значение `multiple` — всегда PHP-list; хранение в `{table}_mfv_{field}`; `@` = contains (`S ⊆ M`); `=` без `@` = **равенство множеств** (`S = M`). Публичный фасад `IOpenedTable` не менять. `Service/` **не** резать на подпапки.

## Todo

- [x] **mfv-schema** — нет колонки multiple на основной таблице; DDL sidecar без FK CASCADE; `updateTable` создаёт отсутствующие mfv, даже если скалярных колонок добавлять нечего.
- [x] **mfv-rows** — add/update/delete/get в одной DML-транзакции с mfv; SELECT `get` без имён multiple; assembler: скаляры отдельно от list для replace; update только mfv без пустого `UPDATE`; `hydrateSelected` ветка list.
- [x] **filter** — `@` contains; `=` равенство множеств; `ListMultipleFilter`; binder: убрать отказ `@` на multiple; `sqlColumns` ≠ `hydrateNames`; в SQL добавить `id`, если hydrate multiple.
- [x] **tests-gates** — юнит cast/map; юнит фильтра без MySQL; MySQL CRUD+contains+равенство; skip как ping; cs/quality/phpunit.

## Не входит

`reference` (остаток пункта 5 roadmap), индекс/unique кроме PK sidecar, `forceUpdateTable` / `deleteTable`, TTL/кэш, json/text/html/id как multiple, порядок элементов как API-контракт, `!=` / NOT IN по множеству, fluent `query()`, подпапки `Service/`, `ON DELETE CASCADE`.

Код `FIELD_MULTIPLE_UNSUPPORTED`: sort; операторы кроме `@` и `=`.

## Зачем не вместе с reference

Reference — новый тип, FK на чужую таблицу, restrict/SET NULL. Multiple — sidecar той же таблицы. Разные причины меняться; один заход как планы 3–4.

## Слои

Дробить **по задаче**, не по числу строк quality. Плоский `Service/`.

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| Поле / `FieldSettings` | `Field/` `Dto/` | list: каждый элемент через scalar cast/extract/hydrate; у `StringField` геттер `maxLength()` | SQL, имя mfv-таблицы |
| `SmartTableDefinition` | `Table/` | запрет multiple на недопустимом типе; `string` + multiple и `maxLength() > 255` | CREATE TABLE |
| `MfvSchema` | `Service/` | имя и DDL `{table}_mfv_{field}` | insert значений, filter |
| `MfvRows` | `Service/` | replace/load/delete множеств по `owner_id` | Blueprint основной, JOIN в getList |
| `TableSchema` | `Service/` | колонки **только не-multiple**; делегирует mfv DDL | значения |
| `RowAssembler` | `Service/` | скалярный payload; list для mfv; hydrate list по элементам | SQL |
| `TableRows` | `Service/` | insert/update/delete/get; SELECT основной без multiple | contains / равенство SQL |
| `ListMultipleFilter` | `Service/` | `@` EXISTS и `=` (count + IN / NOT EXISTS) на mfv | загрузка list в select |
| `ListFilterBinder` | `Service/` | multiple → `ListMultipleFilter` **до** ветки IN; переписать `assertContainsPrefix`, не оставить отказ `@`+multiple | SQL SELECT колонок |
| `TableList` | `Service/` | `sqlColumns` без multiple; `hydrateNames` как просил сосед; после страницы догрузить mfv | разбор `@` |

`MfvSchema` / `MfvRows` / `ListMultipleFilter` stateless. `new` в schema/rows/list допустим, **тот же** `IlluminateDatabaseConnection`. Второй адаптер запрещён. Не порт контейнера, не `Interface/`.

Имя класса не `ListContainsFilter`: задача — и contains, и равенство множеств.

`OpenedTable` не получает 5-й dep: mfv за schema/rows/list.

`DriverErrorTranslator` тот же; SELECT/EXISTS без нового кода ошибки.

Имя mfv-таблицы — только `MfvSchema::tableName`. Запрет **типа** multiple — `getMap` / `indexFields`. Лимит VARCHAR — `StringField::maxLength()`, не дублировать 255 в двух местах.

## Типы, которым можно multiple

Индекс `(owner_id, value)` требует индексное `value`. InnoDB: utf8mb4 VARCHAR в PK не длиннее 255.

| Тип | mfv `value` | Multiple |
|---|---|---|
| `string` | VARCHAR, **maxLength 1..255** | да; >255 → `MAP_INVALID` на карте |
| `int` | INT | да |
| `bool` | TINYINT(1) | да |
| `datetime` | INT unix | да |
| `id` | — | нет (`MAP_INVALID` в конструкторе, уже так) |
| `text`, `html`, `json` | — | нет: `getMap` → `MAP_INVALID` |
| `reference` | — | нет, план reference |

`multiple` + `unique`/`indexed` на поле — флаги-мета; DDL индекса плана 6, в этом плане игнор кроме PK sidecar.

## Физическая таблица mfv

Имя: `{getName()}_mfv_{fieldName}`. Не проходит шаблон `[a-z][a-z0-9_]*` или длина > 64 → `MAP_INVALID` в `MfvSchema::tableName`.

Колонки:

- `owner_id` INT NOT NULL. **Без FK CASCADE** (канон: нет автокаскада связанных строк). Целостность: `delete()` сначала `MfvRows::deleteByOwner`, потом строка основной.
- `value` — тип элемента, NOT NULL.

PK: `(owner_id, value)` — дубль в list запрещён. Колонки `sort` нет. Чтение: `ORDER BY value ASC` (стабильно, не «как в add»). Тест get не сравнивает порядок с add.

`createTable`: основная без колонок multiple; затем каждая mfv. `TABLE_EXISTS` только если **основная** уже есть. Teardown: `dropIfExists` сначала все mfv, потом основная.

`updateTable`: **не** `return` при пустом списке недостающих колонок основной. Сначала `ALTER` скалярных колонок (если есть), затем для каждого multiple: нет mfv-таблицы → создать. Имя multiple не искать среди колонок основной. Лишние mfv в БД не удалять.

## cast / extract / hydrate

Снять `assertNotMultiple` с рабочего пути. Multiple:

- вход всегда **list** (`array_is_list`); ассоциативный массив → `FIELD_INVALID`;
- нет ключа, нет default → `[]`;
- default должен быть list (иначе `FIELD_INVALID`);
- `null` как значение поля → как `[]` (не колонка NULL);
- элемент `null` в list → `FIELD_INVALID`;
- дубль после extract → `MAP_INVALID`;
- каждый элемент — существующий scalar `castPresent` / extract / hydrate;
- `required` и итоговый list пуст → `FIELD_REQUIRED` на add/update (не на фильтре `@` / `=`).

`required` для multiple — только PHP. Колонки нет, SQL `NOT NULL` не вешать.

План 4 «`hydrateRow` не менять» для этого захода **не действует**: `hydrateSelected` обязан понимать list.

`hydrateSelected` / `hydrateRow`: если поле `multiple` — в `$databaseRow[$name]` уже list сырых `value` (после догрузки mfv). Каждый элемент — scalar `hydrate`. Не list → `SCHEMA_MISMATCH`. Скаляры как сейчас. Не вызывать `hydrate` на весь массив как на одно значение.

`RowAssembler` на запись — **два выхода**, не один payload:

- скаляры для `INSERT`/`UPDATE` основной: цикл карты **без** multiple и без `id` (insert) / только переданные скаляры (update);
- множества: по каждому multiple на add (и на update, если ключ есть) — тот же `cast`+`extract`, включая нет ключа на add → `[]` / default / `FIELD_REQUIRED`. Этот list уходит в `MfvRows::replace`, не в SQL основной.

Если на add просто `continue` по multiple без `cast` — required и default не сработают.

Пустой list в БД = ноль строк mfv. Нет mfv-таблицы при чтении → `TABLE_MISSING`.

## CRUD

`add` / `update` / `delete` с mfv: скаляры и mfv — **одна** транзакция на соединении, если `transactionLevel() === 0`. Если уровень уже > 0 (`gateway.transaction`) — все DML без нового `begin`. Не вызывать `ISmartTableGateway::transaction` из `TableRows`. Не открывать savepoint. Сбой второго шага не оставляет строку без множества и не оставляет множество без строки.

`update`: ключ multiple в `$values` → полная замена множества. Нет ключа → mfv не трогать. После вычёркивания multiple payload основной может быть пустым: это **не** `MAP_INVALID`. Проверить `exists` (`ROW_NOT_FOUND`), не слать `UPDATE` без колонок, только `replace` mfv. Пустой `$values` с входа по-прежнему `MAP_INVALID`.

`delete`: сначала `MfvRows::deleteByOwner` (все mfv поля карты), затем `DELETE` основной, в той же транзакции. Без CASCADE.

`get`: `select` только скалярные имена. Догрузить mfv в ключи multiple, затем `hydrateRow`.

## Фильтр

`fromOptions` без изменений. Смысл — compiler / binder.

**Порядок в binder:** переписать текущий `assertContainsPrefix` (он бросает `@`+multiple). Сначала: поле multiple → `ListMultipleFilter`; не multiple и `@` → `MAP_INVALID`; иначе прежние IN/скаляр/json. Не оставить старый `throw` рядом с новой веткой.

Иначе `tags => []` станет пустым IN, а `tags => [1, 2]` — `IN` по несуществующей колонке.

| Условие | Поведение |
|---|---|
| `@` + не multiple | `MAP_INVALID` |
| `@` + `null` или `@` + `[]` | `MAP_INVALID` |
| `@` + скаляр | contains одноэлементного множества |
| `@` + не скаляр и не list (assoc, объект) | `FIELD_INVALID` |
| `@` + непустой list | contains: для **каждого** элемента `EXISTS` (`owner_id` + `value`). `S ⊆ M`. Надмножество строки — да. |
| `=` (в т.ч. без префикса) + multiple + `null` | `MAP_INVALID` |
| `=` + multiple + не скаляр и не list | `FIELD_INVALID` |
| `=` + multiple + скаляр | равенство множеств с одноэлементным `{x}` |
| `=` + multiple + list (в т.ч. `[]`) | равенство множеств: `M = S`. Порядок не важен. Дубль в операнде → `MAP_INVALID`. `[]` — `NOT EXISTS` рядов mfv (пустые строки **находит**). Непустое: число рядов mfv = \|S\| **и** все значения ∈ S (или COUNT по `value IN S` = \|S\|). `{1,4,6}` не равно `{1,4}`. |
| sort по multiple | `FIELD_MULTIPLE_UNSUPPORTED` |
| `!=`, `%`, `<`, `>`, `><`, `<=`, `>=` на multiple | `FIELD_MULTIPLE_UNSUPPORTED` |

Не путать с IN скаляра: `id => [1, 2]` по-прежнему `whereIn` на колонке. На multiple тот же синтаксис — **не** IN, а `M = {1, 2}`.

«1 или 2» как contains — OR двух `@`. «Ровно `{1}` или ровно `{2}`» — OR двух `=`.

Операнд: `cast`+`extract` элемента. Неверный тип → `FIELD_INVALID`.

SQL: `whereExists` / коррелированный count, **не JOIN** mfv к странице. Имена — whitelist, backticks.

`countTotal`: тот же WHERE, clone до ORDER/LIMIT.

**Два списка имён (getList):** не один `$columnNames` в `select()` и в `hydrateSelected`.

- `hydrateNames` — то, что просил сосед (`select` или вся карта, **включая** multiple).
- `sqlColumns` — из `hydrateNames` выкинуть multiple.
- Если в `hydrateNames` есть хотя бы одно multiple — в SQL добавить `id`, даже когда соседа `id` не просил (`select => ['tags']`). Иначе не собрать `owner_id IN (...)`. Пустой `select()` без колонок не слать.
- В ответ `id` попадает только если он в `hydrateNames`.

После выборки страницы: в каждую строку драйвера подставить list сырых value по `hydrateNames` ∩ multiple (один `IN (id…)` на поле; нет id / пустая страница — без `IN ()`), затем `hydrateSelected(..., $hydrateNames)`. `get()` одной строки — SQL скаляры (+ `id` всегда есть в карте), hydrate вся карта.

`replace([])` — удалить ряды mfv владельца, не insert пустой строки. Не ждать колонку multiple в результате драйвера.

## Ошибки

Новых кодов нет. `FIELD_MULTIPLE_UNSUPPORTED`: message про оператор/sort, не «ещё не реализовано».

Без секретов соединения.

## Тесты

Только MySQL, skip как ping. Teardown: mfv, затем основная.

Фикстура: `string` multiple (`tags`) + скаляр (`title`); отдельный `int` multiple.

MySQL: `createTable` без колонки `tags`, есть mfv; add `['b','a']` / get = `['a','b']`; add без ключа `tags` при required — `FIELD_REQUIRED`; add без ключа при не-required — get `[]`; update замена; `update` только `tags` без скаляров; delete без сирот mfv; getList полный select — в row есть `tags`; `select => ['tags']` — в row есть `tags`, ключа `id` нет; `select => ['id','title']` — ключа `tags` нет; `@tags => 'a'`; `@tags => ['a','b']` да на `{a,b}` и на `{a,b,c}`; `@tags => ['a','z']` нет; `@tags => []` отказ; `tags => ['a','b']` да только точное множество; `tags => []` находит пустые, не находит непустые; `tags => 'a'` равно `{a}`; OR двух `@`.

Юнит: `text`/`json` multiple → `MAP_INVALID`; string multiple maxLength 256 → `MAP_INVALID`; `@` на скаляре `MAP_INVALID`; sort multiple отказ; дубль в list отказ; `id => []` по-прежнему `MAP_INVALID` (пустой IN, не equality multiple).

Регресс плана 4: IN/json/`<id`. Юнит «`@` на multiple → `FIELD_MULTIPLE_UNSUPPORTED`» заменить на contains / equality (мок `whereExists`). `FieldMapTest`: `cast` multiple — list, не отказ.

`updateTable`: основная уже есть, mfv нет → создаёт sidecar.

Ворота: `cs-check`, `quality`, `phpunit`.

## Следующий заход

Reference: тип `reference`, restrict по умолчанию, без cascade; SET NULL не для required. Multiple+reference — после одиночного reference. Кэш — план 8. Индексы полей — план 6.
