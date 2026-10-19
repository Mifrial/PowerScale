# Versioning/Space

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Versioning/Space --zone=tr`.

- **Путь:** `modules/Versioning/Space`
- **Неймспейс:** `Mifrial\Versioning\Space`
- **Контейнер:** `Mifrial\Versioning\Space\Container\SpaceContainer`
- **Локатор:** `Mifrial\Versioning\Space\Interface\Container\ISpaceContainer`
- **Setup:** —
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Versioning\Space\Interface\Service\IVersionedCatalog`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| — | — | — |

## Контракты `Interface/`

- `Interface/Container/ISpaceContainer.php` — Публичный контейнер модуля Versioning/Space.
- `Interface/Service/IVersionedCatalog.php` — Открывает репозиторий по реестру или явной спецификации кластера.
- `Interface/Service/IVersionedRepository.php` — Часы и срез одной версионируемой сущности.

## Зависимости

- **Зависит от:** [Core/Cache](../Core/Cache.md), [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md)
- **От него зависят:** [Roleplay/Rule](../Roleplay/Rule.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [versioning-plan-01.md](../../versioning-plan-01.md) | план | `~СДЕЛАНО~` | 2026-09-05 |
| [versioning-roadmap.md](../../versioning-roadmap.md) | план | `~СДЕЛАНО~` | 2026-09-05 |

