# План 2 — поля Basic и getMap

**Статус:** план реализации, 2026-09-01, после ревью. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Соединение — [`smarttable-plan-01-connection.md`](smarttable-plan-01-connection.md).

Цель: наши типы, `getMap()`, cast / extract / hydrate. Без Schema, `open`, CRUD, getList, working multiple, reference.

## Todo

- [x] **internal-connection** — на `IlluminateDatabaseConnection` метод `illuminateConnection(): MySqlConnection` (не на `IDatabaseConnection`).
- [x] **kernel-datetime** — `Mifrial\Core\Kernel\Value\DateTime`: `fromUnix(int)`, `toUnix(): int`; юнит.
- [x] **fields-map-hydrate** — BaseField + типы v1 + IdField + `SmartTableDefinition::getMap()`; каст/extract/hydrate.
- [x] **hydrator-stub-gates** — `IFieldHydrator`, пустой `HydratorRegistry`; юниты; cs/quality/phpunit.

## Не входит

DDL, CRUD, getList, reference, mfv-таблицы, decimal, файл, Versioned, formatRelative, разбор ISO на входе **cast**.

## Хвост плана 1

`illuminateConnection()` на конкретном адаптере, та же обёртка `DB_CONNECT_FAILED`, что ping. Интерфейс соседа — только `ping()`. PHP нет package-private: чужой модуль теоретически кастит класс; договор — не вызывать. `ping()` может вызывать `illuminateConnection()`, не дублировать try/catch.

## DateTime

Класс `Mifrial\Core\Kernel\Value\DateTime` (коллизия с `\DateTime` — в полях `use` FQCN ядра). Сигнатура `fromUnix(int)`: чужой тип на границе PHP даст `TypeError`, сами `TypeError` не бросаем и не ловим пачкой. Без ISO. SmartTable: **cast** с int/string — отказ; **hydrate из БД** — int или numeric-string → объект.

Колонка INT signed: даты после 2038 — ограничение канона v1, не BIGINT в этом плане.

## Поля

`BaseField`: name `[a-z][a-z0-9_]*`, label-ключ (пусто → fallback имя), required, multiple, default, **index/unique как флаги-метаданные** (DDL — планы 3/6). Настройки — один DTO (не 7 аргументов конструктора и не 12 public-геттеров: quality ≤10 public / ≤6 deps). Cast/extract/hydrate + `type()` / `name()` / `settings()` / мета колонки — уложиться в лимит, остальное на DTO.

`getName()` таблицы — тот же шаблон имени, что у поля.

Имя `id` занимает только `IdField`; другой тип с именем `id` — исключение.

**`id`:** autoincrement. На записи `id === null` допустим; hydrate всегда int.

Карта без `id` — исключение. Дубль имени — исключение. `multiple === true` — исключение на extract/hydrate.

`default`: в cast, если ключа нет. Явный `null` при required — ошибка.

Bool: **cast** только `bool`; 0/1/`"1"` на входе API — отказ. Hydrate с БД — 0/1 строки. Int: **cast** только `int` (не float, не numeric-string). Hydrate — numeric-string целого.

Строки: **`mb_strlen(..., 'UTF-8')`**. В `composer.json` — `ext-mbstring`. maxLength 255, диапазон 1..1024. Пустая строка при required допустима.

Коды: `FIELD_INVALID`, `MAP_INVALID` (нет id / дубль / имя), `FIELD_MULTIPLE_UNSUPPORTED`.

| Класс | extract | hydrate | колонка (мета, не DDL) |
|---|---|---|---|
| StringField | string | string | VARCHAR(n) |
| TextField | string | string | TEXT |
| HtmlField | string, без санитации | string | LONGTEXT |
| IntField | int; min/max; float/`true` не приводить | int (numeric-string с БД ок) | INT |
| BoolField | 0/1 | bool (0/1/`"0"`/`"1"` с БД) | TINYINT(1) |
| DateTimeField | unix int | Kernel DateTime | INT |
| JsonField | JSON-совместимое PHP (`array` / скаляр / `null`); не строка `json_encode` | то же или объект hydrator | JSON |

**JSON.** Свой класс-обёртка не заводим: канон уже задал тип — `array` / скаляр, либо объект hydrator. DateTime нужен VO, потому что в PHP нет unix-instant; у JSON натуральный тип уже есть. `json_encode` — граница драйвера в плане 3, не extract поля. Ресурс / `NAN` / цикл → `FIELD_INVALID`. Строка `'{"a":1}'` на cast — строка, не документ (молча не парсим). Hydrate: драйвер мог отдать уже decoded или JSON-строку — decode только на этой границе.

Неверный тип на **cast** → `FIELD_INVALID`. Hydrate с БД чуть мягче (numeric-string для int/bool/datetime).

Путь записи: **cast, затем extract**.

## getMap

`SmartTableDefinition`: `getName(): string` (физическое имя таблицы, для плана 3) + `getMap(): array<string, BaseField>`. Наследник отдаёт список полей; база индексирует по name, проверяет `id` и дубли. Фикстура без БД.

`getType(): string` — наш тип (`string`, `html`, …), не MySQL.

## Hydrator

`IFieldHydrator` hydrate/extract. JsonField — опционально. `HydratorRegistry` порт контейнера SmartTable, на boot пустой. Ключ `smarttable_hydrators` в module.config **зарезервировать комментарием**, сканер чужих модулей не писать.

## Тесты

Юниты без MySQL (+ регресс ping). Round-trip по типам; UTF-8 длина «я»×256; int/float; datetime cast int отказ / hydrate `"1700000000"` ок; json; required null; id null на extract add ок; карта без id; дубль; multiple true; DateTime unix.

Ворота: cs-check, quality, phpunit. Нет классов CRUD/Schema.

## Следующий заход

План 3 — DDL из метаданных поля, `open`, CRUD, `transaction()`, тот же `illuminateConnection()`.
