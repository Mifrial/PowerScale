# План 15 — ширина INT/BIGINT и `onDelete: cascade`

**Статус:** сделано, 2026-09-02. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). Поля — [`smarttable-plan-02-fields.md`](smarttable-plan-02-fields.md). Reference — [`smarttable-plan-05-reference.md`](smarttable-plan-05-reference.md). Auth 1 — [`auth-plan-01-session.md`](auth-plan-01-session.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: PHP-карта может объявить широкий системный `id` и широкую целую колонку; словарь — `type: bigint` на поле данных; `reference` умеет `onDelete: cascade` (в т.ч. required). Не отдельные классы `BigIntField` / `BigIdField`. Не ширина ссылки руками. Не флаг таблицы «широкий PK» из UI (план 12).

Нужно Auth 1: PK `auth_session` — частый insert+delete, `AUTO_INCREMENT` INT кончится; `user_id` сессии — `cascade` на `user`. `user.id` не меняем.

## Решения

**Ширина — свойство `IntField`, не `FieldSettings`.** Как `maxLength` у string: SQL-тип колонки, не required/unique/multiple. Конструктор `IntField`: флаг `$big = false`. `isBig(): bool`. `type()`: `int` или `bigint`. `column()`: `INT` или `BIGINT`. PHP in/out по-прежнему `int` (64-bit). min/max как сейчас.

**Не `IntField::big()`.** Статический метод родителя в PHP виден на `ReferenceField`. Обычная широкая колонка: `new IntField($name, $settings, $min, $max, big: true)`.

**PK:** карта по-прежнему `instanceof IdField`. `IdField` прокидывает `$big` в родителя. Сахар только здесь: `IdField::big(?FieldSettings $settings = null): self`. Имя `id`, AUTO_INCREMENT, `allowsNullWrite` — как сейчас.

**Не классы-близнецы.** Один бит ширины не размножает иерархию и allow-list `instanceof`.

**`reference` ширину не выбирает.** Конструктор и `forTable` флаг не принимают. `ReferenceField::big()` нет. В JSON спеки ключа `big` на ссылке нет. Пока родители с широким `id` не обещаны: FK остаётся signed INT (родители Auth — `user`, INT). Вывод ширины FK из `id` цели — отдельный заход.

**Словарь.** `type: bigint` → `IntField` с `$big`; extraKeys `min`/`max` как у `int`. Ключа `big` в JSON нет. `type: int` — как сейчас. `id` в спеку не входит; `RuntimeDefinition` клеит `IdField()` (INT). Флаг мета-таблицы «широкий системный id» — не этот план.

**Filter / index / multiple.** `IdField::big()` даёт `type() === 'bigint'`. В группу с `int` (не в `default` фильтра): `ListScalarFilter` сейчас для неизвестного типа даёт только `=`/`!=`; без явного `bigint` операторы `<`/`><` на широком поле отвалятся. То же имя в `assertIndexFlags` и `assertMultipleAllowed`. Sort по колонке, списка типов нет.

**Hydrate.** `IntField::hydratePresent` уже ест цифровую строку PDO → `int`. `TableRows::add` уже `(int) lastInsertId()`. На 64-bit signed BIGINT в PHP `int` укладывается. Отдельный hydrator не нужен; mysql-тест широкого `id` обязан пройти через add/get.

**DDL.** Не `bigIncrements()`: в Illuminate это **unsigned**. Сейчас id: `$blueprint->integer('id', true, false)` (autoIncrement, signed). Широкий PK: **`$blueprint->bigInteger('id', true, false)`**. Данные: `ColumnMeta` `BIGINT` → `$blueprint->bigInteger($name)` в `blueprintColumn` (сейчас match знает только `INT` — иначе `MAP_INVALID`).

**mfv ширина.** `valueColumn` — ветка `BIGINT`. `owner_id`: не AUTO_INCREMENT; signed `integer` или `bigInteger` по `isBig()` у `id` **этой** таблицы (`createOne` сейчас `use ($field)` без definition — понадобится карта). Auth-сессия без mfv; без веток широкий PK + multiple не поднимаются.

**`onDelete: cascade`.** Четвёртый режим. Сейчас required ⇒ только restrict; `cascade` в `FieldMapTest` — отказ. `required`+`cascade` законно; `required`+`setNull`/`none` нет. `defineForeign`: ветка `cascadeOnDelete`; `restrictOnUpdate` как сейчас. Граф CLI: ребро = `onDelete !== 'none'`.

**CASCADE обходит PHP `delete()`.** InnoDB снесёт строки-дети сам: `TableRows::delete` (чистка mfv) не вызовется. FK mfv→владелец по-прежнему нет. Поэтому **на одной карте нельзя** `onDelete: cascade` и любое `multiple` → `MAP_INVALID` в `getMap`. Auth-сессия без mfv. FK sidecar не добавляем (план 5: чистка в PHP, пока delete идёт через ST).

**Не force PK.** `updateTable` / `forceUpdateTable` колонку `id` не трогают и **тип id не сверяют** (есть ли колонка `id` — да; INT vs BIGINT — нет). Смена уже живого PK — не этот план: широкий id только на **CREATE**. Молчаливый INT при карте `IdField::big()` на старой таблице — не чиним.

## Todo

- [x] **int-width** — флаг на `IntField`; `type()` / `column()`; без `IntField::big()`. Юнит min/max + big.
- [x] **id-big** — `IdField` принимает `$big`; `IdField::big()`; `ColumnSchema` `bigInteger('id', true, false)`. Карта `instanceof IdField`. Не `user`.
- [x] **ddl-meta** — `blueprintColumn` + `MfvSchema` (`value` BIGINT, `owner_id` по `isBig()` у id таблицы).
- [x] **catalog** — assembler `type: bigint` + extraKeys min/max; юнит без MySQL. Не флаг PK таблицы.
- [x] **query-index** — `bigint` в той же ветке операторов, что `int` (`ListScalarFilter`), index, multiple. Mysql: add/get широкого id; filter `<` по bigint (не только `=`).
- [x] **cascade** — ctor; required+cascade ок; cascade+multiple на той же карте → `MAP_INVALID`; `FieldMapTest`; `defineForeign`; mysql: ребёнок **без** mfv, delete родителя сносит детей; restrict/setNull как были.
- [x] **tests-gates** — mysql: `IdField::big()`, колонка `IntField` big, cascade FK; cs/quality/phpunit smarttable. Карт Auth нет.

## Не входит

`BigIntField` / `BigIdField`. `ReferenceField` с `$big`. Вывод ширины FK из цели. Флаг широкого PK в `st_meta_table`. Смена `user.id`. Unsigned. 32-bit PHP. JSON-float > 2⁵³ (уже у `int`). cascade×mfv на одной карте (запрет, не FK sidecar). UI админки. Карты Auth. Versioned.

## Документы захода

этот файл; [`smarttable.md`](smarttable.md); roadmap; [`TR.md`](TR.md); [`smarttable-plan-05-reference.md`](smarttable-plan-05-reference.md) (дополнение); [`auth-plan-01-session.md`](auth-plan-01-session.md) (потребитель: `IdField::big()` + cascade).

## Следующий заход

Код Auth 1 (`auth-plan-01-session.md`), начиная с карт: сессия `IdField::big()`, `user_id` cascade.
