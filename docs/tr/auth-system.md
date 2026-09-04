# Пользователи и авторизация

**Статус:** каноническая документация поведения frontend; backend-детали помечены `OPEN`, если не подтверждены контрактом.

## Пользователь и группы

Пользователь имеет профиль и может состоять в нескольких группах. Права групп суммируются. Система различает владельца объекта, участника контекста и пользователя без доступа.

Описанные области прав:

- пользователи и группы;
- пространства и правила;
- признаки и шаблоны уведомлений;
- персонажи;
- игры;
- чаты.

Ключ права должен быть стабильным, человекочитаемым и проверяться одинаково для одиночного и batch-запроса. Bypass ACL — флаг `bypass` **не больше чем на одной** группе ([`user-plan-02-groups.md`](user-plan-02-groups.md)); колонки `super_admin` на `user` нет. Нельзя оставить систему без членства в активной bypass-группе (`USER_LAST_BYPASS`).

## Проверка доступа

Проверка учитывает:

1. аутентифицированного пользователя;
2. глобальное право или bypass группы;
3. владение объектом;
4. членство и роль в контексте;
5. object-level permission;
6. действие над объектом.

Effective permissions должны вычисляться на backend. Frontend отображает доступные действия, но не является границей безопасности.

Точная матрица наследования прав, формат batch endpoint и backend-хранение object permissions — `OPEN`; старые плоские frontend-права не считать окончательной backend-моделью.

## Сессия и безопасность

Текущие frontend-решения:

- сессионная аутентификация использует httpOnly cookie;
- CSRF защищает state-changing запросы;
- password policy предоставляется через API;
- async-запросы имеют loading/error/retry-состояния;
- отменяемые операции используют `AbortController`.

Регистрация открыта. Вход принимает login или email и пароль. Forgot — один action `auth.startPasswordReset` (login, иначе email): в `data` статусы `not_found` / `no_email` / `sent` (+ `login`; email в JSON нет). Публичного `auth.findUser` нет. Завершение сброса — `auth.finalPasswordReset` (`login`, `resetToken`, `newPassword`); автологина нет. Политика пароля — `auth.getPasswordPolicy` (каталог `auth_security_policy`, связь с группой `auth_group_security_policy`; эффективная = наибольшая среди активных групп, иначе default). Сессия по умолчанию живёт 24 часа, `remember me` — 30 дней; logout инвалидирует server-side session. Reset-токен и session token в БД только как хеш.

Регистрация, редактирование профиля, деактивация и guest entry являются отдельными сценариями UI. Их backend-правила должны быть описаны только после сверки с фактическим API.

## Деактивация

Деактивация выполняется диалогом внутри `UserProfilePage`. Отдельная страница и маршрут `/users/:id/deactivate` не являются текущим контрактом (`DEC-057`).

## Подтверждённые frontend routes и операции

Core/Auth предоставляет маршруты:

- `/login`;
- `/register`;
- `/forgot-password`;
- `/reset-password`.

Logout не является отдельной страницей или frontend route: это authenticated action `auth.logout`, который запускается через confirmation dialog в shell. После подтверждения UI дожидается action, показывает loading и переводит на `/login`.

Core/User предоставляет административные маршруты списка, создания, просмотра и редактирования users и groups. Точные guards и состав вложенных routes должны сверяться с `Core/User/routes.ts`; backend route contract — `OPEN`.

Route access:

- `/users` — `user.view`;
- `/users/new` — `user.create`;
- `/users/:id` — `user.view`, владелец может видеть свой профиль;
- `/users/:id/edit` — владелец или `user.edit` (`auth.user.edit` в meta **не** добавлять);
- блок пароля на edit — владелец **этой** учётки или ключ `auth.user.edit` (bypass = ключ есть); одного `user.edit` на чужом профиле недостаточно;
- `/admin/groups` и descendants — соответствующий `user_group.*`;
- `/admin/keywords` и descendants — соответствующий `keyword.*`;
- `auth.logout` — authenticated user, POST action из logout confirmation dialog.

## Поля пользователя

В текущем контуре JSON User: `id`, `login`, `email` (`string|null`), display name fields, `registered` / `lastLogin` unix, `bypass`, `permissions`, `groups` (id). Строка `user` — [`user.md`](user.md): нет `super_admin` и `last_login_at`. Ключи API — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md).

Первый администратор создаётся при установке и состоит в bypass-группе; его нельзя снять как последнего члена активных bypass-групп. Новому пользователю при пустом `groups` назначаются группы с `assign_on_register` (seed кладёт флаг на «Игрок»). Backend seed и сессия — [`auth-plan-01-session.md`](auth-plan-01-session.md) (не план User 2).

## Permission invariants

При создании и редактировании группы оператору требуется право назначать права группам. Даже при наличии этого права он может назначить или снять только те permissions, которыми сам обладает (HTTP + текущий пользователь; не репозиторий плана 2). Член bypass-группы проходит ACL целиком; снять флаг / последнее такое членство нельзя, если это оставит ноль членств в активных bypass-группах. Проверка ограничения выполняется на backend; frontend UI не является границей безопасности.

Владелец может работать со своим профилем и персонажем без глобального права на чужой объект. Для чужого объекта необходимы соответствующие permission keys. Роль `owner` игры даёт game-management права, `gm` — moderation/edit права, `player` не получает дополнительных прав только из роли.

При списочном запросе нельзя делать N отдельных permission-запросов: effective access должен вычисляться пакетно, а frontend получает computed flags вроде `can_edit`/`can_delete`. SQL/JOIN реализация — backend `OPEN`.

## Guest

Гость — сессия Auth **без** `user_id` и без объекта User. На Vue: `session.kind === 'guest'`, `currentUser === null`, флаг `guestActor` (не `User { id: 0 }`). Гость видит публичные pages / games / spaces / chats с `meta.guestAllowed`; не создаёт/не правит users, rules, characters, games, spaces и не пишет в Chat. HTTP без актора на защищённом action → `AUTH_REQUIRED` (400); клиент уводит на `/login` по коду. CSRF 403 не считается выходом.

## Profile and avatar

Владелец может менять собственные login, email, имя, фамилию, nickname и avatar, но не группы. Пароль — identity Auth: `auth.setPassword` (на `/users/:id/edit` — отдельный submit плагина Auth, не поле `user.update`). Чужой пароль — ключ **`auth.user.edit`**, не `user.edit`. Пользователь с `user.edit` может менять группы профиля. Заполненный email уникален. Avatar upload принимает PNG/JPG до 2 MB; storage и image processing относятся к `data-model.md` и backend `OPEN`.

## Permission catalog semantics

`user.*`, `user_group.*`, `keyword.*`, `notification_template.*` — глобальные административные области. `auth.user.edit` — смена чужого пароля (категория Auth, не User). `space.*` и `rule.*` применяются в контексте пространства; `game.*` — в контексте игры; `chat.*` — в контексте чата. Группы, keywords и notification templates soft-deactivate вместо физического удаления.

По legacy-требованию новая учётная запись получает группы с `assign_on_register` (seed — «Игрок»), а bypass-группа (имя вроде «Администраторы») даёт полный обход ACL, не обязательно полный каталог ключей в строке. Инициализация групп — data-шаг [`auth-plan-01-session.md`](auth-plan-01-session.md).

## Ordered access algorithm

```text
if bypassGroup(user): allow
permissions = globalPermissions(user)
if objectId: permissions += objectPermissions(user, objectType, objectId)
if action in permissions: allow
if isOwner(user, objectType, objectId): allow
deny
```

Для game membership роль `owner` даёт `game.edit`, `game.moderate`, `game.manage`; `gm` даёт `game.edit`, `game.moderate`; `player` не получает дополнительных прав только из роли. Эти role-derived права действуют без дублирующей записи в `game_member_permissions`.

Для batch-страниц используется один запрос вроде `getUsersByIds(ids[])`/JOIN-фильтр и computed flags (`can_edit`, `can_delete`), а не N permission requests. Конкретный endpoint и backend JOIN — `OPEN`.
