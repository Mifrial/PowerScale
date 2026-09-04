# User (backend)

**Статус:** канон серверного модуля учётки, 2026-09-03. Поведение UI — [`auth-system.md`](auth-system.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). Данные только через SmartTable. Owner [`architecture.md`](architecture.md).

`Core/User` владеет **учёткой**: кто человек (профиль, группы, права). **Сессия, cookie, login/logout и способы входа — Auth.** User не импортирует Auth. Auth — клиент `IUserAccounts` и (после плана 2) `IUserGroups`.

Guest — **не** строка `user` (сессия Auth без `user_id`). Extranet — когда понадобится, additive (тип/группа/таблица), не `is_guest` на учётке. Этот сайт extranet не включает.

Имя: `user` ← `UserTable`. Таблицы пароля/VK в этом модуле нет.

## Учётка `user`

Стабильный профиль. Новый способ входа **не** добавляет колонок сюда и **не** ставит таблицу в User.

| Поле | Тип SmartTable | Заметки |
|---|---|---|
| `id` | id | системный |
| `login` | string unique required | человекочитаемый вход и ссылка «кто»; VK позже задаёт login при создании учётки (свой или из id), не nullable |
| `email` | string unique | не required в Core (VK без почты); пустое хранить как `null`, не `''` |
| `name` | string required | фронт `name` |
| `surname` | string | |
| `nickname` | string | |
| `active` | bool required | default true |
| `registered_at` | datetime required | UTC; default «сейчас» на add без ключа (`DateTimeNow`) |
| `deactivated_until` | datetime | |
| `deactivate_reason` | text | |

## Граница с Auth

Хеш пароля и внешние id **не** в строке `user` и **не** в `UserRecord`. Их хранит Auth (таблица вроде `user_identity`: `user_id` → `UserTable` restrict, `kind`, unique `identity_key` `{kind}:{external}`, `secret_hash` только для password). Проверка секрета — Auth. Смена пароля — `auth.setPassword` (себе — текущий пароль; чужому — bypass или ключ **`auth.user.edit`**, не `user.edit`). User отдаёт профиль по id/login/email; создание учётки — без plaintext пароля на фасаде User (`user.create` собирает Auth).

Последний вход — факт Auth, не колонка `user`. HTTP `user.findPage` / `user.get` ключ `lastLogin` **не** кладут. Login / `getCurrentUser` — unix int, если Auth передал `last_used_at`. Сетка учёток не фильтрует last login.

`super_admin` на профиле нет. Bypass ACL — флаг `bypass` **не больше чем на одной** группе (мешок членств, ключи могут быть пусты) и инвариант «нельзя снять последнего» (план 2), не bool на человеке.

## Группы

Таблицы модуля User (не Auth): `user_group`, `user_group_member`. Ключи прав — multiple string на группе; членство — отдельная строка, составной unique `(user_id, group_id)`. `assign_on_register` — автовыдача при `auth.register` и `user.create` с пустым `groups` (Auth читает `IUserGroups::getAssignOnRegisterIds()`; «Игрок» — контент seed). Каталог ключей и object-ACL — не строка `user`. Подробно — [`user-plan-02-groups.md`](user-plan-02-groups.md), хвост суррогата — [`user-plan-07-member-unique.md`](user-plan-07-member-unique.md).

HTTP `user.create` / `user.update`: поле `groups` — **id групп** (`int[]`), не отображаемые имена.

`UserRecord` / `GroupRecord` — геттеры смысла (`getLogin()`, `isBypass()`), не мешок ключей. `NewUser` / `UserPatch` / `NewGroup` / `GroupPatch` — присутствующие ключи (`fields()`). Сборка JSON User — `IUserViews`, не Record (`DEC-079`).

Аватар **не** колонка плана 1 и **не** `int avatar_file_id`. Когда будет Files: поле `avatar`, тип SmartTable `file` / `picture` (картинка — ограничение на файле, не сырой id). Физика может быть INT FK; API — не int. Пока типа нет — колонки нет (`updateTable` потом добавит).

HTTP админки учётки — [`user-plan-03-http.md`](user-plan-03-http.md) и [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md): страница `user.findPage` (`{ items, total }`), не dump `getList`. JSON User — `IUserViews`: `email` `string|null`, `bypass`, unix-даты, camelCase `deactivatedUntil` / `deactivateReason`, без `super_admin` / `avatar_file_id` / `initials`. Вход deactivate: `deactivatedUntil` как **`Y-m-d`**; в ответе — unix. Гость — сессия Auth без `user_id`, не строка User и не `id: 0`. `AUTH_REQUIRED` → HTTP 400; клиент редиректит на login по **коду**, CSRF 403 не вылогинивает.

HTTP групп — [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md): `userGroup.findPage`, JSON `bypass` / `assignOnRegister` только на чтении; `GroupMember` без `initials`. Страница членов — [`user-plan-06-members-page.md`](user-plan-06-members-page.md).

## Ошибки

Листья `UserException`: `USER_NOT_FOUND`, `USER_DUPLICATE` (login / email / имя группы / членство), `USER_INVALID`, `USER_LAST_BYPASS` (план 2). Unique SmartTable наружу не протекает.
