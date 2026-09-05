# План 18 — срез до 10k и addMany

**Статус:** план, 2026-09-05. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Потребитель — [`versioning-plan-01.md`](versioning-plan-01.md) (состав ревизии ~3k). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один `getList` может отдать **весь** состав ревизии; один `addMany` — записать состав. **Не** поднимать 500 у `aggregate`. **Не** новый SQL-диалект и не `getByIds` как второй SELECT: `IN` уже есть.

Блокер Versioning 1: не писать чанки по 500 в фасаде спейса.

## Зачем

`getList` / `aggregate` сейчас **1..500**. Страница UI и unread-группы — ок. Срез правил ~3k и `getList` по `revision_id` (не только `IN` по id) — нет: это не `getByIds`, это все строки состава.

Глобально отдать админке 10k по ошибке — риск, но лимит **обязан быть ≥ каталога**. HTTP по-прежнему передаёт маленький `limit` (Chat/User не менять). Меняется только потолок `ListQuery`, не дефолт страницы.

`add` × 3k в commit — roundtrip. `addMany` — один multi-insert, те же проверки.

## Решения

### 1. Потолок `getList` = 10000

Один потолок в **`ListQuery` ctor и parser** (сейчас оба 1..500). Сообщение `1..10000`. Константа на `ListQuery` (как TTL на `ICacheStore`), parser не дублирует магическое число.

`AggregateQuery` **остаётся 1..500** (группы unread, не строки каталога). `getUnique`/`getFirst` по-прежнему подставляют limit 2/1 — в новом потолке лежат.

Словарь `CatalogDictionary` `limit => 500` — размер страницы, **не** трогать.

HTTP User/Chat (`limit` 1…500, тест `501` на `getMembers`) **не** менять: это страница API, не max ST.

Канон [`smarttable.md`](smarttable.md) уже пишет 10000 и `addMany`; этот заход **догоняет код**, не переписывает канон заново.

Тесты: `ListQuery::fromOptions(['limit' => 501])` ок; `10001` и ctor `500` ок / `10001` отказ. Mysql на 10k строк **не** гонять. Aggregate 501 по-прежнему отказ.

### 2. Не метод `getByIds`

Пустой `IN []` по-прежнему `MAP_INVALID`. Пустой состав Versioning **не** зовёт getList. Недостающие id в `IN` — просто нет строк, не ошибка.

Порядок среза задаёт Versioning (состав), не ST.

### 3. `addMany`

`IOpenedRecords` сейчас **8** public (`add`…`aggregate`) → **9** (лимит 10).

`addMany(array $rows): array` — `@return array<int, int>` новые id **в порядке входа**.

- `$rows` — list карт, `array_is_list`, длина **1..10000** (тот же потолок, что getList). `[]`, не-list, не-карта ряда → `MAP_INVALID`. Без SQL на отказ длины.
- Каждая карта — тот же путь, что `add`: `assembleInsert` (default / `DateTime::now()` / required / запрет `id`). Не слабее одиночного add.
- Колонки INSERT **одинаковы у всех рядов**: `assembleInsert` уже обходит всю скалярную карту, не «какие ключи прислали». Illuminate `insert([map, map, …])`. Пустая скалярная карта — как сейчас `['id' => null]` на ряд.
- Любое `multiple` в `getMap()` → `MAP_INVALID` на весь вызов, даже если ключа нет в рядах (v1: состав без mfv). Не частично.
- Один multi-insert, не цикл `add`. `OpenedRecords`: `noteAdd` **один** раз после успеха (как один `add`: тег стола, не N get).
- Id: InnoDB на **один** statement — блок подряд, `lastInsertId` = первый. Вернуть `range`. Параллельный insert в ту же таблицу во время вызова — не контракт. Вызов из уже открытой `gateway.transaction()` не требовать.
- 1062 / FK — весь statement. Те же `UNIQUE_CONSTRAINT` / `REFERENCE_CONSTRAINT`.
- `writeAtomic` вокруг одного insert (как `add` без второй ноги mfv). Не внутренний begin «ради пачки», если TX уже открыта.
- max_allowed_packet / лимит плейсхолдеров: **не** резать пачку внутри ST. Драйвер упал → `ROW_WRITE_FAILED`. Узкие карты состава (~2 колонки × 3k) влезают; широкая 10k — тот же отказ I/O, не чанки.

Не `updateMany` / `deleteMany`. Не словарь HTTP.

`TableRows` / quality: не раздувать `add`; вынести сборку списка payload. Mysql: две строки + порядок id; unique на пачке; карта с multiple — отказ; `[]` / `10001` — unit.

### 4. Канон ST

[`smarttable.md`](smarttable.md): getList max 10000; `addMany`; aggregate 500 без изменений.

## Todo

- [x] **limit** — ctor+parser getList 10000; ListQueryTest 501 ок / 10001 отказ; aggregate 500; HTTP User/Chat не трогать.
- [x] **addMany** — порт, mysql две строки, unique fail, multiple отказ, пустой/не-list/10001 unit.
- [x] **gates** — phpunit smarttable; cs/quality ST.
- [x] **canon** — этот файл; roadmap 18; smarttable.md; TR.md.

## Не входит

Versioning. Versioned-оболочка. JOIN. Смена 500 у aggregate. `getByIds`. Cursor. HTTP. mfv в addMany — [`smarttable-plan-19-addmany-mfv.md`](smarttable-plan-19-addmany-mfv.md) (план 18 так и закрыт без mfv).

## Документы захода

этот файл; [`smarttable.md`](smarttable.md); [`smarttable-roadmap.md`](smarttable-roadmap.md); [`versioning-plan-01.md`](versioning-plan-01.md).
