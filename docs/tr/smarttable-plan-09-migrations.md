# План 9 — DDL одной таблицы в SmartTable; прогон модулей не здесь

**Статус:** решение, 2026-09-02, уточнение терминов 2026-09-02. Канон — [`smarttable.md`](smarttable.md) § Назначение. Прогон модулей — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Нарезка ST — [`smarttable-roadmap.md`](smarttable-roadmap.md) пункт 9.

Код прогона в `Core/SmartTable` **не пишем**. Ворота Basic (план 10) этот код не ждут.

## Решение

SmartTable — доступ к **одной** таблице: карта, строка, список, кэш, DDL (`createTable` / `updateTable` / `forceUpdateTable` / `deleteTable`).

Установка и обновление **набора** модулей (граф FK, CLI, реестр data-шагов) — **Kernel**, не SmartTable и не отдельный `Core/Migration`. Не журнал аудита.

Пока CLI нет, прикладной `install()` модуля может один раз вызвать `createTable` / `updateTable`. Runtime-таблицы словаря накатывает каталог, не git.

## Не класть в SmartTable

- `ISchemaMigrator`, `st_schema_log`, `bin/smarttable-migrate`.
- Реестр class-string всего приложения и обход `ModuleManager`.
- Numbered PHP `up`/`down`, Laravel migrate / artisan.

Иначе у SmartTable вторая причина меняться: релиз, не доступ к данным.

## В SmartTable уже есть

- DDL и сверка `getMap()` ↔ физика одной таблицы.
- Словарь: runtime живёт строками meta.
