# План User 3 — HTTP учётки и актор запроса

**Статус:** сделано, 2026-09-03. Канон учётки — [`user.md`](user.md). UI — [`auth-system.md`](auth-system.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). Сессия — [`auth-plan-01-session.md`](auth-plan-01-session.md). Конвейер — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Список HTTP — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md): `user.findPage`, не `user.getList`.

Цель: фронтовый `IUserApi` ходит в боевые `user.*`. Guards смотрят **актора сессии**, не mock. User **по-прежнему не импортирует Auth**. Пароль на создании учётки пишет Auth. Не `user_group.*`, не object-ACL, не смена пароля профиля, не Vue.

На диске уже есть фасады `IUserAccounts` / `IUserGroups` и Auth-сессия. Это HTTP + снимок актора на процесс.

## Термины

| Термин | Смысл |
|---|---|
| Актор | Снимок на запрос: `userId`, ключи из `getPermissionKeys`, `hasBypass`. Не полный JSON User. |
| Binder | Порт модуля (`IRequestBinder` в Kernel): после cookie, до dispatch, кладёт актора в `IRequestContext`. Реализация — Auth. |
| Guard | Проверка «есть актор / ключ / владелец» в HTTP-слое User (и в `user.create` у Auth). Не middleware «на все action». |

HTTP **не** 401 (как Auth 1). Нет актора / нет права → `ActionException` `AUTH_REQUIRED` / `AUTH_DENIED`, HTTP **400**. 403 по-прежнему только CSRF.

## Решения

### 1. Граница модулей

**User** владеет `user.findPage` / `user.get` / `user.getByIds` / `user.update` / `user.deactivate` и сборкой JSON профиля (кроме `lastLogin`). Additive на фасаде: страница и пачка id. Не читает `user_identity` / `auth_session`. HTTP dump `user.getList` снят (план 5).

**Auth** владеет сессией и **`user.create`**: фронт шлёт пароль; identity живёт в Auth; цикл `User → Auth` не заводим. Register уже создаёт identity — admin-create тот же секрет, без новой сессии на созданного.

**Kernel** не знает login/пароль. Additive:

1. DTO актора + поля на `IRequestContext` (`getActor()` / `setActor()`, `reset()` сбрасывает актора вместе с cookie). Не `actor()` — чтение с глагола, как в стандартах.
2. Контракт `IRequestBinder` (один метод `bind(IRequestContext): void`).
3. Ключ модуля `request_bind` → class-string порта в **этом** контейнере. `Application::handle` после CSRF, до `dispatch`: для каждого загруженного модуля с ключом — `get` порта, если это `IRequestBinder` — вызвать. Нет ключа — нет вызова. `dispatch()` без HTTP binder не зовёт: тесты кладут актора через `setActor`.
4. Коды `AUTH_REQUIRED` / `AUTH_DENIED` — обычный `ActionException` Kernel (как CSRF не домен Auth). Листья Auth (`AUTH_INVALID` и пр.) для create/policy как сейчас.

Auth `module.config.php`: `'request_bind' => AuthSessionBinder::class`. Binder: cookie → живая сессия → активный user → `getPermissionKeys` + `hasBypass`; иначе актор `null`. Не разбирать JSON `getCurrentUser` в контекст (снимок — id и права, не объект User). Общая логика поиска сессии с `getCurrentUser` — да; неактивный / нет строки / `USER_NOT_FOUND` — актор пуст, строку сессии можно снести. Не бросать из binder.

User **не** `get(IAuthContainer)`. Актор только из `IRequestContext`.

Чужой `Service/` не импортируем (стандарты). Auth уже берёт `IUserAccounts` / `IUserGroups`. Для JSON и guard — **новые порты User**, не `new UserViewAssembler` из Auth:

- `IUserViews::assemble(UserRecord, ?DateTime $lastLogin): array` — переезд `AuthUserAssembler`.
- `IUserAccess` — `requireActor`, `requireKey`, `requireSelfOrKey`, `assertCanAssignBypassMembership`, `assertCanUpdate`, `assertCanDeactivate` (глаголы; `hasBypass`/`can*` на снимке). Auth `user.create` зовёт порты через `IUserContainer`.

`request_bind` — class-string **порта в карте `ports` того же модуля**, иначе `get` на запросе упадёт. Проверка при загрузке модуля (как кривой `handler`). Binder не бросает: нет сессии → актор `null`.

### 2. Actions (`IUserApi`)

Имена как у фронта. CSRF: все `user.*` **`csrf: true`** (после логина кука есть; `HttpClient` шлёт заголовок на любой `runAction`). `auth.getCurrentUser` не трогаем (`csrf: false`).

| Action | Модуль | Payload `handle` | Успех `data` |
|---|---|---|---|
| `user.findPage` | User | `FindPageInput`: `limit` (1…500), `offset`, опционально `q`, `active` | `{ items: User[], total }` |
| `user.get` | User | `id` | объект `User` |
| `user.getByIds` | User | `ids` (`int[]`) | `User[]` (найденные; порядок как в запросе; дырки не 404). **`[]` → `[]` без `getList`**: ST `IN` пустой список — `MAP_INVALID`. |
| `user.create` | **Auth** | как `UserForm` create: `name`, `login`, `email`, `password`, `groups` (**id групп**, `int[]`); **опционально `surname` / `nickname`**. Маршрут только в Auth, не дублировать в User. | объект `User` |
| `user.update` | User | `id` + опционально `name`, `surname`, `nickname`, `email`, `groups` (id), `active`. Свой профиль **не** шлёт `groups`/`active`. Админский `UserForm` edit **всегда** шлёт оба. `login` на edit фронт не шлёт. | объект `User` |
| `user.deactivate` | User | `id`; `reason` / `deactivatedUntil` опционально. `JSON.stringify` выкидывает `undefined` — полей может не быть. | `null` |

`deactivatedUntil`: диалог — `<input type="date">` → строка **`Y-m-d`**, не unix. `DateTime` ядра и `DateTimeField` **строки не едят** (только объект). HTTP-слой парсит `Y-m-d` как UTC 00:00:00 → `DateTime::fromUnix`; пусто/нет ключа → `null`; мусор → `USER_INVALID`. В нормализатор уже объект, не строка.

Листья `UserException` **наследуют `ActionException`** (как Auth): свой `errorCode` на базе User убрать, код в родителя. Иначе `USER_*` → HTTP 500. Коды `USER_*` сохранить.

Не в этом заходе: `user_group.*`, смена пароля, avatar, `user.view_sensitive` (email в JSON как у `getCurrentUser`).

### 3. Guards

Снимок актора. `hasBypass` → пропуск ключа (как фронтовый `bypass`). Иначе нужен ключ.

| Action | Кто |
|---|---|
| `findPage`, `getByIds` | `user.view` |
| `get` | `user.view` **или** `actor.userId === id` (свой профиль без глобального view, [`auth-system.md`](auth-system.md)) |
| `create` | `user.create` |
| `update` | `user.edit` **или** (свой id **и** в payload **нет** `groups` и **нет** `active`) |
| `deactivate` | `user.deactivate`; **нельзя** себя |

Нет актора → `AUTH_REQUIRED`. Актор есть, ключ/владелец не сошлись → `AUTH_DENIED`. Не маскировать под `USER_NOT_FOUND` (enumeration чужих id при своём профиле: чужой `get` без `user.view` — DENIED, не «нет такого»). Нет строки при праве view → `USER_NOT_FOUND`.

Членство в **bypass-группе** (имя не магия: группа с `bypass=true`): выдать/снять может только актор с `hasBypass`. Иначе эскалация через `user.edit`. Прочие группы — достаточно `user.edit` (create — `user.create`). Нет группы по id → `USER_NOT_FOUND`. Пустой `groups` на create (`[]` — дефолт формы) → как register: все группы с `assign_on_register`; нет ни одной → `AUTH_INVALID`. Непустой список — **как есть**, флажок сами не дописываем. На update ключ `groups` отсутствует — членство не трогать; ключ есть (в т.ч. `[]`) — заменить набор. `USER_LAST_BYPASS` с фасада не маскировать. JSON `User.groups` — id, не имена.

### 4. JSON User

Канон ключей — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md) этап B: unix int UTC, `email` `string|null` (не `""`), `bypass` (не `super_admin`), `groups` — id, `permissions` — ключи, `deactivatedUntil` / `deactivateReason` camelCase, без `avatar_file_id`. Вход deactivate: `deactivatedUntil` как **`Y-m-d`**; в ответе — unix.

Сборщик — порт `IUserViews`. Auth передаёт `lastLogin`; HTTP User — `lastLogin=null`, ключ **опускать**.

Лимит страницы / пачки: **500**. Больше id в `getByIds` → `USER_INVALID`. Dump `getList` нет: сетка — `findPage` с `total`.

### 5. Фасад User (additive)

`IUserAccounts`:

- `findPage(int $limit, int $offset, ?string $searchQuery, ?bool $active): UserRecordPage` — страница + `total`; HTTP `user.findPage`. ST `getList` только в репозитории.
- `getByIds(array $ids): UserRecord[]` — уникальные id, один ST `getList` `id in`, без N×`getById`. Нет id в БД → не в результате. **Пустой `$ids` → `[]` без запроса** (тот же запрет пустого ST `IN`).

Репозиторий режет limit 500. `ListQuery` только в `Repository/`.

Деактивация: `update` + patch `active=false` и опционально reason / until. Включить обратно — `user.update` с `active=true` и обнулением reason/until (фронтовый `UpdateUserData.active`). Отдельного `activate` нет.

`user.create` (Auth): не раздувать `AuthService` (уже 6 зависимостей ctor). Отдельный сценарий create в Auth `Service/` + тонкий action. `addFromInput` (пустой email → null; surname/nickname если есть), политика пароля как register, identity `password:{id}`, членства. Не `login()` созданного. Дубль → `AUTH_DUPLICATE`. `USER_INVALID` → `AUTH_INVALID`. Слабый пароль → `AUTH_POLICY`. Транзакции **нет**.

### 6. Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `IRequestBinder`, актор на контексте | Kernel | вызов binder, снимок | пароль, ключи прав как каталог |
| `AuthSessionBinder` | Auth | cookie → актор (поиск сессии как у `getCurrentUser`, без JSON User в контексте) | JSON User |
| `UserCreateAction` + create-сервис | Auth | create + identity; guard через `IUserAccess` | `open`, чужой `Service/` |
| `IUserViews` / `IUserAccess` | User `Interface/Service/` | JSON и ACL для Auth и HTTP User | cookie, `ListQuery` |
| Actions get/update/deactivate | User `Action/` | bind + guard + фасад | `ActionResponse` |
| `IUserAccounts` findPage/getByIds | User | выборка | HTTP |

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

- [x] **kernel-actor** — `getActor`/`setActor` на `IRequestContext`; `IRequestBinder`; `request_bind` ∈ `ports`; валидация конфига; вызов из `handle`; `reset` чистит актора; ping без binder; фейковый binder пишет id.
- [x] **user-exception-http** — `UserException` extends `ActionException`; `dispatch` `USER_NOT_FOUND` → 400.
- [x] **views-access-ports** — `IUserViews` + `IUserAccess`; Auth login/getCurrentUser через порт; HTTP без `lastLogin`.
- [x] **facade-list** — `getList` / `getByIds`; пустой ids без IN; mysql.
- [x] **guard** — таблица §3; bypass-членство; unit без HTTP.
- [x] **user-actions** — get/update/deactivate/getList; csrf true; `Y-m-d` на deactivate; mysql + явный актор (не два `dispatch`).
- [x] **user-create** — Auth, не `AuthService` ctor+1; surname/nickname; пустой groups → `assign_on_register`; не сессия; mysql.
- [x] **quality** — phpunit suites kernel/user/auth; cs.

## Не входит

`user_group.*`. Middleware «актор на все action». Guest. Reset. `remember` на Vue. Смена пароля / login (edit на фронте disabled). Avatar. `user.view_sensitive`. Object-ACL / `can_edit`. Пагинация. Транзакция create. Kernel→Auth. Vue unix-типы. HTTP 401 на `AUTH_REQUIRED` (фронт редиректит только с 401; без сессии сетка получит 400 — сознательно, как Auth 1). Журнал.

## Документы захода

этот файл; [`user.md`](user.md); [`user-roadmap.md`](user-roadmap.md); [`auth-plan-01-session.md`](auth-plan-01-session.md); [`auth-system.md`](auth-system.md); [`architecture.md`](architecture.md) (binder + actor); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).

## Следующий заход после кода

Блокер контракта — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md). После этапа D: таблица политики пароля Auth; `remember` на Vue — если спросят.
