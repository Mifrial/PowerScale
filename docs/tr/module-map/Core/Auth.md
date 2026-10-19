# Core/Auth

**СГЕНЕРИРОВАНО.** Не редактировать вручную.
Команда: `composer maps` (из `www/mifrial`); сверка — `composer maps-check`.
Источник: `module.config.php`, `Interface/`, импорты модуля; планы — `doc-index.json`.
Карточка небольшая: открывать можно. Детали по планам — `show.php --module=Core/Auth --zone=tr`.

- **Путь:** `modules/Core/Auth`
- **Неймспейс:** `Mifrial\Core\Auth`
- **Контейнер:** `Mifrial\Core\Auth\Container\AuthContainer`
- **Локатор:** `Mifrial\Core\Auth\Interface\Container\IAuthContainer`
- **Setup:** `Closure(IServiceLocator)`
- **`request_bind`:** `AuthSessionBinder`
- **Карта входа:** [README.md](../../../../www/mifrial/modules/Core/Auth/README.md)
- **Канон:** раздел «Карта доверия: стабильные модули Core» в [contract-status.md](../../contract-status.md)

## Порты

- `Mifrial\Core\Auth\Service\AuthService`
- `Mifrial\Core\Auth\Service\UserCreateService`
- `Mifrial\Core\Auth\Service\AuthSessionBinder`
- `Mifrial\Core\Auth\Action\LoginAction`
- `Mifrial\Core\Auth\Action\RegisterAction`
- `Mifrial\Core\Auth\Action\LogoutAction`
- `Mifrial\Core\Auth\Action\GetCurrentUserAction`
- `Mifrial\Core\Auth\Action\GuestAction`
- `Mifrial\Core\Auth\Action\GetPasswordPolicyAction`
- `Mifrial\Core\Auth\Action\UserCreateAction`
- `Mifrial\Core\Auth\Service\PasswordResetService`
- `Mifrial\Core\Auth\Action\StartPasswordResetAction`
- `Mifrial\Core\Auth\Action\FinalPasswordResetAction`
- `Mifrial\Core\Auth\Service\SetPasswordService`
- `Mifrial\Core\Auth\Action\SetPasswordAction`

## Маршруты

| Action | Handler | CSRF |
|---|---|---|
| `auth.login` | `LoginAction` | нет |
| `auth.register` | `RegisterAction` | нет |
| `auth.logout` | `LogoutAction` | да |
| `auth.getCurrentUser` | `GetCurrentUserAction` | нет |
| `auth.guest` | `GuestAction` | нет |
| `auth.getPasswordPolicy` | `GetPasswordPolicyAction` | нет |
| `user.create` | `UserCreateAction` | да |
| `auth.startPasswordReset` | `StartPasswordResetAction` | нет |
| `auth.finalPasswordReset` | `FinalPasswordResetAction` | нет |
| `auth.setPassword` | `SetPasswordAction` | да |

## Контракты `Interface/`

- `Interface/Container/IAuthContainer.php` — Публичный контейнер модуля Auth.
- `Interface/Service/IPasswordResetNotifier.php` — Доставка сырого reset-токена.

## Зависимости

- **Зависит от:** [Core/Kernel](Kernel.md), [Core/Mail](Mail.md), [Core/SmartTable](SmartTable.md), [Core/User](User.md)
- **От него зависят:** —

## События

—

## Планы и документы

| Документ | Тип | Состояние | Дата |
|---|---|---|---|
| [auth-plan-01-session.md](../../auth-plan-01-session.md) | план | `~СДЕЛАНО~` | 2026-09-02 |
| [auth-plan-02-password-flow.md](../../auth-plan-02-password-flow.md) | план | `~СДЕЛАНО~` | 2026-09-03 |
| [auth-plan-03-mail-reset.md](../../auth-plan-03-mail-reset.md) | план | `~СДЕЛАНО~` | 2026-09-03 |
| [auth-plan-04-guest.md](../../auth-plan-04-guest.md) | план | `~СДЕЛАНО~` | 2026-09-04 |
| [auth-system.md](../../auth-system.md) | канон | ? | — |

