# План 9 — накат схемы не в SmartTable

**Статус:** решение, 2026-09-02. Канон — [`smarttable.md`](smarttable.md) § Назначение. Нарезка — [`smarttable-roadmap.md`](smarttable-roadmap.md) пункт 9. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Код в `Core/SmartTable` по этому пункту **не пишем**. Следующий заход SmartTable — план 10 (ворота Basic).

## Решение

SmartTable — HL-подобный доступ к **одной** таблице: карта, строка, список, кэш, DDL (`createTable` / `updateTable` / `forceUpdateTable` / `deleteTable`).

Журнал наката репозитория (какие шаги уже в этой БД, up/down, CLI, порядок модулей, шаги не про таблицы SmartTable) — **отдельный модуль Core**, по роли как sprint.migration относительно HL. Он клиент SmartTable: `open` + DDL, без Schema Builder и без Illuminate migrate.

Ворота Basic **не ждут** этот модуль. Пока его нет, прикладной install может один раз вызвать `createTable`. Runtime-таблицы накатывает словарь, не git.

Имя модуля наката здесь не фиксируем.

## В SmartTable уже есть (не этот пункт)

- DDL и сверка `getMap()` ↔ физика одной таблицы.
- Словарь: runtime живёт строками meta.

## Не класть в SmartTable

- `ISchemaMigrator`, `st_schema_log`, `bin/smarttable-migrate`.
- Реестр всех class-string таблиц приложения и обход `ModuleManager`.
- Numbered PHP с произвольным `up` (seed, не-табличные шаги).
- Laravel migrate / artisan.

Иначе у SmartTable вторая причина меняться: релиз, не доступ к данным.

## Следующий заход SmartTable

План 10: чеклист [`smarttable.md`](smarttable.md) v1, без UI, без Versioned. Модуль наката — своя нарезка, не пункт внутри SmartTable.
