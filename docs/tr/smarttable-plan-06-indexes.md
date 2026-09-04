# План 6 — индексы из определения

**Статус:** сделано, 2026-09-01. Канон — [`smarttable.md`](smarttable.md) § Индексы и § Ошибки. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Reference — [`smarttable-plan-05-reference.md`](smarttable-plan-05-reference.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: флаги `indexed` / `unique` в `FieldSettings` становятся DDL на **одной** колонке основной таблицы. Составные unique — не v1. Fulltext — **хвост** (нет флага, getList по-прежнему `%` / LIKE). Сигнатуры `IOpenedTable` не расширять; `@throws` дополнить. `Service/` не резать на подпапки.

## Todo

- [x] **map** — недопустимые пары флагов × тип / multiple / id / длина string — `MAP_INVALID` в `SmartTableDefinition::indexFields` (рядом с `assertMultipleAllowed`), без SQL. Не в `IndexSchema` (слой). Не в конструкторе поля (`type()` ещё нет). `unique` без отдельного `indexed`: один UNIQUE. Оба флага true — тоже один UNIQUE (`_unq`), не unique+index.
- [x] **ddl** — `IndexSchema`: имена `{table}_{field}_idx` / `{table}_{field}_unq`; `assertNames` **до** `Schema::create` (длина > 64 → `MAP_INVALID` без сироты). CREATE: колонки → ALTER индексы → mfv → FK. `updateTable` **тот же порядок**: колонки → `createMissing` индексов → mfv → FK; только недостающие по **имени**; лишние не дропать. `createTable` уже у порога 30 строк: только вызов IndexSchema без логики имён; если quality — вынести post-create (индексы → mfv → FK) в один private метод.
- [x] **write** — translator: только MySQL `1062` → `UNIQUE_CONSTRAINT`; `1451`/`1452` не трогать. add/update без предварительного SELECT «уже есть».
- [x] **tests-gates** — юнит отказов **карты** (`getMap`); MySQL create/unique duplicate/updateTable restore; skip как ping; cs/quality/phpunit; канон § Ошибки: `UniqueConstraintException` в ветке row.

## Не входит

Fulltext / `MATCH AGAINST`, составной **неуникальный** index, индекс на mfv кроме уже существующего PK `(owner_id, value)`, `forceUpdateTable` (снятие лишнего индекса), префиксные VARCHAR-индексы, unique на `text`/`html`/`json`, functional indexes, смена unique→index DROP+ADD, план 7 словарь, кэш, JOIN, multiple+reference.

Составной **unique** закрыт: [`smarttable-plan-16-composite-unique.md`](smarttable-plan-16-composite-unique.md).

## Зачем не с fulltext и не с составными

Fulltext без смены диалекта getList мёртв (`%` остаётся LIKE). Составные — отдельный контракт на таблице, не флаг поля. Sidecar mfv уже имеет PK; второй смысл unique на multiple (глобально уникальный элемент) — другой продукт.

## Слои

Плоский `Service/`. Дробить по задаче.

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `FieldSettings` | `Dto/` | уже `indexed()` / `unique()` | DDL, отказ типа |
| `SmartTableDefinition` | `Table/` | `MAP_INVALID` флагов на карте | Blueprint |
| `IndexSchema` | `Service/` | `assertNames` до CREATE; `indexName` / `uniqueName`; `getIndexes`; ALTER | insert, FK, проверка типа |
| `TableSchema` | `Service/` | CREATE и `updateTable`: колонки → индексы → mfv → FK | значения |
| `DriverErrorTranslator` | `Service/` | `errorInfo[1] === 1062` → лист | 1451/1452, DDL |
| `TableRows` | `Service/` | без SELECT uniqueness | DDL |
| `IOpenedTable` | `Interface/` | только `@throws` на add/update | новые методы |

`IndexSchema` stateless, `new` на том же адаптере, не порт. `OpenedTable` без нового dep. `Table/` не импортирует `Service/`.

Порядок CREATE и `updateTable`: колонки → **индексы** → mfv → FK. Unique/index на `reference` до FK, чтобы InnoDB переиспользовал индекс. Индексы **не** в первом create-callback: ALTER после `Schema::create`.

`assertNames` (как `assertTargetsExist` у FK): до CREATE/до добавления колонок на update, чтобы `{table}_{field}_unq` длиннее 64 не оставлял таблицу после CREATE.

## Контракт флагов

Уже есть: `FieldSettings::fromOptions(['indexed' => true])`, `['unique' => true]`. Новых ключей нет. Fulltext-ключа нет.

Изолированный `new TextField(..., unique)` карту не собирает — отказ только на `getMap()`.

`unique === true` ⇒ физически UNIQUE INDEX `_unq`.  
`indexed === true` и `unique === false` ⇒ INDEX `_idx`.  
Оба true ⇒ только `_unq`.

`MAP_INVALID` на `getMap`:

- `indexed` или `unique` на `id` (PK уже есть);
- на `multiple` (PK sidecar `(owner_id, value)` уже есть);
- на типах `text`, `html`, `json`;
- на `string` с `maxLength() > 255` (utf8mb4: 1024 не влезает в лимит ключа InnoDB без prefix; prefix — не v1);
- допустимы: `string` (≤255), `int`, `bool`, `datetime`, `reference`.

`bool` + unique формально допустим (два `false` не влезут) — не запрещать, отдельный тест не обязателен.

Nullable unique: MySQL допускает несколько NULL — это поведение СУБД, не эмулировать в PHP.

## Имена и leftover

Шаблон `[a-z][a-z0-9_]*`, длина ≤ 64 — в `IndexSchema`, как mfv/FK.

| Флаг | Имя |
|---|---|
| indexed | `{table}_{field}_idx` |
| unique | `{table}_{field}_unq` |

Есть ли индекс: Illuminate `getIndexes($table)` (11.x), сравнение **имени**. Не парсить `SHOW INDEX`. `PRIMARY` не трогать.

`updateTable`: после недостающих колонок — `createMissing` индексов, потом mfv, потом FK. Имени нет → создать. Имя есть → не трогать (в т.ч. если в PHP сняли флаг или сменили unique↔index). Снятие — `forceUpdateTable`, не этот план.

**Leftover `_unq`:** флаг в PHP сняли, constraint в БД остался — дубли по-прежнему **1062** / `UNIQUE_CONSTRAINT`. Это то же правило, что leftover FK: `updateTable` не ослабляет схему.

Побочный индекс InnoDB под уже существующий FK (`{table}_{field}_fk`) — не `{table}_{field}_idx`. Если на `reference` впервые ставят `indexed`, появится **наш** `_idx`; не переименовывать индекс FK.

## Фасад

Сигнатуры не менять. `@throws UniqueConstraintException` на `add` / `update` (дубль unique). `createTable` / `updateTable` — по-прежнему `DdlFailedException`, если драйвер отклонил `CREATE INDEX` (в т.ч. дубли в уже лежащих данных при добавлении unique). DDL **не** гонять через `DriverErrorTranslator` (1062 на ALTER unique с грязными данными — `DDL_FAILED`, не `UNIQUE_CONSTRAINT`).

## Ошибки

Лист: `Exception/Row/UniqueConstraintException`, код `UNIQUE_CONSTRAINT`. Конструктор `?Throwable $previous = null`, как у `ROW_WRITE_FAILED`.

`DriverErrorTranslator`: `errorInfo[1] === 1062` → этот лист. Не 1451/1452. Не весь `23000`. Не разбирать текст.

Канон [`smarttable.md`](smarttable.md) § Ошибки — в ветку row добавить `UniqueConstraintException`. § Индексы — уточнить: одно поле; unique ⇒ UNIQUE; leftover UNIQUE продолжает отвергать дубли; fulltext не v1 этого захода.

## Тесты

Только MySQL, skip как ping. Teardown `dropIfExists`.

Фикстуры: таблица с `string` unique; с `int` indexed. Отказы **getMap**: unique на text, на multiple, на id, string 256 unique — `MAP_INVALID`.

MySQL:

- create: есть `{table}_{field}_unq` / `_idx`; unique дубль add и update → `UNIQUE_CONSTRAINT`; строка на месте.
- два NULL в nullable unique — допустимы (если проверяем).
- updateTable restore: unique — `dropUnique(имя)`; index — `dropIndex(имя)`; колонка остаётся → `updateTable` создаёт индекс снова.
- leftover: не вызывать drop «лишних»; не ждать, что снятие флага в PHP снимет UNIQUE в БД.
- регресс **раздельно**: CRUD/mfv без флагов; reference 1452 **без** unique на той же колонке; unique 1062 **без** FK на той же колонке. Не одна таблица «и FK, и unique» ради путаницы кодов.

Юнит: отказы `getMap` без SQL.

Ворота: `cs-check`, `quality`, `phpunit`.

## Документы захода

- этот файл; ссылки в [`TR.md`](TR.md) и [`smarttable-roadmap.md`](smarttable-roadmap.md);
- [`smarttable.md`](smarttable.md) § Индексы и § Ошибки.

## Следующий заход

План 7: runtime-словарь и DDL удаления. Кэш — план 8. Fulltext — хвост после диалекта поиска, не вместе со словарём.
