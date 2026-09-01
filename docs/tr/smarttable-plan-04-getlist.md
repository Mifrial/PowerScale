# План 4 — getList

**Статус:** сделано, 2026-09-01. Канон — [`smarttable.md`](smarttable.md) § getList. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). CRUD — [`smarttable-plan-03-crud.md`](smarttable-plan-03-crud.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: список строк с filter / sort / page / select на том же фасаде `IOpenedTable`. Fluent `query()` нет. Кэш (план 8) и contains/mfv (план 5) не писать.

## Todo

- [x] **query-dto** — `ListQuery` / `ListResult` / дерево фильтра в `Dto/`; `fromOptions` только синтаксис; без Illuminate.
- [x] **compiler** — `ListQueryCompiler` в `Service/`: карта + WHERE/ORDER/LIMIT на Builder; не SELECT строк.
- [x] **table-list** — `TableList` выполняет список; `IOpenedTable::getList`; общий переводчик SQLSTATE с `TableRows`; `hydrateSelected`.
- [x] **tests-gates** — юнит синтаксиса и юнит с definition; MySQL включая IN списком; skip как ping; cs/quality/phpunit.

## Не входит

Contains по multiple, JOIN на `{table}_mfv_*`, working `multiple` на add/update, TTL/кэш, `reference`, fluent `query()`, join/group, keyset-сахар сверх `<`/`>` + limit, cursor API, `forceUpdateTable`, **json `IN`** (несколько документов — `LOGIC => OR` и `=`).

Ключа `ttl` в `ListQuery` нет (план 8 добавит явно). Префикса `^` нет.

## Слои

Дробить **по задаче класса**, не потому что «кончились строки quality».

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `ListQuery`, `ListResult`, `FilterGroup`, `FilterCondition` | `Dto/` | опции и дерево фильтра | карта таблицы, SQL, hydrate |
| `ListQueryCompiler` | `Service/` | условия списка → Builder (where/order/limit/offset) | SELECT колонок, COUNT, hydrate, PDO |
| `TableRows` | `Service/` | одна строка: insert/update/delete/get/exists | список, разбор фильтра |
| `TableList` | `Service/` | выборка страницы и optional COUNT | add/update, разбор массива опций |
| переводчик SQLSTATE | `Service/` | throwable драйвера → листья 42S02/42S22/`ROW_WRITE_FAILED` | Builder, фильтр |
| `OpenedTable` | `Service/` | `getList(ListQuery): ListResult` | SQL |
| `RowAssembler` | `Service/` | `hydrateRow` (get, вся карта); `hydrateSelected` (список) | SQL |
| Поле | `Field/` | `cast`/`extract` операнда (кроме `null` на `=`/`!=`) | разбор префикса, IN vs `=` |

`IOpenedTable` не порт контейнера. Новый ключ в `module.config` не нужен.

`SmartTableGatewayPortFactory` собирает `TableList` на **том же** `IlluminateDatabaseConnection`, что schema/rows. Второй `new` адаптера запрещён.

Компилятор stateless: `new` в `TableList` допустим.

Переводчик SQLSTATE — класс в `Service/`, **не** порт и не `Interface/`: два исполнителя SQL (`TableRows`, `TableList`) не копируют `errorInfo`. Новый лист `ROW_READ_FAILED` нет; SELECT тем же маппингом. `OpenedTable` получает `TableList` четвёртым аргументом (лимит 6 deps).

`hydrateRow` для `get` **не менять** (вся карта). Список — `hydrateSelected`: только поля `select`; отсутствующий выбранный ключ → `SCHEMA_MISMATCH`.

Публичных методов у `OpenedTable` станет 7 (лимит 10).

## Контракт

```
IOpenedTable::getList(ListQuery $query): ListResult
```

Не `array $params`. Сосед:

```
ListQuery::fromOptions([
    'filter' => [/* дерево */],
    'sort' => ['id' => 'desc'],
    'limit' => 20,
    'offset' => 0,
    'countTotal' => true,
    'select' => ['id', 'title'],
])
```

Неизвестные ключи верхнего уровня → `MAP_INVALID`.

`ListResult`: `rows(): array<int, array<string, mixed>>`, `total(): ?int` (`null`, если `countTotal` не просили — не `0`).

## Два шага валидации

**`ListQuery::fromOptions` (без definition):** `limit`/`offset`/`countTotal`/`sort` форма; дерево: `LOGIC`, префиксы, шаблон имени поля `[a-z][a-z0-9_]*`, форма `><` (ровно два элемента). Пустой `[]` у `=` / без префикса **не** отвергать здесь: для json это документ, для int — пустой IN. Не проверяет, есть ли поле в таблице.

**`getList` / compiler (с `getMap()`):** поле в карте; `multiple`; оператор × `type()`; `=` + `array_is_list` → IN **только** не-json (**пустой list → `MAP_INVALID`**); json + любой array (в т.ч. `[]`) → равенство документа; `cast`/`extract`; whitelist колонки.

`select` без ключа в options: «все поля» режется по карте в `getList`. В DTO `select === null` значит «вся карта». Пустой массив в options → `MAP_INVALID` уже в `fromOptions`.

## ListQuery

| Ключ | Правило |
|---|---|
| `filter` | нет ключа или `[]` — без WHERE. Иначе дерево. |
| `sort` | `array<string, string>` поле → `asc`/`desc` (регистр не важен, хранить upper). В одном PHP-массиве повтор ключа — как PHP, победит последний. Пусто → в compiler `id ASC`. Имя поля — шаблон; карта — позже. |
| `limit` | обязателен, `int` **1..500**. Нет ключа / 0 / >500 / не int → `MAP_INVALID`. |
| `offset` | нет ключа → `0`; иначе `int` ≥ 0. |
| `countTotal` | нет ключа → `false`; иначе строго `bool`. |
| `select` | нет ключа → `null` (вся карта на `getList`). Пустой массив → `MAP_INVALID`. Дубли в списке → `MAP_INVALID`. Карта — на `getList`. |

Сортировка `multiple === true` → `FIELD_MULTIPLE_UNSUPPORTED` (compiler).

Колонка SQL — имя из карты (план 3: имя поля = колонка), только whitelist.

## Дерево фильтра

Массив диалекта канона, не fluent. Illuminate в DTO нет.

**Группа.** `LOGIC` => `AND` | `OR` (разбор без учёта регистра, хранить upper). Нет `LOGIC` → `AND`. Дети: пары `префикс+поле => значение` или вложенный массив-группа. Пустая группа / только `LOGIC` → `MAP_INVALID`. Иное `LOGIC` → `MAP_INVALID`.

Два условия по одному полю в одном массиве с одним ключом — PHP перезапишет; для AND двух `=` — вложенные группы.

**Условие.** Ключ = префикс + имя поля. Самый длинный префикс первым: `><`, `<=`, `>=`, `!=`, затем `@`, `=`, `%`, `<`, `>`. Без префикса в дереве хранить как `=`.

| Префикс | Смысл | Значение |
|---|---|---|
| *(нет)* / `=` | равенство; `null` → `IS NULL`. **Не-json** + непустой `array_is_list` → SQL `IN` (как Bitrix `ID => [1, 2]`). Один скаляр — `=`. Не путать с `><`: `id => [10, 20]` это IN, интервал только префикс `><`. | Пустой `[]` на не-json → `MAP_INVALID` (**compiler**). `null` в list → `MAP_INVALID`. List у `!=` / `%` / `<` / `>` / `<=` / `>=` → `MAP_INVALID` (NOT IN в v1 нет). |
| `><` | `BETWEEN ? AND ?` включительно | ровно два элемента `[min, max]`; min > max после extract → `MAP_INVALID`. |
| `<=` `>=` `!=` | сравнение | скаляр; `null` только у `!=` |
| `%` | `LIKE`, шаблон **как есть** (потребитель ставит `%`). `%`/`_` не экранируем. | только string/text/html |
| `<` `>` | сравнение | скаляр, не `null` |
| `@` | **не IN.** Бронь канона: contains на multiple (план 5). | В этом плане всегда отказ: поле `multiple` → `FIELD_MULTIPLE_UNSUPPORTED`; иначе `MAP_INVALID`. |

Неизвестный префикс (`~title`, `^id`) → `MAP_INVALID`.

**IN vs contains.** IN (скаляр): ячейка `x` ∈ набор (ИЛИ). Contains (план 5): набор ⊆ список строки (И). Не подменять одно другим. Несколько json-документов: не IN, а `LOGIC => OR` и `=`.

Примеры:

```
['=title' => 'a', '>id' => 1]
['LOGIC' => 'OR', ['=title' => 'a'], ['=title' => 'b']]
['id' => [1, 2, 3]]          // IN
['=id' => [1, 2, 3]]         // то же IN
['id' => 5]                  // =
['><age' => [10, 20]]
['%title' => '%hel%']
['!=active' => true]
['=payload' => ['a' => 1]]   // json документ
['=payload' => []]           // json [] как документ
['=payload' => [1, 2]]       // json-массив как документ, не IN(1,2)
['LOGIC' => 'OR',            // два json-документа
  ['=payload' => ['a' => 1]],
  ['=payload' => ['b' => 2]],
]
```

Лента чата: `['<id' => $lastId]`, `sort: ['id' => 'desc']`, `limit`. Cursor API нет. `IdField` min=1: `'<id' => 0` → `FIELD_INVALID` (тот же range, что add). Курсор — `lastId >= 1`.

## Операнды и типы

Ненулевой операнд: **`cast(..., true)` + `extract`**, включая min/max. IN — каждый элемент списка. `><` — оба конца. Неверный тип → `FIELD_INVALID`.

**`null`:** только `=` / `!=`. **Не** вызывать `cast` (required дал бы `FIELD_REQUIRED`). Сразу `IS NULL` / `IS NOT NULL`. `>id => null` → `MAP_INVALID`. Пустая строка — обычный `cast`.

| Поле | Разрешено | Запрет (`MAP_INVALID`) |
|---|---|---|
| string, text, html | все кроме `@`; list → IN | `@` |
| int, id, datetime | `=` `!=` `<` `>` `<=` `>=` `><`; list → IN | `%` `@` |
| bool | `=` `!=` | `%` `@` `><` `<` `>` list/IN |
| json | `=` `!=` (array/скаляр = документ) | `%` `@` `><` `<` `>` `<=` `>=` **IN** (list не превращать в IN) |
| multiple true | — | любое условие → `FIELD_MULTIPLE_UNSUPPORTED` |

`datetime`: объект ядра, как add; int unix на фильтре отказ. В SQL — unix после extract.

`json`: encode в compiler **теми же флагами**, что `TableRows::encodeJsonColumns` (`JSON_THROW_ON_ERROR`), не в поле.

`bool`: в SQL 0/1 после extract.

## SQL (`TableList` + compiler)

`TableList`: `select($columnNames)` из запроса (имена карты), не `*`. Compiler вешает where/order/offset/limit на тот же билдер. Не-json IN → `whereIn`.

Группа **AND**: дети через `where` / вложенный `where(function)`. Группа **OR**: дети через `orWhere` внутри **одной** `where(function)`, иначе получится `(a) OR (b AND c)`.

`countTotal === true`: второй запрос `count()` с тем же WHERE; клон билдера **после** where, **до** order/limit/offset. Не считать total по длине страницы.

Строки коллекции: объект → `get_object_vars`, не массив → `SCHEMA_MISMATCH`, затем `hydrateSelected`.

Пустой список: `rows = []`; `total` 0 только если считали COUNT. Не `ROW_NOT_FOUND`.

Нет таблицы → `TABLE_MISSING`. Нет колонки → `SCHEMA_MISMATCH`.

Два запроса (page + count) без общей транзакции — v1 допустимо.

## Ошибки

Новых кодов нет: `MAP_INVALID`, `FIELD_INVALID`, `FIELD_REQUIRED` (ненулевой операнд required-поля, не `null`-фильтр), `FIELD_MULTIPLE_UNSUPPORTED`, `TABLE_MISSING`, `SCHEMA_MISMATCH`, `ROW_WRITE_FAILED`.

Без секретов соединения в message.

## Тесты

Только MySQL, skip как ping. Фикстура — `CrudProbeTable`. `dropIfExists` в teardown. Для sort/filter multiple — отдельное definition с `multiple true` (без add таких строк).

MySQL: `=` / `!=` / `id => [1,2,3]` IN / `%` / `<` `>` (старше id) / `><` / AND / OR-группа; sort desc; offset+limit; `countTotal`; `select` без лишнего поля; json `=` документа и json-массива как документа (не IN); два json через OR; datetime/bool; `=required => null` как IS NULL (не `FIELD_REQUIRED`); пустой список; нет таблицы.

Юнит без БД, **синтаксис** (`fromOptions`): нет `limit`; 0 и 501; unknown prefix; `><` не пара; `LOGIC` мусор. Пустой `[]` у `=` здесь **валиден**.

Юнит **с definition**: unknown field; `%` на int; list на json остаётся `=`; `payload => []` — `=`, не IN; `id => []` — `MAP_INVALID`; list на int → IN; `active => [true]` отказ; sort multiple; `@` на скаляре `MAP_INVALID`; `@` на multiple `FIELD_MULTIPLE_UNSUPPORTED`.

Ворота: `cs-check`, `quality`, `phpunit`.

## Следующий заход

План 5 — mfv; `@` на multiple = contains (`S ⊆ M`), не IN. Sort multiple — исключение. Кэш — план 8.
