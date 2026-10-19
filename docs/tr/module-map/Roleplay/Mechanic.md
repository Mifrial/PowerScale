# Roleplay/Mechanic

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Roleplay/Mechanic --zone=tr`.

- **Путь:** `modules/Roleplay/Mechanic`
- **Неймспейс:** `Mifrial\Roleplay\Mechanic`
- **Контейнер:** `Mifrial\Roleplay\Mechanic\Container\MechanicContainer`
- **Локатор:** `Mifrial\Roleplay\Mechanic\Interface\Container\IMechanicContainer`
- **Setup:** `Mifrial\Roleplay\Mechanic\Setup\MechanicModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Roleplay\Mechanic\Interface\Service\IMechanics`
- `Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicEngine`
- `Mifrial\Roleplay\Mechanic\Interface\Service\IMechanicRolls`
- `Mifrial\Roleplay\Mechanic\Service\MechanicHttpService`
- `Mifrial\Roleplay\Mechanic\Action\GetMechanicListAction`
- `Mifrial\Roleplay\Mechanic\Action\GetMechanicAction`
- `Mifrial\Roleplay\Mechanic\Action\CreateMechanicAction`
- `Mifrial\Roleplay\Mechanic\Action\UpdateMechanicAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `mechanic.getList` | `GetMechanicListAction` | да |
| `mechanic.get` | `GetMechanicAction` | да |
| `mechanic.create` | `CreateMechanicAction` | да |
| `mechanic.update` | `UpdateMechanicAction` | да |

## Контракты `Interface/`

- `Interface/Container/IMechanicContainer.php` — Публичный контейнер модуля Roleplay/Mechanic.
- `Interface/IBloodClotting.php` — Маркер свёртывания на карточке состояния. runEvent его не зовёт.
- `Interface/IBluntUnconscious.php` — Потеря сознания при пороге успеха и повреждении не ниже стойкости.
- `Interface/IDamageAsWounds.php` — Повреждения этого удара пишутся раной, а не остатком.
- `Interface/IExhaustionAmount.php` — Истощение этого удара пишется в состояние той же силы.
- `Interface/IExhaustionWound.php` — Истощение этого удара пишется в рану с множителем.
- `Interface/IInjuryEfficiency.php` — Помеха проверки увечья. Дельта лежит в payload хука.
- `Interface/IMechanicHandler.php` — Хендлер механики: подписки события на приоритет и мутация контекста.
- `Interface/IReliabilityCut.php` — Маркер хендлера, который срезает надёжность слоёв с порогом.
- `Interface/MechanicPayload.php` — Payload binding: хендлер сам сужает тип. Общего поведения нет.
- `Interface/Service/IMechanicEngine.php` — Публичный событийный движок: сосед не регистрирует хендлеры и не видит реестр.
- `Interface/Service/IMechanicRolls.php` — Порт броска: сосед собирает binding и не пишет формулу кубов.
- `Interface/Service/IMechanics.php` — Фасад справочника механик: без схемы и без таблиц.

## Зависимости

- **Зависит от:** [Core/Kernel](../Core/Kernel.md), [Core/SmartTable](../Core/SmartTable.md), [Core/User](../Core/User.md)
- **От него зависят:** [Roleplay/Character](Character.md), [Roleplay/Game](Game.md), [Roleplay/Rule](Rule.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [mechanic-plan-05.md](../../mechanic-plan-05.md) | план | `~ПЛАН~` | — |
| [mechanic-plan-06.md](../../mechanic-plan-06.md) | план | `~В РАБОТЕ~` | — |
| [mechanic-plan-01.md](../../mechanic-plan-01.md) | план | `~ЧАСТИЧНО~` | 2026-09-05 |
| [mechanic-plan-02.md](../../mechanic-plan-02.md) | план | `~ЧАСТИЧНО~` | 2026-09-06 |
| [mechanic-plan-04.md](../../mechanic-plan-04.md) | план | `~ЧАСТИЧНО~` | — |
| [mechanic-plan-03.md](../../mechanic-plan-03.md) | план | `~СДЕЛАНО~` | 2026-09-07 |
| [mechanic-plan-07.md](../../mechanic-plan-07.md) | план | `~СДЕЛАНО~` | 2026-10-07 |
| [mechanic-roadmap.md](../../mechanic-roadmap.md) | план | `~СДЕЛАНО~` | 2026-10-07 |

