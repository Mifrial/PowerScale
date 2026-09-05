# План 19 — addMany пишет mfv; вложенная TX

**Статус:** сделано, 2026-09-05. Канон — [`smarttable.md`](smarttable.md). Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md). План 18 оставил mfv вне `addMany`. Потребитель — [`rule-plan-01.md`](rule-plan-01.md): признаки снимка — множество id на строке версии, не отдельная join-таблица. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: `addMany` пишет sidecar **так же, как `add`**. Вызов из уже открытой `gateway.transaction()` **не** `TRANSACTION_OPEN`. **Не** новый тип поля (это план 20). **Не** `updateMany`.

## Зачем

План 18 отрезал mfv, чтобы быстрее закрыть состав часов (~2 скалярные колонки). Правило с 1:N id на той же строке в `addMany` так не записать. Обход (json, join-таблица «потому что addMany») — ложный: после insert id известны (`lastInsertId` + длина пачки), mfv пишется тем же `writeAtomic`, что у `add`.

`add` / `addMany` / Versioning `commit` все зовут `transaction()`. Сейчас уровень > 0 → `TRANSACTION_OPEN`. Правило: внешняя TX вокруг commit часов + insert версий с mfv. Join TX — тот же заход, не отдельная «магия Rule».

## Решения

### 1. `addMany` и mfv

Снять `MAP_INVALID`, если на карте есть `multiple` (или тип плана 20).

Порядок: один multi-insert скаляров → блок id подряд → для каждого ряда `writeMfv` по своему id (как `add`). Всё в одном `writeAtomic`. `noteAdd` один раз после успеха.

Пустой list у поля — нет рядов mfv (как `add`). `required` + `[]` — до SQL, весь вызов. Дубль в list — как у одиночного add.

Тесты: карта с multiple, две строки, разные множества; getById / getList видят list; mysql unique/FK как в 18; пустой/10001 без изменений.

### 2. Уже открытая TX

`gateway.transaction()` и `writeAtomic`: если TX уже есть — выполнить замыкание в ней, **без** своего begin/commit/rollback, **без** `TRANSACTION_OPEN`. Свой commit/rollback — только если этот вызов открывал TX.

Кэш ST/среза часов — после commit **внешней** TX (как уже написано в [`versioning-plan-01.md`](versioning-plan-01.md)).

Тесты: вложенный `transaction()`; `addMany` с mfv внутри `transaction()`; suite `smarttable` и `versioning` зелёные.

Savepoint нет.

### 3. Канон

[`smarttable.md`](smarttable.md): `addMany` с mfv; вложенная TX — join. План 18: mfv в addMany больше не «не входит» — см. этот файл.

## Todo

- [x] **mfv** — addMany пишет sidecar; убрать отказ по multiple; mysql две строки с list.
- [x] **tx-join** — вложенный transaction/writeAtomic; versioning commit из внешней TX.
- [x] **gates** — phpunit `smarttable` + `versioning`; cs/quality ST.
- [x] **canon** — этот файл; roadmap 19; smarttable.md; TR.md.

## Не входит

Тип `linkset` ([`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md)). JOIN / hop через множество. HTTP. `updateMany`. Savepoint. Чанки пачки.

## Документы захода

этот файл; [`smarttable-plan-18-slice-batch.md`](smarttable-plan-18-slice-batch.md); [`smarttable-plan-20-linkset.md`](smarttable-plan-20-linkset.md); [`smarttable.md`](smarttable.md); [`smarttable-roadmap.md`](smarttable-roadmap.md); [`php-coding-standards.md`](php-coding-standards.md).
