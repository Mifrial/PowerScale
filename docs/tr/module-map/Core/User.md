# Core/User

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/User --zone=tr`.

- **Путь:** `modules/Core/User`
- **Неймспейс:** `Mifrial\Core\User`
- **Контейнер:** `Mifrial\Core\User\Container\UserContainer`
- **Локатор:** `Mifrial\Core\User\Interface\Container\IUserContainer`
- **Setup:** `Mifrial\Core\User\Setup\UserModuleSetup`
- **Канон:** не зафиксирован

## Порты

- `Mifrial\Core\User\Interface\Service\IUserAccounts`
- `Mifrial\Core\User\Interface\Service\IUserGroups`
- `Mifrial\Core\User\Interface\Service\IUserViews`
- `Mifrial\Core\User\Interface\Service\IUserAccess`
- `Mifrial\Core\User\Service\UserHttpService`
- `Mifrial\Core\User\Action\FindUserPageAction`
- `Mifrial\Core\User\Action\GetUserAction`
- `Mifrial\Core\User\Action\GetUsersByIdsAction`
- `Mifrial\Core\User\Action\UpdateUserAction`
- `Mifrial\Core\User\Action\DeactivateUserAction`
- `Mifrial\Core\User\Service\GroupHttpService`
- `Mifrial\Core\User\Action\FindGroupPageAction`
- `Mifrial\Core\User\Action\GetGroupAction`
- `Mifrial\Core\User\Action\GetGroupMembersAction`
- `Mifrial\Core\User\Action\CreateGroupAction`
- `Mifrial\Core\User\Action\UpdateGroupAction`
- `Mifrial\Core\User\Action\DeactivateGroupAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `user.findPage` | `FindUserPageAction` | да |
| `user.get` | `GetUserAction` | да |
| `user.getByIds` | `GetUsersByIdsAction` | да |
| `user.update` | `UpdateUserAction` | да |
| `user.deactivate` | `DeactivateUserAction` | да |
| `userGroup.findPage` | `FindGroupPageAction` | да |
| `userGroup.get` | `GetGroupAction` | да |
| `userGroup.getMembers` | `GetGroupMembersAction` | да |
| `userGroup.create` | `CreateGroupAction` | да |
| `userGroup.update` | `UpdateGroupAction` | да |
| `userGroup.deactivate` | `DeactivateGroupAction` | да |

## Контракты `Interface/`

- `Interface/Container/IUserContainer.php` — Публичный контейнер модуля User.
- `Interface/Service/IUserAccess.php` — Guards HTTP учётки по снимку актора.
- `Interface/Service/IUserAccounts.php` — Фасад учётки для соседей: профиль, без схемы и без таблиц.
- `Interface/Service/IUserGroups.php` — Фасад групп и членства для соседей: без схемы и без таблиц.
- `Interface/Service/IUserViews.php` — JSON-профиль User для HTTP и Auth.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md), [Core/SmartTable](SmartTable.md)
- **От него зависят:** [Core/Auth](Auth.md), [Core/Logger](Logger.md), [Messages/Chat](../Messages/Chat.md), [Roleplay/Character](../Roleplay/Character.md), [Roleplay/Game](../Roleplay/Game.md), [Roleplay/Keyword](../Roleplay/Keyword.md), [Roleplay/Mechanic](../Roleplay/Mechanic.md), [Roleplay/RuleSpace](../Roleplay/RuleSpace.md)

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [user-plan-01-account.md](../../user-plan-01-account.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [user-plan-02-groups.md](../../user-plan-02-groups.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [user-plan-03-http.md](../../user-plan-03-http.md) | план | `~СДЕЛАНО~` | 2026-09-03 |
| [user-plan-04-groups-http.md](../../user-plan-04-groups-http.md) | план | `~СДЕЛАНО~` | 2026-09-03 |
| [user-plan-05-no-catalog-dump.md](../../user-plan-05-no-catalog-dump.md) | план | `~СДЕЛАНО~` | 2026-09-03 |
| [user-plan-06-members-page.md](../../user-plan-06-members-page.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [user-plan-07-member-unique.md](../../user-plan-07-member-unique.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [user-plan-08-member-count-aggregate.md](../../user-plan-08-member-count-aggregate.md) | план | `~СДЕЛАНО~` | 2026-09-05 |
| [user-roadmap.md](../../user-roadmap.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [user.md](../../user.md) | канон | ? | 2026-09-03 |

