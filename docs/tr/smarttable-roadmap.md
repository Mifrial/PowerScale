# Нарезка SmartTable

**Статус:** план реализации, 2026-09-02. Канон требований — [`smarttable.md`](smarttable.md). Каждый пункт — отдельный заход (чат/PR), не один мега-diff.

Порядок строгий сверху вниз, кроме явно параллельных. Versioned и админка не стартуют раньше своих ворот.

## 0. Сделано

- `DEC-078`, [`smarttable.md`](smarttable.md).
- `composer require illuminate/database` в `www/mifrial` (без Eloquent, без Laravel-приложения).

## 1. Соединение — сделано

Подробно: [`smarttable-plan-01-connection.md`](smarttable-plan-01-connection.md). Illuminate MySQL, `IRuntimeConfig`, ping.

## 2. Поля Basic — сделано

Подробно: [`smarttable-plan-02-fields.md`](smarttable-plan-02-fields.md).

## 3. CRUD одной таблицы с PHP-классом — сделано

Подробно: [`smarttable-plan-03-crud.md`](smarttable-plan-03-crud.md).

- `SmartTableDefinition` в коде (фикстура теста).
- `open` / `add` / `update` / `delete` / `getById` / `createTable` / `updateTable`.
- Транзакция `transaction()`.
- PHPUnit против MySQL.

## 4. `getList` — сделано

Подробно: [`smarttable-plan-04-getlist.md`](smarttable-plan-04-getlist.md).

- filter / sort / page (limit+offset, optional total) / select.
- Диалект v1: `=`, `!=`, `%`, `<`, `>`, `<=`, `>=`, `><`, AND/OR; не-json list → IN (как Bitrix); json IN нет (`OR` + `=`). `@` — contains, план 5.
- Sort по multiple — исключение. Кэш TTL — план 8.

## 5. Multiple и Reference

Multiple — сделано: [`smarttable-plan-05-multiple.md`](smarttable-plan-05-multiple.md) — mfv, working list, `@` contains, `=` равенство множеств. **Не** вместе с reference.

- Таблица `{table}_mfv_{field}`, индекс `(owner_id, value)`.
- Фильтр: `@` contains; `=` — равенство множеств (в т.ч. пустое).
- **Следом (тот же пункт 5, отдельный план):** [`smarttable-plan-05-reference.md`](smarttable-plan-05-reference.md) — restrict по умолчанию, без cascade; SET NULL не для required. **Сделано.**

## 6. Индексы из определения

Подробно: [`smarttable-plan-06-indexes.md`](smarttable-plan-06-indexes.md). index / unique на одной колонке; fulltext — хвост; составные unique — не v1. **Сделано.**

## 7. Runtime-словарь и DDL

Сначала деструктивный DDL таблицы с PHP-классом: [`smarttable-plan-07-force-ddl.md`](smarttable-plan-07-force-ddl.md) — `forceUpdateTable` / `deleteTable`. **Сделано.**

Затем словарь: [`smarttable-plan-07-dictionary.md`](smarttable-plan-07-dictionary.md) — мета-таблицы, `openByName`, создать/удалить таблицу и поле. **Сделано.**

## 8. Тегированный кэш

Подробно: [`smarttable-plan-08-cache.md`](smarttable-plan-08-cache.md). TTL только если передан в getById/getList; инвалидация после commit; `redis` | `file`. **Сделано.**

## 9. Накат схемы — не SmartTable (решение)

Подробно: [`smarttable-plan-09-migrations.md`](smarttable-plan-09-migrations.md). **Кода прогона в SmartTable нет.** DDL одной таблицы уже в модуле. Установка всех модулей, граф FK, CLI, реестр data-шагов — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md) (Kernel, не ST). Runtime-словарь — не git.

## 10. Ворота Basic — сделано

Подробно: [`smarttable-plan-10-gates.md`](smarttable-plan-10-gates.md). Чеклист [`smarttable.md`](smarttable.md) v1 закрыт. Нет UI и Versioned-кода. Дальше — [`auth-plan-01-session.md`](auth-plan-01-session.md) или план 11.

**После ворот (не отдельный пункт нарезки):** handle разрезан. `IOpenedTable` — сумка `schema()` / `records()`, не 9–10 CRUD-методов на одном типе. Закрытые планы 3–8 и «9 public» в плане 10 описывают API той недели; текущий контракт — [`smarttable.md`](smarttable.md). `getUnique` / `getFirst` — оболочка над `getList` + TTL.

## 13. DateTime default «сейчас» — сделано

Подробно: [`smarttable-plan-13-datetime-now.md`](smarttable-plan-13-datetime-now.md). Sentinel в `default` на datetime: add без ключа → `DateTime::now()`. Не SQL. User `registered_at` / `created_at` в том же заходе. Не блокирует план 11.

## 14. Путь через `reference` в getList — сделано

Подробно: [`smarttable-plan-14-reference-path.md`](smarttable-plan-14-reference-path.md). Тип `reference` — FK на `id` цели. Путь: hop только `reference`→`id`; лист — любое поле цели (в т.ч. multiple); в SQL только select/sort/filter. FROM — своя таблица. Повтор стола в пути законен. Не блокирует план 11. User: `hasBypass` / LAST_BYPASS — путь на членстве.

## 15. BIGINT и cascade — сделано

Подробно: [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md). Ширина на `IntField`; PK — `IdField::big()`; словарь `type: bigint`; `onDelete: cascade`. Не `BigIdField`. Ссылка ширину не выбирает. Блокер Auth 1. Не блокирует план 11.

## 11. VersionedSmartTable

- Только после плана 10.
- Оболочка: ревизия, чтение среза, copy-on-write.
- Без админки правил.

## 12. Админка SmartTables (фронт)

- Только после Auth (и ворот Basic).
- Раздел админки: список таблиц, поля, записи — для проверки глазами.

## Параллелить нельзя

- 11 с 3–8.
- 12 с отсутствием Auth.
- Прикладные репозитории с обходом SmartTable «на PDO, потом заменим».
