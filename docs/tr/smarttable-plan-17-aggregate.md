# План 17 — агрегат своей таблицы

**Статус:** сделано, 2026-09-04. Канон — [`smarttable.md`](smarttable.md) § getList. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Путь reference — [`smarttable-plan-14-reference-path.md`](smarttable-plan-14-reference-path.md) (**JOIN в FROM по-прежнему нет**). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Потребитель — Chat 5 ([`chat-roadmap.md`](chat-roadmap.md)). User `getCountsByGroupIds` — через этот API: [`user-plan-08-member-count-aggregate.md`](user-plan-08-member-count-aggregate.md) (**сделано**); в [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md) это хвост, не текущий код.

Цель: пачка `COUNT` / `MAX` / `MIN` / `SUM` по **своей** таблице с `GROUP BY` своих колонок. Фильтр — тот же диалект, что `getList` (включая путь). Порог с **другой** карты — `SubqueryValue` в операнде (`last_read` членства vs `id` сообщения), не JOIN в FROM и не страницы по 500 в PHP.

Не fluent `query()`. Не полный Bitrix JOIN. Не Chat 5 (visibility-колонка, `unreadCount` в JSON). Не переписывать User в этом PR.

## Зачем

`countTotal` на `getList` — **одно** число на весь WHERE. До этого API пачка групп шла `getList` страницами по 500 и `++` в PHP. Это не «пока нет GROUP BY», а дыра: на ленте сообщений те же 500 режут unread. User COUNT — план 8; собирать **строки** членств (`getGroupIdsByUserIds`) по-прежнему страницами — ST не отдаёт массив id в одном агрегате.

Chat 5: `unread` = число сообщений чата с `id > last_read_message_id` актора (скрытые — фильтр Chat, не ST). `last_read` живёт на `chat_member`, сообщения — на `chat_message`. Разный порог на чат. Preview — `MAX(id)` (или `MAX(created_at)`) среди видимых, потом догрузка строк. Оба запроса — агрегат, не N× `getList`.

План 14: **FROM списка = своя таблица**, JOIN размножает 1:N. Закон остаётся. Порог с другой карты — подзапрос в операнде, SELECT снаружи по-прежнему FROM нас.

## Термины

| Термин | Смысл |
|---|---|
| Агрегат | `GROUP BY` своих колонок + агрегатные функции; ряд результата **не** строка карты. |
| Агрегатная функция | Слот `select` агрегата, не колонка карты: `CountField` / `MaxField` / `MinField` / `SumField`. |
| Подзапрос | Скаляр другой карты в операнде фильтра: `SubqueryValue`. Колонка внешней FROM во внутреннем WHERE — `OuterColumn`. |
| Промах группы | `GROUP BY` не возвращает ключ без подходящих строк. Нуль дописывает репозиторий по списку id, не ST. |

## Решения

### 1. Не `getList`

`getList` гидратит строки карты (`id`, поля, mfv, пути). `GROUP BY` ломает «ряд = сущность»: нет `id` строки, `count` не поле карты, `offset` страницы сущностей путается со страницами групп.

Новый метод на `IOpenedRecords` (сейчас 7 public → 8, лимит 10):

`aggregate(AggregateQuery $query, ?int $cacheTtl = null): AggregateResult`

Не седьмой слот `ListQuery`. Не `select: ['COUNT(*)']` в getList. `getUnique` / `getFirst` / `getById` без агрегата.

`IOpenedSchema` не трогать. Словарь: тот же `records()` у `openByName`.

### 2. Фильтр — уже общий тип, не `QueryFilter`

Дерево фильтра **уже класс**: `FilterGroup` / `FilterCondition` / `FilterTreeParser`. `ListQuery` держит `?FilterGroup`. `AggregateQuery` — тот же слот, тот же диалект массива (`'=title' => 'a'` короче классов).

Новый `QueryFilter` не заводить: это второе имя `FilterGroup` (обёртка без своей оси — php-coding-standards). Не переименовывать `FilterGroup` в этом заходе.

DRY, который нужен:

- `FilterTreeParser::parseOptional(mixed): ?FilterGroup` — `null` / `[]` → нет WHERE; иначе `parseGroup`. Сейчас это 10 строк внутри `ListQueryOptionsParser`; `ListQuery` и `AggregateQuery` зовут **то же**. Внутренний `$filter` подзапроса — `parseGroup` (пустой массив → `MAP_INVALID` сразу в ctor, не «нет WHERE»).
- Компилятор: `applyFilter(Builder, ?FilterGroup, Definition $local, ?Definition $outer = null)`, `applyWhere(ListQuery)` делегирует без outer. Не собирать фейковый `ListQuery` ради WHERE. `$outer` — только компиляция внутреннего WHERE подзапроса (§5).

`fromOptions` у ListQuery и AggregateQuery остаётся входом соседа. Репозиторий по-прежнему не клеит `FilterGroup` руками, если нет причины.

### 3. `AggregateQuery`

Свой DTO, `fromOptions`. Слоты:

| Слот | Обязателен | Смысл |
|---|---|---|
| `filter` | нет | То же дерево, что ListQuery (`FilterGroup`; пути, `@`, IN, `><`, …). |
| `group` | да | `array<int, string>` своих скалярных имён, ≥1. Порядок = `GROUP BY`. |
| `select` | да | Ключи групп (`string`) **и** агрегатные функции. Порядок = SELECT / ключи ряда. |
| `limit` | да | 1…500 — число **групп** в ответе, не строк карты. |

Нет `offset`, нет `countTotal`, нет `sort` в v1 (порядок групп — порядок `group` ASC). Понадобится страница групп — отдельный заход.

`MAP_INVALID`: пустой `group`; имя не своей карты; путь (точка) в `group`; multiple / json / text / html в `group`; дубль имени в `group`; `select` без всех ключей `group`; строка в `select` не из `group`; дубль строки или alias в `select`; нет ни одной меры; функция не из allowlist; массив/`fn` в `select`; `limit` вне 1…500; лишний ключ опций; `offset`/`countTotal`/`sort` если передали.

Allowlist `group` по `type()`: `reference` / `int` / `bigint` / `bool` / `datetime` / `string`. Поле `id` входит как `int`/`bigint` (`IdField::type()` — не `'id'`; не писать `type() === 'id'`).

Порядок групп в ответе — `group` ASC (несколько ключей — слева направо). Компилятор ставит `ORDER BY` этих колонок ASC: в MySQL 8 `GROUP BY` сам не сортирует, без ORDER страница из 500 нестабильна. Больше 500 групп — страница из 500, как getList, без `total`. SQL NULL в ключе группы (nullable колонка) → PHP `null`, группу не выкидывать.

### 4. Агрегатные функции — классы, не `['fn' => …]`

Строка в `select` — имя колонки карты (как в getList). Агрегат в том же списке — **не** колонка и не сахар `['fn' => 'count']`. Класс — существительное, тот же суффикс, что у карты: `CountField`, как `StringField`. Не с глагола (`AggregateCount`, `Count`).

Не `CountExpressionField` и не общий Bitrix `ExpressionField`: там имя + произвольная SQL-строка (`DEC-078`). У нас закрытый вид (`COUNT` / `MAX` / `MIN` / `SUM`), SQL клеит компилятор.

Это **не** поле карты: живут в `Dto/`, не в `Field/`; не наследуют `BaseField`; не в `defineFields()`. Сосед (Chat/User `Repository/`) импортирует их как `ListQuery`: `Dto/` и `IOpenedRecords` — публичный контракт шлюза, не внутренности (`Service/Query/`, Illuminate). Фасад / `Service/` соседа — нет. Порт-фабрика `newCountField()` не заводить (KISS: интерфейс ради одного `new`).

```php
'select' => [
    'chat_id',
    new CountField('unread'),
    new MaxField('id', 'last_id'),
]
```

| Тип | Конструктор | SQL | PHP в ряду |
|---|---|---|---|
| `CountField` | `new CountField(alias)` | `COUNT(*)` | `int` (`(int)` из драйвера, не гидратор поля) |
| `MaxField` / `MinField` | `new MaxField(field, alias)` | `MAX/MIN(col)` | гидратор поля-аргумента или `null` |
| `SumField` | `new SumField(field, alias)` | `SUM(col)` | `int\|null`; не гидратор поля |

`COUNT(col)` в v1 нет (Chat/User не просят; иначе второй аргумент `CountField` — отдельный заход). Статиков `all` / `of` нет: класс — существительное, создание — `new`.

Четыре `final` в `Dto/`, без общего интерфейса «на будущее» и без абстрактного Runtime. Компилятор ветвит по `instanceof`. Элемент `select` — `string` или один из четырёх; массив / неизвестный объект → `MAP_INVALID`.

Скаляр аргумента по `type()`: Max/Min — `int` / `bigint` / `bool` / `datetime` / `reference`; `string` с `maxLength` ≤ 255. Поле `id` — как int/bigint. `SumField` — только `int` / `bigint` / `reference` (не bool, не datetime, не string). `text` / `html` / `json` / multiple → `MAP_INVALID`.

MySQL `SUM(INT)` отдаёт DECIMAL (`"15"` / `"15.0000"`). Гидратор `IntField` режет min/max колонки и не принимает дробную строку — сумму так не гидратить (сумма шире поля). Компилятор: `CAST(SUM(col) AS SIGNED)` (bigint — `SIGNED` тот же signed 64), PHP `int`; все NULL → SQL NULL → PHP `null`. `COUNT(*)` — `(int)`, не поле карты.

`alias`: `[a-z][a-z0-9_]*`, не колонка карты, уникален в `select`. Ключи `group` в результате — как в карте, без alias.

Гидратация ряда: ключи `group` — гидратор поля карты (как select getList; SQL NULL → PHP `null`). `CountField` — `int` (`COUNT(*)` у вернувшейся группы не NULL). Max/Min — гидратор аргумента; Sum — как абзац выше. SQL `NULL` меры → PHP `null`, не выкидывать группу.

`AggregateResult::rows(): array<int, array<string, mixed>>` — только ключи из `select`, порядок как в `select`. Не `ListResult` (нет `total`). Пустой набор групп → `[]`, не ошибка.

Нули для «группы из IN, но 0 членов»: репозиторий, как сейчас `zeroCounts`. ST не дописывает.

### 5. FROM = мы; подзапрос в операнде

JOIN в FROM **нет**, в том числе «безопасный N:1 к членству». Один закон с планом 14.

Операнд фильтра (`>`, `<`, `>=`, `<=`, `=`, `!=`) — `SubqueryValue`, не карта и не список IN. Имя — существительное (значение-подзапрос), не глагол Correlate и не жаргон Scalar. Не `LookupValue` (похоже на getById). Не `ForeignValue` (путается с `reference`). Не `*Field` (это не слот select и не колонка карты).

```php
'>id' => new SubqueryValue(
    ChatMemberTable::class,
    'last_read_message_id',
    filter: [
        'chat_id' => new OuterColumn('chat_id'),
        'user_id' => $actorId,
    ],
    coalesce: 0,
),
```

Сигнатура: `__construct(string $table, string $field, array $filter, mixed $coalesce = null)`. Пример соседа — named arguments: слот фильтра читается как `'filter' =>` у `fromOptions`, не как безымянная карта корреляции. Не `FilterGroup` на слоте и не `QueryFilter`: иначе в одном unread два входа в одно дерево (снаружи массив, внутри класс).

`$filter` — массив диалекта, разбор `parseGroup` в конструкторе (как сосед пишет; не второй `fromOptions` на весь запрос). Пустой массив → `MAP_INVALID`. `FilterGroup` сосед руками не клеит.

Правила:

- Первый аргумент — class-string `SmartTableDefinition` **или** физ. имя словаря. Class-string → `new $class()` (как цель `ReferenceField` с классом). Имя → тот же `CatalogDefinitionLookup`, что hop плана 14. Не второй lookup и не новый каталог. Сейчас hop так не зовут — только цель FK; метод «карта по class-string или имени» рядом с walker. Chat не импортирует чужой `Table/`: подзапрос к своему `ChatMemberTable`. В ключе кэша — как сосед написал (class-string и имя — два слота).
- Второй — **одно** скалярное поле той карты (не агрегатная функция, не `*`, не multiple). `json` / `text` / `html` как select подзапроса → `MAP_INVALID`. Тип select vs внешнее поле / OuterColumn не сверяем (как ширину байтами): кривое сравнение — на соседе или `ROW_WRITE_FAILED`.
- Внутренний filter: только **свои** поля той карты (не путь / не точка в ключе, не вложенный `SubqueryValue`). Значение: скаляр **или** `new OuterColumn('field')` — колонка **внешней** FROM (имя без точки, есть на внешней карте).
- Внутренний filter обязателен (≥1 условие). Иначе подзапрос без WHERE — запрещён.
- Подзапрос скалярный: компилятор `LIMIT 1` не ставит; уникальность даёт unique карты (членство Chat/User). Две строки → MySQL `Subquery returns more than 1 row` → `ROW_WRITE_FAILED` (тот же переводчик SELECT, что getList).
- Четвёртый аргумент `coalesce`: нет / `null` — SQL как есть (`id > NULL` → строка не входит). Иначе — `cast` поля select внутренней карты (как операнд листа). Не тот тип → `FieldInvalid`. `NULL` last_read у члена → без coalesce все сообщения выпадут из `id > …`; Chat 5 передаёт `0`.
- Подзапрос только на **своём** поле внешней карты (ключ без точки). Путь + `SubqueryValue` → `MAP_INVALID`.
- Ветка `SubqueryValue` / `OuterColumn` в биндере **до** `cast` (`ListScalarFilter::sqlValue` иначе сломает гидратор). В `applyCondition` операнд-`SubqueryValue` **раньше** ветки пути (точка): иначе `ListPathFilter` уйдёт в тот же `cast`.
- `applyFilter(Builder, ?FilterGroup, Definition $local, ?Definition $outer = null)`: внешний WHERE — `$outer = null`; внутренний WHERE подзапроса — `$local` = внутренняя карта, `$outer` = внешняя FROM. Иначе `OuterColumn('chat_id')` ищется как поле внутренней карты / уходит в `cast`. `OuterColumn` без `$outer` → `MAP_INVALID`. Вложенный `SubqueryValue` (`$outer !== null`) → `MAP_INVALID`. `applyWhere(ListQuery)` делегирует `applyFilter` без outer.
- Не в `sort`/`select` getList, не в аргументе `CountField`/`MaxField`/…, не в `IN` / `><` / `@` / `%`.
- Неизвестная таблица / нет поля `select` / нет имени `OuterColumn` → `MAP_INVALID`.
- `OuterColumn` в SQL — `{внешний_стол}.{поле}` (физ. имя внешней карты, как `from` Illuminate). Не голая колонка: иначе столкнётся с одноимённым полем внутренней карты.
- Карта `['correlate' => …]` / `['outer' => …]` → `MAP_INVALID` (не второй синтаксис).

`FilterTreeParser`: операнд — PHP-скаляр / `null` / `DateTime` ядра / list (IN/`><`/json) / `SubqueryValue` / `OuterColumn`. **`DateTime` — не «иной объект»**: иначе datetime-фильтры getList умрут на разборе. Иной объект → `MAP_INVALID`. Контекст «OuterColumn только внутри подзапроса» — компилятор, не парсер.

SQL (схема):

```sql
SELECT chat_id, COUNT(*) AS unread
FROM chat_message
WHERE chat_id IN (…)
  AND id > COALESCE((
    SELECT last_read_message_id FROM chat_member
    WHERE chat_member.chat_id = chat_message.chat_id AND user_id = ?
  ), 0)
GROUP BY chat_id
ORDER BY chat_id ASC
LIMIT 500
```

Репозиторий Chat **не** пишет этот SQL.

### 6. Кэш

Без TTL — кэша нет.

С TTL текущий код **поломает** и подзапрос, и меры, если оставить `ListCacheKey` / `ListCacheFieldTags` как для одних строк-имён.

**Ключ.** Канон объектов (вид + поле + alias; подзапрос: стол, select, внутренний filter, coalesce). Иначе `json_encode` → `{}` / `CACHE_CONFIG_INVALID`. В каноне filter `DateTime` — unix int: у `DateTime` нет public-свойств, сейчас объект в `ListCacheKey` становится `{}` и разные даты свалятся (дыра getList; чинить общим walker list+aggregate). Префикс/маркер вида `list` vs `aggregate`, чтобы ключи не пересеклись. `CachePayload::decode` сейчас `allowed_classes` = `ListResult` + `DateTime`: без `AggregateResult` слот агрегата всегда промах или мусор. Дописать `AggregateResult`.

**Теги.** Закон плана 8 не менять: `update` бьёт `st:{table}:{field}`, не стол; `add`/`delete` бьют `st:{table}`. `storeTags` уже вешает тег **своего** стола.

Меры — не alias в тег. Тегировать колонки SQL:

| Что в запросе | Теги своей карты |
|---|---|
| `group` / строки в `select` | эти поля |
| filter | как getList, плюс обход `SubqueryValue` |
| `CountField` (`COUNT(*)`) | нет лишнего поля. Update `content` hit. Не вся карта как `select: null`. |
| `MaxField` / `MinField` / `SumField` | поле-аргумент |

Чужой стол — пары `table:field` физ. имени **внутренней** карты (select + поля внутреннего filter), тот же формат что путь плана 14. `storeTags` уже вешает `st:{чужой стол}` при первой такой паре. Голое `last_read_message_id` повесило бы тег **внешней** карты. `OuterColumn` тегирует поле **внешней** карты (часто уже есть в `group`/filter). Иначе `markRead` не собьёт unread.

**Слот запроса, не близнец list/aggregate.** У `TableCache` два разных чтения: строка по id (`lookupGet` / `saveGet`, ключ `{table}:get:{id}`, тег `:rows`) и **результат запроса с тегами полей**. Агрегат — второй вид, не третий: тот же OR-тег, тот же skip в tx, тот же fail-soft. Отличается только канон ключа и тип payload.

Поэтому не `lookupAggregate` рядом с `lookupList` (копия проводки) и не `ListQuery|AggregateQuery` в `TableCache` (`instanceof` диалекта в кэше). `TableCache` не знает `ListQuery`: принимает готовый ключ и список тегов полей.

Заменить `lookupList` / `saveList` на:

- `lookupTagged(string $cacheKey): CacheHit`
- `saveTagged(string $tableName, string $cacheKey, mixed $value, int $cacheTtl, array $fieldTags): void`

`storeTags` остаётся внутри save. Канон ключа и список тегов — на стороне `OpenedRecords` (сейчас `listFieldTags` → `TableList::cacheFieldTags`). Общий обход `FilterGroup` (пути + `SubqueryValue` → пары чужого стола) + для агрегата `group` и аргументы Max/Min/Sum; не тащить агрегат в `TableList`. Теги агрегата — `TableAggregate::cacheFieldTags` (как `TableList::cacheFieldTags`). Ключ — `new` хелпера в методе (`ListCacheKey` / общий walker DateTime→unix). **Не 7-й dep** `OpenedRecords`: после `TableAggregate` конструктор уже 6 = потолок phpcs. `TableCache` ключ не клеит. Транзакция: skip read/write при `transactionLevel() > 0`.

Число public у `TableCache` после замены то же; лимит phpcs — не причина выбора.

Сборка: `OpenedTable::bind` и `SmartTableGateway` / `SmartTableCatalog` / `CatalogDictionary` получают `TableAggregate` (Gateway и Catalog — 6-й dep, потолок phpcs; Dictionary — 5-й). `SmartTableSupport` клеит его рядом с `TableList` на том же walker/компиляторе.

Дописать в этом заходе:

- канон объектов в ключ; allowlist payload;
- теги: filter (с подзапросом) + group + аргументы Max/Min/Sum; не alias;
- тесты: CountField + update поля вне filter/group → hit, add → miss; MaxField + update аргумента → miss; подзапрос + update чужой карты → miss; hit агрегата после TTL (DateTime в ряду MAX).

`debug` / отвал драйвера — тот же закон плана 8.

### 7. Слои и quality

Новый `TableAggregate` в `Service/Query/`. Не класть GROUP BY в `TableList` (там уже один сценарий страницы + ignore complexity): это вторая причина меняться, не «закончились deps». `OpenedRecords` уже режет CRUD (`TableRows`) и список (`TableList`); агрегат — третий исполнитель, 6-й аргумент. Не 7-й «на всякий случай». Лимит phpcs 6 — совпадение, не причина выноса.

Тот же `FieldPathWalker` / каталог, что у getList (путь и физ. имя подзапроса).

`IOpenedRecords` 7 public → 8. Исключения `aggregate` — как `getList` (`MAP_INVALID`, `FIELD_*`, `TABLE_MISSING`, `SCHEMA_MISMATCH`, `ROW_WRITE_FAILED`) плюс TTL ≤ 0.

WHERE — `applyFilter` из §2 / §5 (`$outer` у подзапроса). Подзапрос SQL в `Service/Query/`, не `Utils/`.

`OpenedRecords::aggregate` — кэш как getList (в т.ч. skip в tx), затем `TableAggregate`.

Фикстуры mysql — свои definition в тесте ST, не карты Chat/User. Сценарии: COUNT по `group_id`; MAX(id); MAX datetime → `DateTime`; промах ключа; подзапрос + coalesce 0 в aggregate и getList; `OuterColumn` во внешнем filter → `MAP_INVALID`; две строки подзапроса → `ROW_WRITE_FAILED`; `getList` `CountField` в select отвергает; TTL как §6.

User `getCountsByGroupIds` в этом файле не трогать. Хвост — [`user-plan-08-member-count-aggregate.md`](user-plan-08-member-count-aggregate.md) (**сделано**). Chat 5 — после этого файла. Не блокирует план 11.

## Todo

- [x] **dto** — `FilterTreeParser::parseOptional`; `AggregateQuery` / `AggregateResult`; `CountField` / `MaxField` / `MinField` / `SumField`; `SubqueryValue` / `OuterColumn`; `fromOptions`; отказы `MAP_INVALID`.
- [x] **group** — `TableAggregate`: GROUP BY + `ORDER BY` group ASC + `CountField`/`MaxField`/`MinField`/`SumField`; `CAST(SUM…)`; FROM своя; `IOpenedRecords::aggregate`; mysql без подзапроса.
- [x] **subquery** — подзапрос + `OuterColumn` + optional coalesce (cast внутреннего поля); mysql unread-схема (две таблицы фикстуры); дубль строк подзапроса → write failed; `OuterColumn` без outer-контекста → `MAP_INVALID`; путь + `SubqueryValue` → `MAP_INVALID`.
- [x] **cache** — канон ключа (вид list/aggregate, DateTime→unix, объекты мер/подзапроса); `lookupTagged`/`saveTagged`; `CachePayload` + `AggregateResult`; теги §6; skip в tx; mysql hit/miss как §6. TableCacheTest без `ListQuery`.
- [x] **canon** — сверка [`smarttable.md`](smarttable.md) § агрегат/кэш; roadmap 17; [`TR.md`](TR.md) (не «коррелят»).
- [x] **gates** — cs-check SmartTable, quality, phpunit suite `smarttable`. Не phpunit Chat/User.

## Не входит

JOIN в FROM. Fluent `query()`. HAVING. `offset` / `sort` групп. `CountField`/`MaxField`/… в `getList`. `SubqueryValue` в sort/select, в аргументе меры и на ключе-пути. `COUNT(col)` / `COUNT(DISTINCT)`. Агрегат без меры (DISTINCT через GROUP BY). Агрегат по mfv. Составной неуникальный INDEX. Visibility / `see_all` / unread JSON. Перепись `getCountsByGroupIds`. Versioned. Bitrix `ExpressionField` / `CountExpressionField` / произвольный SQL. Второй синтаксис массивами. Класс `QueryFilter`. Порт-фабрика `newCountField`. `lookupAggregate` рядом с `lookupList`. `TableCache` знает `ListQuery`/`AggregateQuery`. `CorrelateScalar`. `AggregateCount`. Голый `Count`. `CountMeasure`. `CountField` в `Field/` или как `BaseField`. `type() === 'id'`. Гидратор `IntField` на `SUM`.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `AggregateQuery` | `Dto/` | слоты, `fromOptions` | SQL |
| `AggregateResult` | `Dto/` | `rows()` | total |
| `CountField` / `MaxField` / `MinField` / `SumField` | `Dto/` | вид + alias | колонка карты, SQL-строка |
| `SubqueryValue` / `OuterColumn` | `Dto/` | подзапрос / колонка внешней FROM | JOIN |
| `FilterGroup` | `Dto/` | общее дерево (уже есть) | новый тип QueryFilter |
| `TableAggregate` | `Service/Query/` | GROUP BY + ORDER BY group + подзапрос; `cacheFieldTags` агрегата | гидрация mfv |
| `IOpenedRecords` | `Interface/Service/` | `aggregate` | schema |
| `OpenedRecords` | `Service/` | TTL; ключ (`new` хелпера); теги через List/Aggregate; 6-й dep = `TableAggregate` | DDL, SQL, 7-й dep |
| `TableCache` | `Service/Cache/` | get по id; слот запроса по ключу+тегам | диалект ListQuery/AggregateQuery |
| `CachePayload` | `Service/Cache/` | allowlist `ListResult` + `AggregateResult` + `DateTime` | SQL |

## Документы захода

этот файл; [`smarttable.md`](smarttable.md); [`smarttable-roadmap.md`](smarttable-roadmap.md); [`smarttable-plan-14-reference-path.md`](smarttable-plan-14-reference-path.md); [`smarttable-plan-04-getlist.md`](smarttable-plan-04-getlist.md); [`smarttable-plan-08-cache.md`](smarttable-plan-08-cache.md); [`chat-roadmap.md`](chat-roadmap.md) шаг 5; [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md) (антипаттерн, не код); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md).

## Следующий заход

План Chat 5 (visibility / unread / preview) на этом API. User `getCountsByGroupIds` — [`user-plan-08-member-count-aggregate.md`](user-plan-08-member-count-aggregate.md) (**сделано**).
