# Roleplay/Keyword

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Keyword --zone=tr`.

- **Путь:** `modules/Roleplay/Keyword`
- **Неймспейс:** `Mifrial\Roleplay\Keyword`
- **Контейнер:** `Mifrial\Roleplay\Keyword\Container\KeywordContainer`
- **Локатор:** `Mifrial\Roleplay\Keyword\Interface\Container\IKeywordContainer`
- **Setup:** `Mifrial\Roleplay\Keyword\Setup\KeywordModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Keyword\Interface\Service\IKeywords`
- `Mifrial\Roleplay\Keyword\Service\KeywordHttpService`
- `Mifrial\Roleplay\Keyword\Action\GetKeywordListAction`
- `Mifrial\Roleplay\Keyword\Action\GetKeywordAction`
- `Mifrial\Roleplay\Keyword\Action\CreateKeywordAction`
- `Mifrial\Roleplay\Keyword\Action\UpdateKeywordAction`
- `Mifrial\Roleplay\Keyword\Action\DeactivateKeywordAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `keyword.getList` | `GetKeywordListAction` | да |
| `keyword.get` | `GetKeywordAction` | да |
| `keyword.create` | `CreateKeywordAction` | да |
| `keyword.update` | `UpdateKeywordAction` | да |
| `keyword.deactivate` | `DeactivateKeywordAction` | да |

## Контракты `Interface/`

- `Interface/Container/IKeywordContainer.php` — Публичный контейнер модуля Roleplay/Keyword.
- `Interface/Service/IKeywords.php` — Фасад справочника признаков: без схемы и без таблиц.

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** [Roleplay/Character](Character.md), [Roleplay/Rule](Rule.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [keyword-plan-01.md](../../keyword-plan-01.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [keyword-plan-02.md](../../keyword-plan-02.md) | план | `~ЧАСТИЧНО~` | 2026-09-06 |
| [keyword-plan-03.md](../../keyword-plan-03.md) | план | `~ЧАСТИЧНО~` | 2026-09-06 |

