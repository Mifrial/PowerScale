# План User 3 — HTTP учётки и актор запроса

**Статус:** план, 2026-09-02. Канон учётки — [`user.md`](user.md). UI — [`auth-system.md`](auth-system.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). Сессия — [`auth-plan-01-session.md`](auth-plan-01-session.md). Конвейер — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: фронтовый `IUserApi` ходит в боевые `user.*`. Guards смотрят **актора сессии**, не mock. User **по-прежнему не импортирует Auth**. Пароль на создании учётки пишет Auth. Не `user_group.*`, не object-ACL, не смена пароля профиля, не Vue.

На диске уже есть фасады `IUserAccounts` / `IUserGroups` и Auth-сессия. Это HTTP + снимок актора на процесс.

## Термины

| Термин | Смысл |
|---|---|
| Актор | Снимок на запрос: `userId`, `permissionKeys`, `hasBypass`. Не полный JSON User. |
| Binder | Порт модуля (`IRequestBinder` в Kernel): после cookie, до dispatch, кладёт актора в `IRequestContext`. Реализация — Auth. |
| Guard | Проверка «есть актор / ключ / владелец» в HTTP-слое User (и в `user.create` у Auth). Не middleware «на все action». |

HTTP **не** 401 (как Auth 1). Нет актора / нет права → `ActionException` `AUTH_REQUIRED` / `AUTH_DENIED`, HTTP **400**. 403 по-прежнему только CSRF.

## Решения

### 1. Граница модулей

**User** владеет `user.getList` / `user.get` / `user.getByIds` / `user.update` / `user.deactivate` и сборкой JSON профиля (кроме `lastLogin`). Additive на фасаде: список и пачка id. Не читает `user_identity` / `auth_session`.

**Auth** владеет сессией и **`user.create`**: фронт шлёт пароль; identity живёт в Auth; цикл `User → Auth` не заводим. Register уже создаёт identity — admin-create тот же секрет, без новой сессии на созданного.

**Kernel** не знает login/пароль. Additive:

1. DTO актора + поля на `IRequestContext` (`actor()` / `setActor()`, `reset()` сбрасывает актора вместе с cookie).
2. Контракт `IRequestBinder` (один метод `bind(IRequestContext): void`).
3. Ключ модуля `request_bind` → class-string порта в **этом** контейнере. `Application::handle` после CSRF, до `dispatch`: для каждого загруженного модуля с ключом — `get` порта, если это `IRequestBinder` — вызвать. Нет ключа — нет вызова. `dispatch()` без HTTP binder не зовёт: тесты кладут актора через `setActor`.
4. Коды `AUTH_REQUIRED` / `AUTH_DENIED` — обычный `ActionException` Kernel (как CSRF не домен Auth). Листья Auth (`AUTH_INVALID` и пр.) для create/policy как сейчас.

Auth `module.config.php`: `'request_bind' => AuthSessionBinder::class`. Binder: cookie сессии → живая строка → активный user → `permissionKeys` + `hasBypass`; иначе актор `null`. Неактивный / нет строки / `USER_NOT_FOUND` — как `getCurrentUser`: актор пуст, строку сессии можно снести (как Auth 1). Не бросать из binder: отказ — дело guard action.

User **не** `get(IAuthContainer)`. Актор только из `IRequestContext`.

### 2. Actions (`IUserApi`)

Имена как у фронта. CSRF: все `user.*` **`csrf: true`** (после логина кука есть; `HttpClient` шлёт заголовок на любой `runAction`). `auth.getCurrentUser` не трогаем (`csrf: false`).

| Action | Модуль | Payload `handle` | Успех `data` |
|---|---|---|---|
| `user.getList` | User | пусто / `{}` | `User[]` |
| `user.get` | User | `id` | объект `User` |
| `user.getByIds` | User | `ids` (`int[]`) | `User[]` (найденные; порядок как в запросе; дырки не 404) |
| `user.create` | **Auth** | `name`, `login`, `email`, `password`, `groups` (`string[]` имён) | объект `User` |
| `user.update` | User | `id` + поля `UpdateUserData` | объект `User` |
| `user.deactivate` | User | `id`, `reason` optional string, `deactivatedUntil` optional string | `null` |

Binder JSON: `deactivatedUntil` → колонка `deactivated_until` (имя параметра = ключ фронта). Пустой `reason` после trim → `null`. Пустой `deactivatedUntil` → `null` (бессрочно, пока `active=false`). Разбор даты: unix int **или** ISO-строка, которую уже ест datetime-поле; мусор → `USER_INVALID` → HTTP как `AUTH_INVALID` на create, на deactivate/update — `USER_INVALID` (`ActionException` лист User, диспетчер ловит если листья наследуют `ActionException` — **сейчас UserException может дать 500**). В этом заходе: листья `UserException` **наследуют `ActionException`** (как Auth 1), иначе сетка админки сыпет INTERNAL. Коды `USER_*` сохранить.

Не в этом заходе: `user_group.*`, смена пароля, avatar, `user.view_sensitive` (email в JSON как у `getCurrentUser`).

### 3. Guards

Снимок актора. `hasBypass` → пропуск ключа (как фронтовый `super_admin`). Иначе нужен ключ.

| Action | Кто |
|---|---|
| `getList`, `getByIds` | `user.view` |
| `get` | `user.view` **или** `actor.userId === id` (свой профиль без глобального view, [`auth-system.md`](auth-system.md)) |
| `create` | `user.create` |
| `update` | `user.edit` **или** (свой id **и** в payload **нет** `groups` и **нет** `active`) |
| `deactivate` | `user.deactivate`; **нельзя** себя |

Нет актора → `AUTH_REQUIRED`. Актор есть, ключ/владелец не сошлись → `AUTH_DENIED`. Не маскировать под `USER_NOT_FOUND` (enumeration чужих id при своём профиле: чужой `get` без `user.view` — DENIED, не «нет такого»). Нет строки при праве view → `USER_NOT_FOUND`.

Членство в **bypass-группе** (имя не магия: группа с `bypass=true`): выдать/снять может только актор с `hasBypass`. Иначе эскалация через `user.edit`. Прочие группы — достаточно `user.edit` (create — `user.create`). Нет группы по имени → `USER_INVALID`. Пустой `groups` на create → как register: только «Игрок» (`findByName`); нет «Игрок» → `AUTH_INVALID`. На update ключ `groups` отсутствует — членство не трогать; ключ есть (в т.ч. `[]`) — заменить набор (снять лишние, добавить недостающие). `USER_LAST_BYPASS` с фасада не маскировать.

### 4. JSON User

Тот же контракт, что Auth 1 `getCurrentUser`: unix int UTC, `email` null → `""`, `super_admin` ← `hasBypass`, `permissions` ← ключи, deactivate-поля, без `avatar_file_id`. Vue **не** меняем (типы фронта всё ещё string).

Сборщик живёт в **User** (`UserViewAssembler`): Auth 1 `AuthUserAssembler` переезжает сюда (Auth остаётся клиентом, передаёт `lastLogin`). HTTP User **не** ходит в identity: `lastLogin` на сетке **опускать** (ключ нет). Расхождение с «кто я» осознанное v1; денорм last login на `user` не заводим.

Лимит списка / пачки: **500** (как членство). Больше id в `getByIds` → `USER_INVALID`. `getList` — первая страница 500 по `id` asc, без фильтра. Сетка фронта без пагинации — этого хватит, пока учёток меньше 500.

### 5. Фасад User (additive)

`IUserAccounts`:

- `list(int $limit = 500): UserRecord[]` — репозиторий `getList`, не HTTP.
- `getByIds(array $ids): UserRecord[]` — уникальные id, один `getList` `id in`, без N×`getById`. Нет id → не в результате.

Репозиторий режет limit 500. `ListQuery` только в `Repository/`.

Деактивация: `update` + patch `active=false` и опционально reason / until. Включить обратно — `user.update` с `active=true` и обнулением reason/until (фронтовый `UpdateUserData.active`). Отдельного `activate` нет.

`user.create` (Auth): `addFromInput` (`name`/`login`/`email` как register: пустой email → null), политика пароля как register, identity `password:{id}`, членства. Не `login()` созданного. Дубль → `AUTH_DUPLICATE`. `USER_INVALID` → `AUTH_INVALID`. Слабый пароль → `AUTH_POLICY`. Транзакции user+identity+members **нет** (как register v1).

### 6. Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `IRequestBinder`, актор на контексте | Kernel | вызов binder, снимок | пароль, ключи прав как каталог |
| `AuthSessionBinder` | Auth | cookie → актор | JSON User |
| `UserCreateAction` | Auth `Action/` | create + identity | `open` |
| `UserViewAssembler` | User `Service/` | JSON профиля | cookie |
| `UserAccess` (guard) | User `Service/` | REQUIRED/DENIED, owner, bypass-членство | `ListQuery` |
| Actions get/update/deactivate | User `Action/` | bind + guard + фасад | `ActionResponse` |
| `IUserAccounts` list/getByIds | User | выборка | HTTP |

Сосед по-прежнему не `open`. Actions — порты контейнера, тонкие `handle`.

### 7. Ошибки

| Код | Когда |
|---|---|
| `AUTH_REQUIRED` | нет актора |
| `AUTH_DENIED` | ключ / owner / «не себя» / эскалация bypass-группы |
| `AUTH_*` create | как register + policy/duplicate |
| `USER_NOT_FOUND` | get/update/deactivate существующим админом, нет строки |
| `USER_INVALID` | битый payload, >500 ids, нет имени группы |
| `USER_DUPLICATE` | login/email |
| `USER_LAST_BYPASS` | снять последнее bypass-членство через update groups |

DDL не маскировать. CSRF 403 до action.

## Todo

- [ ] **kernel-actor** — актор на `IRequestContext`; `IRequestBinder`; ключ `request_bind`; вызов из `handle`; `reset` чистит актора; тесты без MySQL: ping без binder, фейковый binder пишет id.
- [ ] **user-exception-http** — `UserException` extends `ActionException` (коды как сейчас); тест: `dispatch` `USER_NOT_FOUND` → 400, не 500.
- [ ] **assembler** — перенос сборки JSON в User; Auth `getCurrentUser` / login без смены контракта; HTTP без `lastLogin`.
- [ ] **facade-list** — `list` / `getByIds`; mysql.
- [ ] **guard** — таблица §3; bypass-членство только при `hasBypass`; unit без HTTP.
- [ ] **user-actions** — getList/get/getByIds/update/deactivate; csrf true; routes User; mysql через сервис + явный актор (не два `dispatch`).
- [ ] **user-create** — action в Auth; политика; «Игрок» если `groups` пуст; не открывать сессию; mysql.
- [ ] **quality** — phpunit suites kernel/user/auth; cs.

## Не входит

`user_group.*` (отдельный план). Middleware «актор обязателен на все action». Guest. Reset/forgot. `remember` на Vue. Смена пароля. Avatar / Files. `user.view_sensitive`. Object-ACL / batch `can_edit`. Пагинация/фильтр сетки. Транзакция create. Kernel→Auth import. Vue типы unix. Журнал аудита.

## Документы захода

этот файл; [`user.md`](user.md); [`user-roadmap.md`](user-roadmap.md); [`auth-plan-01-session.md`](auth-plan-01-session.md); [`auth-system.md`](auth-system.md); [`architecture.md`](architecture.md) (binder + actor); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).

## Следующий заход после кода

HTTP групп (`IGroupApi` / `user_group.*`, `memberCount`, «назначать только свои ключи»). Либо проводка `remember` во `IAuthApi`.
