# План 20 — тип `linkset` (`LinkSetField`, не hop)

**Статус:** сделано, 2026-09-05. Канон — [`smarttable.md`](smarttable.md). После [`smarttable-plan-19-addmany-mfv.md`](smarttable-plan-19-addmany-mfv.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Потребитель — [`rule-plan-01.md`](rule-plan-01.md) (`keywords` на снимке правила).

Цель: тип поля для **1:N связей с другой таблицей**. Хранение — mfv. **Не** hop. Класс **`LinkSetField`**, `type()` = **`linkset`**. Не `reference`, не `LinkField` (это звучит как N:1).

## Зачем не `reference`

`reference` уже занят планом 14: **N:1**, колонка `INT`, **единственный** hop в `getList` (`a.b.c`). `reference` + `multiple` в v1 запрещён.

Нужно «строки таблицы T», но **множество**, без прыжка в filter/sort/select. Назвать это `reference` / `LinkField` — путать с hop.

Не флаг `multiple` на `int`: нет цели, нет FK/onDelete. Не `LinkListField`: порядок не контракт (множество; PHP `list<int>` — только форма). Раскрытие чужих колонок (`keywords[id, code, name]`) — сахар запроса **поверх** связи, не второй тип `ProjectionField`.

В v1 SELECT своей строки — `[1, 3]`, не гидрация `code`/`name`. Подписи — второй `getList` по `id IN` или сахар позже, не путь `keywords.code`.

## Решения

### 1. Тип `linkset`

Класс `LinkSetField` рядом с `ReferenceField` (не наследник hop). Колонки на основной таблице **нет**. Sidecar `{table}_mfv_{field}`, как у `multiple`. Флаг `multiple` на `linkset` не ставить: тип уже множество.

Настройки: цель (class-string карты), `onDelete`: `restrict` (default) / `none`. **Нет** `setNull` (некуда ставить null в mfv). **Нет** `cascade` в v1. `required` + `[]` → `FIELD_REQUIRED`.

FK на `(value)` → `id` цели, если не `none`. Unique `(owner_id, value)`.

PHP in/out: `list<int>`. Дубль в list — ошибка поля. Чужой / нулевой id — как у `reference` (FK или `REFERENCE_CONSTRAINT`).

### 2. Не hop

Путь `getList` / `aggregate`: hop **только** по `reference` (план 14). Ключ `keywords.code` на карте с `linkset keywords` — `MAP_INVALID`. Лист пути может быть `linkset` на **достигнутой** карте (как лист может быть `multiple`).

Своё поле `linkset` в ряду getList — `list<int>`.

Фильтр/сорт: как у `multiple` (`@`, `=` множеств; sort в v1 — исключение).

### 3. DDL / CRUD

`createTable` / `updateTable` / `forceUpdateTable` / `delete` владельца — как mfv. `add` / `addMany` (план 19) / `update` пишут sidecar.

Индекс `indexed`/`unique` на самом `linkset` — нет (есть unique пары в mfv).

### 4. Канон

Таблица типов в [`smarttable.md`](smarttable.md): строка `linkset`. Явно: hop = только `reference`.

## Todo

- [x] **field** — `LinkSetField`, schema sidecar + FK.
- [x] **crud** — add/addMany/update/delete/getList filter `@` и `=`.
- [x] **no-hop** — путь через linkset — отказ; лист linkset после hop по reference — ок.
- [x] **gates** — phpunit smarttable; cs/quality.
- [x] **canon** — этот файл; roadmap 20; smarttable.md; TR.md.

## Не входит

Гидрация `select: keywords[id, code, name]`. Hop через множество. `cascade` onDelete. JSON вместо mfv. Join-таблица «вместо» linkset.

## Документы захода

этот файл; [`smarttable-plan-19-addmany-mfv.md`](smarttable-plan-19-addmany-mfv.md); [`smarttable-plan-14-reference-path.md`](smarttable-plan-14-reference-path.md); [`smarttable.md`](smarttable.md); [`rule-plan-01.md`](rule-plan-01.md); [`php-coding-standards.md`](php-coding-standards.md).
