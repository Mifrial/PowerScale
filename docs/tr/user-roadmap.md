# Нарезка Core/User

**Статус:** план реализации, 2026-09-02. Канон учётки — [`user.md`](user.md). UI/сессия — [`auth-system.md`](auth-system.md). SmartTable Basic закрыт.

Порядок сверху вниз. Auth не стартует раньше плана 1 (нужна учётка с required `login`). Способы входа — таблица Auth, не User.

## 0. Сделано

- Ворота SmartTable Basic; сосед не зовёт `open` — фабрика User отдаёт порты.
- План 1: модуль `Core/User`, таблица `user`, публичный фасад `IUserAccounts`.
- Разрез `IOpenedTable`: `schema()` / `records()`, `getUnique` / `getFirst` над `ListQuery`.

## 1. Учётка в БД — сделано

Подробно: [`user-plan-01-account.md`](user-plan-01-account.md).

## 2. Группы и членство — сделано

Подробно: [`user-plan-02-groups.md`](user-plan-02-groups.md). Таблица `user_group` (права mfv string, `bypass`), таблица членства с `member_key` (составной unique ST v1 нет). Фасад `IUserGroups`. Не HTTP, не seed проды.

## 3. HTTP User — сделано

Подробно: [`user-plan-03-http.md`](user-plan-03-http.md). Actions `IUserApi`; актор сессии на `IRequestContext` (binder Auth, User Auth не импортирует); `user.create` в Auth из‑за пароля. Не `user_group.*`.

## 4. HTTP групп — сделано

Подробно: [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md). `userGroup.*` как у `IGroupApi`, `memberCount`, «назначать только свои ключи».

## 5. Auth — сделано

Подробно: [`auth-plan-01-session.md`](auth-plan-01-session.md). Сессия, cookie, `auth.login` / logout / register / getCurrentUser. Таблица способов входа (`user_identity`) и `auth_session`. Клиент User: `IUserAccounts` + `hasBypass` / `getPermissionKeys` / `findByName`. Метка последнего входа — у Auth, не колонка `user`. Seed «Администраторы» / «Игрок» — data-шаг Auth. Не в модуле User.

## 6. Выправить контракт User/Auth — сделано

Подробно: [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md). `findPage` вместо dump, JSON-вид = Vue DTO, гость не User#0, `AUTH_REQUIRED` на 400. Политика пароля / `remember` / пагинация `getMembers` — после этого трека.

## 7. Auth — политика, remember, сброс — сделано

Подробно: [`auth-plan-02-password-flow.md`](auth-plan-02-password-flow.md). Политика пароля на группах (`auth_security_policy` + `auth_group_security_policy`), `remember` на Vue, `auth.startPasswordReset` / `auth.finalPasswordReset`, `auth.setPassword` (плагин на `/users/:id/edit`, право `auth.user.edit`). Нет `auth.findUser`. Почта сброса — [`auth-plan-03-mail-reset.md`](auth-plan-03-mail-reset.md). Пагинация `getMembers` — [`user-plan-06-members-page.md`](user-plan-06-members-page.md). Гость — [`auth-plan-04-guest.md`](auth-plan-04-guest.md).

## 8. Хвосты: гость и члены

Гость — [`auth-plan-04-guest.md`](auth-plan-04-guest.md) (**сделано**). Члены — [`user-plan-06-members-page.md`](user-plan-06-members-page.md) (**сделано**).

Подробно: [`core-tails-roadmap.md`](core-tails-roadmap.md).

## Параллелить нельзя

- Auth с отсутствием плана 1.
- SQL в User в обход SmartTable.
- Разворачивать прод-схему только из `UserSchema::install` без CLI Kernel ([`kernel-plan-01-setup.md`](kernel-plan-01-setup.md)).
