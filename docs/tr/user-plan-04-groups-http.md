# План User 4 — HTTP групп

**Статус:** сделано, 2026-09-03. Канон — [`user.md`](user.md). UI — [`auth-system.md`](auth-system.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). Актор — [`user-plan-03-http.md`](user-plan-03-http.md). Список — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md): `userGroup.findPage`, не `userGroup.getList`. Input-DTO — [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md) (**сделано**). Record — [`php-coding-standards.md`](php-coding-standards.md) (`DEC-079`). Конвейер — [`architecture.md`](architecture.md).

Цель: фронтовый `IGroupApi` ходит в боевые action. Guards — актор сессии (`IRequestContext`), User **не** импортирует Auth. Не Vue, не политика пароля. Create/update сразу на `IActionInput`.

На диске уже есть `IUserGroups` (`findPage`), `IUserAccess`, HTTP учётки, binder DTO. JSON групп + `memberCount` + «назначать только свои ключи».

## Термины

| Термин | Смысл |
|---|---|
| Action id | Имена как у Vue `GroupApi`: `userGroup.findPage` и т.д. Не `user_group.findPage`. |
| Ключ права | Каталог остаётся `user_group.view\|create\|edit\|deactivate`. |
| Свои ключи | Актор без `hasBypass` на create/update `permissions` может поставить/оставить только ключи из `RequestActor`. Bypass-актор — любой ключ по паттерну нормализатора. |

## Решения

### 1. Граница

**User** владеет всеми `userGroup.*`. Auth не трогаем.

HTTP-слой — отдельный сценарий в `Service/` (как `UserHttpService`), не пятый десяток методов на `IUserGroups`. Сборка JSON — геттеры `GroupRecord` / `UserRecord`, не `values()` (`DEC-079`). Репозиторий: список групп, COUNT членств пачкой (не колонка `memberCount`). `ListQuery` только в `Repository/`.

### 2. Actions (`IGroupApi`)

CSRF **true** на всех (как `user.*`). HTTP 401 нет: нет актора → `AUTH_REQUIRED` → 400.

Короткие чтения — скалярный `handle`, как `user.get`. Письмо с опциональными полями — один `IActionInput`.

| Action | `handle` | Успех `data` |
|---|---|---|
| `userGroup.findPage` | `FindPageInput` | `{ items: Group[], total }` |
| `userGroup.get` | `int $id` | объект `Group` |
| `userGroup.getMembers` | `GetGroupMembersInput` | `{ items: GroupMember[], total }` |
| `userGroup.create` | `CreateGroupInput` | `Group` |
| `userGroup.update` | `UpdateGroupInput` | `Group` |
| `userGroup.deactivate` | `int $id` | `null` — `active=false`, строки членства не delete |

`CreateGroupInput`: `string $name`, `array $permissions`; `OptionalBool` `active`, `assign_on_register` (форма Vue — только имя+ключи → absent → дефолты NewGroup: active true, assign false). Поля **`bypass` во входе нет.**

`UpdateGroupInput`: `int $id`; `OptionalString $name`; `OptionalArray $permissions`; `OptionalBool $active`, `$assign_on_register`. Как `UpdateGroupData`: name / permissions / active. **`bypass` во входе нет.** Лишний ключ `bypass` → `INVALID_PARAMS`. Ни один Optional не present → `USER_INVALID` (пустой patch). HTTP-сценарий не кладёт `bypass` в `GroupInputNormalizer` (ключ нормализатора остаётся для seed/фасада).

`USER_LAST_BYPASS` с фасада не маскировать (deactivate / `active=false` у живой последней bypass-группы — отказ, группа на месте).

### 3. Guards

| Action | Кто |
|---|---|
| findPage, get, getMembers | `user_group.view` (или bypass) |
| create | `user_group.create` |
| update | `user_group.edit` |
| deactivate | `user_group.deactivate` |

Нет «self» у группы. Свой профиль сюда не ходит. Нет актора → `AUTH_REQUIRED`. Нет ключа → `AUTH_DENIED`. Нет группы при праве view → `USER_NOT_FOUND`. Без view чужой/любой id — DENIED, не маскировать 404.

**Ключи прав в payload:** без `hasBypass` итоговый набор ⊆ ключи актора. Выдать чужой ключ → `AUTH_DENIED`. Снять ключ, которого у актора нет → тоже DENIED. Сравнение в HTTP-сценарии групп (`RequestActor::getPermissionKeys()`), не новый метод на `IUserAccess`. Bypass-актор — паттерн нормализатора, каталог PHP не закрываем. Нет ключа `permissions` на update — набор не трогать.

**`bypass` на группе:** HTTP create/update **не принимают** флаг. Пишет seed/setup (`BootstrapGroupsStep`). JSON ответа отдаёт `bypass` для чтения. Снять живой bypass этим планом — только `active=false` / deactivate (LAST_BYPASS на фасаде), не ключ `bypass: false`.

**`assign_on_register`:** опциональный ключ create/update; достаточно `user_group.create` / `edit`. Несколько групп с флагом ок. Vue-форма ключ не шлёт — не дефект этого захода.

### 4. JSON

Контракт ответа **сервера**, не копия Vue-эскиза. `Group`: `id`, `name`, `active`, `memberCount`, `permissions` (сорт), `createdAt` unix int UTC, **плюс** `bypass`, `assignOnRegister` **на чтении** (админке). Ключи входа create/update — camelCase JSON (`assignOnRegister`); колонка ST — `assign_on_register`.

`GroupMember`: `id`, `name`, `login`. Без `initials` в JSON; Vue считает тем же `initials()`. Email не светить.

`memberCount`: COUNT по `user_group_member` на пачку id групп из `findPage`/`get`. Публичного GROUP BY у SmartTable нет: `getList` членств `filter group_id in`, **страницы по 500 до пустой**, агрегат в PHP (`getCountsByGroupIds` в репозитории). Нет строк у группы — 0. Лимит **500 групп** на странице. Страница членов — [`user-plan-06-members-page.md`](user-plan-06-members-page.md).

`getMembers`: `findMemberPage` + `IUserAccounts::getByIds` (один IN), не N×`getById`. Нет учётки по id членства — пропуск, не 404 всего списка.

Лимит страницы групп: 500, `id` asc, фильтр `q` / `active` (план 5).

### 5. Фасад (additive, узко)

`IUserGroups::findPage(...)` — HTTP сетки; ST `getList` только в репозитории. Auth не обязан звать страницу.

Пачка COUNT — только репозиторий членства, не публичный порт.

Deactivate HTTP = `update` + `GroupPatch` `active => false`, не `delete` группы и не `removeMember`.

### 6. Слои

| Тип | Задача | Не делает |
|---|---|---|
| Actions | csrf; скаляр или один input | `ActionResponse`, `open` |
| `Dto/Action/` | `CreateGroupInput` / `UpdateGroupInput` | колонки, Record |
| HTTP-сценарий групп | guard, нормализатор, JSON, subset ключей | `ListQuery`, писать `bypass`, `initials` |
| `IUserGroups` | findPage/add/update; LAST_BYPASS | HTTP |
| `IUserAccess` | `requireKey` | каталог ключей как enum, subset permissions, входной bypass |

## Ошибки

| Код | Когда |
|---|---|
| `AUTH_REQUIRED` / `AUTH_DENIED` | нет актора / нет ключа / чужие permissions |
| `USER_NOT_FOUND` | нет группы (при праве view) |
| `USER_INVALID` | payload (имя, ключи, типы input) |
| `INVALID_PARAMS` | лишний ключ, в т.ч. `bypass` на create/update |
| `USER_DUPLICATE` | имя группы |
| `USER_LAST_BYPASS` | выключить живой последний bypass (`active=false` / deactivate) |

## Todo

- [x] **list-count** — `IUserGroups::getList` + COUNT членств пачкой; mysql.
- [x] **json-members** — JSON Group / GroupMember / initials; unix `createdAt`; геттеры Record.
- [x] **guard-keys** — таблица §3; subset permissions в HTTP-сценарии.
- [x] **action-input** — `CreateGroupInput` / `UpdateGroupInput`; чтения скалярами.
- [x] **actions** — шесть `userGroup.*`, csrf; deactivate = `active=false`.
- [x] **quality** — phpcs-quality + phpunit `kernel,user,auth`.

## Не входит

Vue / смена имён action и типа `createdAt` на фронте. Политика пароля. `remember`. HTTP 401. Транзакции. Object-ACL / `can_edit` на группе. Каталог ключей как enum. Delete группы. Пагинация >500. Seed новых групп. Проводка `assign_on_register` в Vue-форму. Запись `bypass` с HTTP / чекбокс bypass в форме. Новый метод ACL «subset ключей» на `IUserAccess`. GROUP BY / fluent ST.

## Документы захода

этот файл; [`user.md`](user.md); [`user-roadmap.md`](user-roadmap.md); [`user-plan-02-groups.md`](user-plan-02-groups.md); [`user-plan-03-http.md`](user-plan-03-http.md); [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md); [`auth-system.md`](auth-system.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).

## Альтернативы, если этот план не берём

1. Выправить контракт (блокер) — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md).
2. **Auth: таблица политики пароля** — только после этапа D плана 5.
3. **`remember` во `IAuthApi` / Vue** — только если спросят, после D.
