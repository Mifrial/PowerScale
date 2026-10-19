# Core/SmartTable

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/SmartTable --zone=tr`.

- **Путь:** `modules/Core/SmartTable`
- **Неймспейс:** `Mifrial\Core\SmartTable`
- **Контейнер:** `Mifrial\Core\SmartTable\Container\SmartTableContainer`
- **Локатор:** `Mifrial\Core\SmartTable\Interface\Container\ISmartTableContainer`
- **Setup:** `Mifrial\Core\SmartTable\Setup\SmartTableModuleSetup`
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/SmartTable/README.md)
- **Канон:** раздел «Карта доверия: стабильные модули Core» в [contract-status.md](../../contract-status.md)

## Порты

- `Mifrial\Core\SmartTable\Interface\Service\IDatabaseConnection`
- `Mifrial\Core\SmartTable\Interface\Service\ISmartTableGateway`
- `Mifrial\Core\SmartTable\Interface\Service\ITableCatalog`
- `Mifrial\Core\SmartTable\Service\HydratorRegistry`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/ISmartTableContainer.php` — Публичный контейнер модуля SmartTable.
- `Interface/Field/IFieldHydrator.php` — Преобразование JSON-документа в объект модуля и обратно.
- `Interface/Service/IConditionalOpenedRecords.php` — Условные операции одной открытой карты.
- `Interface/Service/IDatabaseConnection.php` — Внутреннее ленивое соединение с MySQL без SQL-API для соседей.
- `Interface/Service/IOpenedRecords.php` — Строки одной открытой карты.
- `Interface/Service/IOpenedSchema.php` — DDL одной открытой карты.
- `Interface/Service/IOpenedTable.php` — Сумка после open: схема и строки одной карты.
- `Interface/Service/ISmartTableGateway.php` — Порт открытия таблиц по PHP-классу и транзакции соединения.
- `Interface/Service/ITableCatalog.php` — Порт словаря: meta DDL и runtime-таблицы по имени.

## Зависимости

- **Зависит от:** [Core/Cache](Cache.md), [Core/Kernel](Kernel.md)
- **От него зависят:** [Core/Agent](Agent.md), [Core/Auth](Auth.md), [Core/Kernel](Kernel.md), [Core/Logger](Logger.md), [Core/Mail](Mail.md), [Core/User](User.md), [Messages/Chat](../Messages/Chat.md), [Roleplay/Character](../Roleplay/Character.md), [Roleplay/Game](../Roleplay/Game.md), [Roleplay/Keyword](../Roleplay/Keyword.md), [Roleplay/Mechanic](../Roleplay/Mechanic.md), [Roleplay/Rule](../Roleplay/Rule.md), [Roleplay/RuleSpace](../Roleplay/RuleSpace.md), [Versioning/Space](../Versioning/Space.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [smarttable-plan-01-connection.md](../../smarttable-plan-01-connection.md) | план | `~ПЛАН~` | 2026-09-01 |
| [smarttable-plan-02-fields.md](../../smarttable-plan-02-fields.md) | план | `~ПЛАН~` | 2026-09-01 |
| [smarttable-plan-09-migrations.md](../../smarttable-plan-09-migrations.md) | план | `~ПЛАН~` | 2026-09-02 |
| [smarttable-plan-18-slice-batch.md](../../smarttable-plan-18-slice-batch.md) | план | `~ПЛАН~` | 2026-09-05 |
| [smarttable-roadmap.md](../../smarttable-roadmap.md) | план | `~ПЛАН~` | 2026-09-02 |
| [smarttable-plan-03-crud.md](../../smarttable-plan-03-crud.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-04-getlist.md](../../smarttable-plan-04-getlist.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-05-multiple.md](../../smarttable-plan-05-multiple.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-05-reference.md](../../smarttable-plan-05-reference.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-06-indexes.md](../../smarttable-plan-06-indexes.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-07-dictionary.md](../../smarttable-plan-07-dictionary.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-07-force-ddl.md](../../smarttable-plan-07-force-ddl.md) | план | `~СДЕЛАНО~` | 2026-09-01 |
| [smarttable-plan-08-cache.md](../../smarttable-plan-08-cache.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [smarttable-plan-10-gates.md](../../smarttable-plan-10-gates.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [smarttable-plan-13-datetime-now.md](../../smarttable-plan-13-datetime-now.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [smarttable-plan-14-reference-path.md](../../smarttable-plan-14-reference-path.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [smarttable-plan-15-bigint.md](../../smarttable-plan-15-bigint.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [smarttable-plan-16-composite-unique.md](../../smarttable-plan-16-composite-unique.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [smarttable-plan-17-aggregate.md](../../smarttable-plan-17-aggregate.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [smarttable-plan-19-addmany-mfv.md](../../smarttable-plan-19-addmany-mfv.md) | план | `~СДЕЛАНО~` | 2026-09-05 |
| [smarttable-plan-20-linkset.md](../../smarttable-plan-20-linkset.md) | план | `~СДЕЛАНО~` | 2026-09-05 |
| [smarttable.md](../../smarttable.md) | канон | `~СДЕЛАНО~` | 2026-09-02 |

