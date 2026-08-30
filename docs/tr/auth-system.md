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

Ключ права должен быть стабильным, человекочитаемым и проверяться одинаково для одиночного и batch-запроса. Super-admin имеет bypass согласно действующему policy.

## Проверка доступа

Проверка учитывает:

1. аутентифицированного пользователя;
2. глобальное право или super-admin;
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

Регистрация открыта. Вход принимает login или email и пароль. Reset сначала ищет по login, затем по email; если email отсутствует, пользователь получает понятное сообщение без раскрытия лишних данных. Сессия по умолчанию живёт 24 часа, `remember me` — 30 дней; logout инвалидирует server-side session. Refresh/reset tokens хранятся только в хешированном виде.

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
- `/users/:id/edit` — владелец или `user.edit`;
- `/admin/groups` и descendants — соответствующий `user_group.*`;
- `/admin/keywords` и descendants — соответствующий `keyword.*`;
- `auth.logout` — authenticated user, POST action из logout confirmation dialog.

## Поля пользователя

В текущем контуре используются `id`, `login`, `email`, display name fields, registration metadata и состояние активности. Legacy schema дополнительно фиксирует `first_name`, `last_name`, `nickname`, `avatar_file_id`, `super_admin`, `deactivated_until` и `deactivate_reason`; эти поля являются `REQUIREMENT` до подтверждения backend.

Супер-администратор создаётся при установке, состоит в группе администраторов и не может быть удалён или лишён этой группы. Новому пользователю по legacy-требованию назначается группа `Игрок`; наличие инициализации этой группы в backend — `OPEN`.

## Permission invariants

При создании и редактировании группы оператору требуется право назначать права группам. Даже при наличии этого права он может назначить или снять только те permissions, которыми сам обладает. Администратор имеет полный набор прав по умолчанию; этот bypass нельзя изменить или отозвать. Проверка ограничения выполняется на backend; frontend UI не является границей безопасности.

Владелец может работать со своим профилем и персонажем без глобального права на чужой объект. Для чужого объекта необходимы соответствующие permission keys. Роль `owner` игры даёт game-management права, `gm` — moderation/edit права, `player` не получает дополнительных прав только из роли.

При списочном запросе нельзя делать N отдельных permission-запросов: effective access должен вычисляться пакетно, а frontend получает computed flags вроде `can_edit`/`can_delete`. SQL/JOIN реализация — backend `OPEN`.

## Guest

Guest session создаётся без регистрации и пароля, действует до закрытия браузера и не переносит временные просмотры после регистрации. Гость может видеть только публичные pages, games, spaces и chats; не может создавать/редактировать users, rules, characters, games или spaces и не может писать в Chat.

## Profile and avatar

Владелец может менять собственные login, password, email, имя, фамилию, nickname и avatar, но не группы. Пользователь с `user.edit` может менять также группы. Заполненный email уникален. Avatar upload принимает PNG/JPG до 2 MB; storage и image processing относятся к `data-model.md` и backend `OPEN`.

## Permission catalog semantics

`user.*`, `user_group.*`, `keyword.*`, `notification_template.*` — глобальные административные области. `space.*` и `rule.*` применяются в контексте пространства; `game.*` — в контексте игры; `chat.*` — в контексте чата. Группы, keywords и notification templates soft-deactivate вместо физического удаления.

По legacy-требованию новая учётная запись получает группу `Игрок`, а группа `Администраторы` имеет полный набор прав. Инициализация этих групп backend-ом должна быть подтверждена отдельно.

## Ordered access algorithm

```text
if super_admin: allow
permissions = globalPermissions(user)
if objectId: permissions += objectPermissions(user, objectType, objectId)
if action in permissions: allow
if isOwner(user, objectType, objectId): allow
deny
```

Для game membership роль `owner` даёт `game.edit`, `game.moderate`, `game.manage`; `gm` даёт `game.edit`, `game.moderate`; `player` не получает дополнительных прав только из роли. Эти role-derived права действуют без дублирующей записи в `game_member_permissions`.

Для batch-страниц используется один запрос вроде `getUsersByIds(ids[])`/JOIN-фильтр и computed flags (`can_edit`, `can_delete`), а не N permission requests. Конкретный endpoint и backend JOIN — `OPEN`.
