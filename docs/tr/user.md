# User (backend)

**Статус:** канон серверного модуля учётки, 2026-09-02. Поведение UI — [`auth-system.md`](auth-system.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). Данные только через SmartTable. Owner [`architecture.md`](architecture.md).

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

Хеш пароля и внешние id **не** в строке `user` и **не** в `UserRecord`. Их хранит Auth (таблица вроде `user_identity`: `user_id` → `UserTable` restrict, `kind`, unique `identity_key` `{kind}:{external}`, `secret_hash` только для password). Проверка секрета — Auth. User отдаёт профиль по id/login/email; создание учётки — без plaintext пароля.

Последний вход — факт Auth, не колонка `user`. Сетка/фильтр «last login» — когда появится Auth (денорм или отдельная метка; User не импортирует Auth).

`super_admin` на профиле нет. Bypass ACL — флаг `bypass` **не больше чем на одной** группе (мешок членств, ключи могут быть пусты) и инвариант «нельзя снять последнего» (план 2), не bool на человеке.

## Группы

Таблицы модуля User (не Auth): `user_group`, `user_group_member`. Ключи прав — multiple string на группе; членство — отдельная строка (`member_key` unique, два reference). Каталог ключей и object-ACL — не строка `user`. Подробно — [`user-plan-02-groups.md`](user-plan-02-groups.md).

Аватар **не** колонка плана 1 и **не** `int avatar_file_id`. Когда будет Files: поле `avatar`, тип SmartTable `file` / `picture` (картинка — ограничение на файле, не сырой id). Физика может быть INT FK; API — не int. Пока типа нет — колонки нет (`updateTable` потом добавит).

HTTP админки учётки — [`user-plan-03-http.md`](user-plan-03-http.md): User не импортирует Auth; пароль на `user.create` пишет Auth.

## Ошибки

Листья `UserException`: `USER_NOT_FOUND`, `USER_DUPLICATE` (login / email / имя группы / членство), `USER_INVALID`, `USER_LAST_BYPASS` (план 2). Unique SmartTable наружу не протекает.
