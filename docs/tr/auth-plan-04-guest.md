# План Auth 4 — гостевая сессия

**Статус:** сделано, 2026-09-04. Стенд `powerscale` на момент сверки: база есть, **таблиц 0** (`setup.php` не гоняли). Нарезка — [`core-tails-roadmap.md`](core-tails-roadmap.md). Канон — [`auth-system.md`](auth-system.md) § Guest. Сессия пользователя — [`auth-plan-01-session.md`](auth-plan-01-session.md) (**сделано**). Vue: `Session.kind`, `guestLogin()` → `auth.guest`, `currentUser === null`, `guestActor` в User store, `guestAllowed`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: гость — живая cookie Auth **без** учётки. Reload не сбрасывает в anon. Защищённые action по-прежнему `AUTH_REQUIRED` (нет `RequestActor`).

Не VK. Не User#0. Не членство в группе с именем «Гость» (это `assign_on_register`, другой смысл). Не SMTP. Не rate limit.

## Термины

| Термин | Смысл |
|---|---|
| Anon | Нет cookie / просрочена. Vue `kind: 'anon'`. |
| Guest | Cookie есть, в `auth_session` нет `user_id`. Vue `kind: 'guest'`, `currentUser === null`, `guestActor === true`. |
| User-сессия | Cookie + `user_id`. Как Auth 1. |

## Решения

### 1. Граница

**Auth** владеет cookie, строкой сессии, `auth.guest`, разбором `getCurrentUser`. **User** не знает guest. **Kernel** `RequestActor` по-прежнему только учётка: гость = binder **не** ставит актора (как anon). Guards `requireKey` → `AUTH_REQUIRED`.

Страницы `guestAllowed` — мета Vue. Бэкенд games/spaces/chat в этом плане не открываем.

### 2. Карта `auth_session`

Сейчас `user_id` required + **cascade** на `user`. Для гостя:

| Поле | Изменение |
|---|---|
| `user_id` | **не required**; `onDelete` оставить **`cascade`** (как Auth 1) |
| `kind` | string; в карте **не required** (см. DDL ниже); v1: `user` / `guest` |
| `token_hash`, `expires_at` | как сейчас |

Почему не `setNull`: nullable `user_id` + cascade допустимы в ST (`required`+`setNull` — нет; `required: false`+`cascade` — да). Гостевая строка не ссылается на `user`. Удаление учётки снесёт только user-сессии. `updateTable` **не** переписывает уже висящий FK (`smarttable-plan-05-reference.md`): смена cascade→setNull на живой `auth_session_user_id_fk` не произойдёт.

Инвариант: `kind === 'user'` ↔ `user_id` не null; `kind === 'guest'` ↔ `user_id` null. Иное `kind` (мусор, пустая строка) — как нарушение: снести строку. Нарушение при bind/getCurrent — снести (как мёртвую user-сессию).

Чтение старых строк без `kind` (NULL после ADD): `user_id` задан → считать `user`; `user_id` null и kind пуст → снести (битая).

Запись: `add` **всегда** кладёт `kind` (`user` / `guest`). Default в `FieldSettings` не нужен: без ключа + PHP default `'user'` при `user_id` null собрал бы битую пару.

### 2.1. DDL: чего ST не сделает сам

`ColumnSchema::defineColumn` **не** пишет SQL `DEFAULT`. `FieldSettings.default` — только PHP при отсутствии ключа в `add`.

`updateTable` **добавляет отсутствующие колонки**, не меняет nullability живых и не меняет `onDelete` существующего FK. `forceUpdateTable` leftover не поможет: `user_id` в карте остаётся.

CLI (`ModuleSetupRunner`) зовёт только `createTable` / `updateTable` по графу. **`AuthSchema::install` в прод не ходит** — это обёртка mysql-тестов (как User/Mail/Agent).

Следствия:

1. **`kind`** — в карте **не required**, чтобы `ADD COLUMN` на непустой таблице прошёл (nullable). Required NOT NULL без SQL default на живых строках — DDL упадёт. CLI `updateTable` колонку добавит.
2. **`user_id` NOT NULL → NULL** карта не сделает. На **CREATE** (mysql-тесты с пустой физикой, новая установка) колонка сразу nullable. На **уже созданной Auth 1 таблице** guest-`add` без ALTER отвергнет MySQL.

**Не делать в PHP:** `ensureSessionUserIdNullable` / Illuminate `change()` / сырой SQL из Auth.

- Auth не импортирует `SmartTable\Service\` (DIP). Публичный `IDatabaseConnection` — **ping, без SQL-API**.
- `->change()` у Illuminate **требует doctrine/dbal** — в `composer.json` его нет.
- Дублировать ALTER в `AuthSchema::install` не покрывает CLI.

**Решение захода:** DDL в коде только карта + обычный create/update. Mysql-тест и первая установка на пустой БД — `createTable`, ALTER не нужен. Если `auth_session` уже создана картой Auth 1 (NOT NULL) — тогда один ручной `ALTER TABLE auth_session MODIFY user_id INT NULL` (signed INT, FK cascade не снимать). Сейчас на стенде физики нет; в D-gate не делать вид, что ALTER обязателен всем.

### 3. Actions

| Action | CSRF | Смысл |
|---|---|---|
| `auth.guest` | `false` (как login: на `/login` csrf-cookie может не быть) | Выпустить guest-сессию, cookie как login **без** remember (TTL `DEFAULT_TTL` = 86400). Data: `{ kind: 'guest' }` |
| `auth.getCurrentUser` | `false` | См. §4 — **ломающий** вид data |
| `auth.login` / `register` | как сейчас | Уже залогинен user — как сейчас (`dropIncomingSession`). Уже guest — заменить на user-сессию |
| `auth.logout` | true | Сносит и user, и guest. После `auth.guest` cookie `csrf-token` уже выдана (`AuthCookieIssuer::issue`) |

`auth.guest` при **живой user-сессии**: **не** заменять на guest, **`AUTH_INVALID`**. Иначе `csrf: false` — CSRF-logout без пароля (login хотя бы требует credentials). Кнопка гостя на Login при user-сессии роутер и так прячет (`layout: auth` → Home). Сначала `auth.logout`.

Повтор `auth.guest` в живой **guest**-сессии: обновить TTL / новый token (`dropIncomingSession` + новая строка), как relogin. Anon без cookie — просто открыть guest.

`openGuestSession` переиспользует выдачу cookie (сессия + csrf, тот же TTL), не копипастить issuer.

### 4. `getCurrentUser` (ломает Vue и PHP-тесты)

Сейчас data = User JSON или `null`. После плана:

| Состояние | `data` |
|---|---|
| Нет/мертвая cookie | `null` |
| Guest | `{ "kind": "guest" }` |
| User | `{ "kind": "user", "user": { …User JSON… } }` |

Login/register **не** меняют форму `{ user }`.

PHP `AuthService::getCurrentUser(): ?array` возвращает этот конверт, не голый User. Тесты Auth 1, что ждут поля User на корне `data` / возврата сервиса, **переписать**.

Vue: `IAuthApi.getCurrentUser` → `CurrentSession \| null` (`null` = anon). Не путать с `Session` store (`anon` только на клиенте).

`checkAuth`:

- `null` → anon, **return false** (как сейчас; `authChecked` в роутере останется false — каждый переход снова спросит API; не чинить в этом плане).
- `kind === 'guest'` → `setGuestSession` + `setGuest` на User store, **return true** (`isAuthenticated`; иначе `evaluateRouteAccess` уведёт на Login).
- `kind === 'user'` → `setUserSession` + `setCurrent`, return true.

Ранний выход `kind !== 'anon'` оставить.

`IAuthApi.guest(): Promise<void>` (или data `{ kind: 'guest' }`, клиенту kind не обязателен — store ставит сам). `guestLogin()` зовёт его. Успех → те же setGuest*. Ошибка API (`AUTH_INVALID` и прочее) → **не** ставить локальный guest (сейчас кнопка врёт без cookie). Мок: `mockGuest` + хранение guest в `sessionStorage` отдельно от `mock_session_user_id` (reload в mock-режиме). `getCurrentUserId()` с fallback `1` для Roleplay-фикстур **не** делать «я гость = Иван»; Auth-мок гостя = нет user id. Roleplay mock от гостя в этом плане не чиним.

Home (`guestAllowed`) в real дергает `notifications.fetchPage` — PHP action нет → `UNKNOWN_ACTION`, не `AUTH_REQUIRED`; HttpClient на Login не уводит. Inbox в этом плане не чинить.

### 5. Binder и поиск сессии

**Ветка `kind` до `getById`.** Сейчас `activeUserFromSession` делает `(int) $sessionRow['user_id']`: у гостя `user_id` null → `0` → `USER_NOT_FOUND` → **delete строки**. Без ветки guest-сессия умрёт на первом `getCurrentUser` / bind.

`resolveActor`: `kind === 'guest'` (и согласованный null `user_id`) → **null актора, строку не удалять**. `kind === 'user'` — как сейчас. Битая пара kind/user_id — delete + null.

Просрочка — уже в `liveSessionRow`, общая.

### 6. Сложность AuthService

Класс уже на phpcs-исключениях. Guest не раздувать 10-м сценарием в том же файле без нужды: `openGuestSession` рядом с `openSession`, либо узкий `GuestSessionService`, если quality не пройдёт. Не новый модуль.

`AuthSessionRepository::add` — `user_id` nullable + `kind`. `deleteByUserId` (reset пароля) гостей не трогает (`user_id` null).

### 7. Тесты

PHP mysql: install → строка guest без `user_id`; `auth.guest` → cookie + csrf queue, getCurrentUser `{ kind: guest }`, resolveActor null, `user.findPage` → AUTH_REQUIRED; login после guest → envelope user; logout сносит guest; просрочка как user-сессия; `auth.guest` при user-сессии → AUTH_INVALID, cookie user жива; битый kind/user_id → null и строки нет.

Vue: AuthApi/store/checkAuth (guest → true); LoginPage кнопка; мок переживает reload. format/lint/tsc/test Auth.

### 8. Не входит

VK. Права гостя на PHP action кроме публичных csrf:false (ping, login, register, guest, getCurrentUser, getPasswordPolicy, forgot/final reset). `guestActor` **в JSON User** (флаг остаётся Vue store). Абсолютный TTL в `local.php`. Object-ACL. Chat/games фильтр на бэке. ALTER TYPE / SQL-API соседям в ST. Чинить `authChecked === checkAuth()` у anon. Illuminate в Auth.

## Этапы

### A — DDL и сессия

Карта: nullable `user_id`, cascade; `kind`; open/delete guest; mysql add без `user_id` на свежей физике.

### B — HTTP + binder

`auth.guest`; envelope getCurrentUser; ветка kind до getById; mysql AUTH_REQUIRED / AUTH_INVALID.

### C — Vue

Api, store, checkAuth return true, guestLogin fail-closed, мок sessionStorage.

### D — доки

auth-system (канон уже); roadmap; TR; quality. ALTER — только если всплывёт старая физика.

## Todo

- [x] **A-session** — карта; guest-строка; инвариант kind/user_id.
- [x] **B-http** — `auth.guest`; envelope; binder без delete гостя; guest при user → AUTH_INVALID.
- [x] **C-vue** — checkAuth / guestLogin / мок.
- [x] **D-gate** — доки; phpunit `auth`; Vue Auth.

## Документы захода

этот файл; [`core-tails-roadmap.md`](core-tails-roadmap.md); [`auth-plan-01-session.md`](auth-plan-01-session.md); [`auth-system.md`](auth-system.md); [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md) (гость не User#0); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md); [`smarttable-plan-05-reference.md`](smarttable-plan-05-reference.md) (FK не переписывается).
