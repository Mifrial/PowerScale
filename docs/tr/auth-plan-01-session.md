# План Auth 1 — сессия, пароль, seed

**Статус:** сделано, 2026-09-02. Канон учётки — [`user.md`](user.md). UI — [`auth-system.md`](auth-system.md). Нарезка User — [`user-roadmap.md`](user-roadmap.md). Setup CLI — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Конвейер HTTP — [`architecture.md`](architecture.md).

Цель: модуль `Core/Auth` принимает login/logout/register и «кто я» по httpOnly cookie. Способы входа и сессия — таблицы Auth. Профиль и группы — клиент `IUserAccounts` / `IUserGroups`. User **не** импортирует Auth. Не object-ACL, не HTTP `user.*`, не guest, не VK.

На диске модуль `Core/Auth` с container, routes и setup. User — клиент портов, не наоборот.

## Термины

| Термин | Смысл |
|---|---|
| Identity | Строка способа входа (`user_identity`). Пароль — `kind=password`. Не колонки `user`. |
| Сессия | Строка `auth_session`: хеш непрозрачного токена, срок, `user_id`. Cookie держит **сырой** токен. |
| Seed | Data-шаг CLI: группы «Администраторы» / «Игрок» и первый оператор. Не `UserSchema::install()`. |
| Текущий пользователь | Сборка JSON под фронтовый `User` (имена групп, `permissions`, `super_admin` ← `hasBypass`). Не колонка `super_admin`. |

HTTP **не** 401: нет сессии на `getCurrentUser` → `success` + `data: null` (как `AuthApi`). Отказ login/register — `ActionException` `AUTH_*`, HTTP 400.

## Решения

### 1. Граница модулей

**Auth** владеет identity, сессией, cookie, политикой пароля, actions `auth.*`, data-шагом seed.

**User** владеет `user` / группами / членством. Additive: `IUserGroups::findByName` и `IUserAccounts::addFromInput` (нормализация профиля внутри User; Auth не импортирует чужой `Service/`). Не HTTP список групп.

**Kernel** (минимум, иначе cookie не прочитать и не выставить):

1. Снимок запроса на процесс: порт `IRequestContext` (extra Kernel, как `IRuntimeConfig`). `Application::handle` кладёт входящий `IHttpRequest` до dispatch. `dispatch()` без HTTP — пустые cookie. Auth **не** получает `IHttpRequest` параметром `handle` (биндер думает, что это JSON).
2. Очередь исходящих cookie: тот же контекст; `ResponseEmitter` шлёт `Set-Cookie` до JSON. Auth не зовёт `setcookie` / `header`.
3. `IRuntimeConfig::section(string $name): mixed` — срез ключа `local.php` или `null`. Разбор `auth` — `AuthSettings` в модуле Auth. Kernel не знает login оператора.

Не PHP `session_start`. Не middleware «на все action» (это следующий заход с HTTP User).

CSRF (double-submit проверяет Kernel; куку `csrf-token` выдаёт Auth на успешный login/register):

- Фронт (`CsrfApi`) читает `csrf-token` из `document.cookie` и шлёт `X-CSRF-Token`. Кука **не** httpOnly (иначе JS не увидит). Сессионная `mifrial-session` — httpOnly.
- SameSite **Lax** на обе куки (сессия и CSRF). Strict не берём: SPA POST с той же площадки; double-submit уже закрывает чужой origin.
- Куриный яйцо: на `/login` куки CSRF ещё нет. `auth.login` и `auth.register` — **`csrf: false`** (как в TZ: login без сессии). `auth.getCurrentUser` и `auth.getPasswordPolicy` — тоже `csrf: false` (первый заход / refresh без CSRF не должен стать 403 вместо `null`).
- `auth.logout` — **`csrf: true`**. Kernel проверяет CSRF **до** action: без пары cookie/заголовок — HTTP 403, handle не зовут. Идемпотентность «нет сессии → 200» только **после** прошедшего CSRF (UI после логина куку имеет). Curl без `csrf-token` — 403, не «успешный пустой logout».
- Успешный login/register: очередь **двух** cookie — сессия и `csrf-token` (не httpOnly, SameSite=Lax, тот же TTL, что сессия). Logout гасит обе.

### 2. Карты SmartTable

Имена: `user_identity`, `auth_session`. Не legacy `sessions.token` в открытом виде.

**`user_identity`**

| Поле | Заметки |
|---|---|
| `id` | |
| `user_id` | `reference` → `UserTable`, required, restrict |
| `kind` | string required; v1 только `password` |
| `identity_key` | string unique required: `password:{userId}` |
| `secret_hash` | string required; `password_hash()` / `password_verify()`; plaintext не хранить |
| `last_used_at` | datetime, nullable; писать при успешном login |

Один password-identity на учётку. `identity_key` = `password:{userId}` — это `{kind}:{external}` из [`user.md`](user.md) для kind password (external = id учётки, не login: смена login ключ не ломает). VK позже — другая `kind`, не колонка User. `secret_hash`: VARCHAR 255 (bcrypt/argon2 hash укладывается; не 1024).

**`auth_session`**

Ищем только по `token_hash`, не по `id`. Колонку `id` со карты ST не убираем (definition без `id` нельзя). `DELETE` просроченных **не** откатывает `AUTO_INCREMENT`: каждый login жжёт следующий id. Обычный `IdField` — signed `INT` (~2.1e9) — для сессий мало. PK сессии — **`IdField::big()`** (BIGINT). `user.id` и `user_id` остаются INT/`reference` как сейчас. Ширина целого — [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md), не классы `BigIdField`.

| Поле | Заметки |
|---|---|
| `id` | `IdField::big()` |
| `user_id` | `reference` → `UserTable`, required, **`cascade`** |
| `token_hash` | string unique required; `hash('sha256', $rawToken)` |
| `expires_at` | datetime required; источник истины «жива ли сессия» |

`created_at` нет: last login — `last_used_at` identity; remember vs 24ч кодируется **сроком в `expires_at`**, не парой created+флаг.

`cascade`, не `restrict`: сессия — хвост учётки. User в v1 не delete; когда delete появится, сессии должны уйти сами, а не блокировать родителя. Физический FK нужен (ребро графа CLI); `none` нельзя — иначе `auth_session` может создаться раньше `user`.

ST до карт Auth: [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md) (`IdField::big()`, `onDelete: cascade`, required+cascade). `required`+`setNull`/`none` по-прежнему нет. mfv без FK CASCADE на sidecar.

Несколько сессий на человека (другие устройства). Logout инвалидирует **эту** сессию по cookie, не все.

Граф CLI: оба стола после `user` (`user_id`). mfv нет. `IModuleSetup::tableClasses()` — эти два class-string. `AuthSchema::install()` для mysql-тестов (как User), не оркестратор прода.

### 3. Cookie и сроки

Имя cookie сессии: `mifrial-session`. httpOnly, `Path=/`, `SameSite=Lax`. `Secure` — `auth.cookie_secure` в `local.php` (в dist `false`). CSRF-кука `csrf-token` — не httpOnly, см. §1.

TTL: 24 часа; `remember === true` — 30 суток. Поле JSON `remember` optional bool, default **false**. Фронт `LoginPage` уже рисует switch, но `IAuthApi.login` **не передаёт** remember — этот план **не** меняет Vue. Когда фронт начнёт слать поле, биндер его примет.

Сырой токен: `random_bytes` → hex/base64url. В БД только хеш. Подбор по cookie: `getUnique` по `token_hash`; просрочка → как нет сессии (строку можно delete).

### 4. Actions (контракт фронта `AuthApi`)

| Action | Payload `handle` | Успех `data` |
|---|---|---|
| `auth.login` | `loginOrEmail`, `password`, `remember = false` | `{ user: … }` |
| `auth.register` | `login`, `email`, `password` | `{ user: … }` |
| `auth.logout` | пусто / `{}` | `null` |
| `auth.getCurrentUser` | пусто | объект `User` **или** `null` |
| `auth.getPasswordPolicy` | пусто | `{ minLength, requireMixedCase, requireDigit, requireSpecialChar }` |

Не в этом заходе: `auth.findUser` ( enumeration ), `auth.resetPassword` / forgot (токены сброса). Маршруты фронта `/forgot-password` остаются на mock.

Политика v1 = фронтовый `DEFAULT_PASSWORD_POLICY`: `minLength: 4`, флаги mixed/digit/special **false**. Режет **только register** (и будущую смену пароля). **Не** login: иначе уже выданный hash/оператор из seed отвалятся при ужесточении политики. Слабый пароль на register → `AUTH_POLICY`.

**Login:** trim идентификатора. Пустой после trim / `USER_INVALID` от `findByLogin` (сейчас пустая строка — не `null`) → **`AUTH_INVALID`**, не 500. Если учётка нашлась по login — email не искать. Иначе `findByEmail`. Нет учётки / `active === false` / нет password-identity / `password_verify` false → тот же **`AUTH_INVALID`**, одна формулировка, без различия «нет логина» vs «плохой пароль». `deactivated_until` без `active=false` **не** банит вход (инвариант — HTTP deactivate позже). Успех: новая сессия, cookie, `last_used_at`, ответ-сборка User.

**Register:** открытая регистрация. Email как у User: пустой после trim → `null` (учётка без почты), не `AUTH_INVALID`. Создать `user` через `IUserAccounts::addFromInput` (`name` = `login`), identity, членство в группе **«Игрок»** (`findByName`). Нет группы «Игрок» → `AUTH_INVALID` (не прогнан seed). Дубль login/email → `AUTH_DUPLICATE` (поймать `USER_DUPLICATE`, не пустить как 500). `USER_INVALID` от нормализатора/`add` → `AUTH_INVALID`. Затем как login (сессия).

**Logout:** CSRF уже прошёл (иначе 403, см. §1). Нет/битая cookie — успех (идемпотентно). Иначе delete строки сессии, очередь cookie с истекшим сроком.

**getCurrentUser:** нет/просрочена сессия → `null`. Неактивный user **или** `USER_NOT_FOUND` на профиле **или на сборке JSON** (группы/`hasBypass`) → как нет сессии: `data: null`, строку сессии снести, гасить cookie не обязательно. Не 401. Вход неактивного — `AUTH_INVALID` (главное: деактивированный не авторизуется).

Сборка JSON — тип Auth (`AuthUserView`), не порт User:

- имена групп: `groupsOfUser` + `getById`;
- `permissions` ← `permissionKeys`;
- `super_admin` ← `hasBypass`;
- `registered`, `lastLogin` — **unix int** UTC (`DateTime::toUnix()`); `lastLogin` нет (`last_used_at` null) — ключ опустить или `null`;
- `deactivated_until` — unix int или `null`; `deactivate_reason` — строка или `null` (поля уже в `UserRecord`; не резать, иначе экран профиля потом ломается);
- `email` null → `""`;
- без `avatar_file_id`; объект `DateTime` в JSON не класть.

Во фронтовом `User.ts` `registered` / `lastLogin` сейчас **string** (mock — локаль). Этот план **не** меняет Vue: типы будут врать до отдельного захода. Групп у человека ≤ 500 (лимит фасада User).

Повторный login в том же браузере: если в запросе уже есть живая сессионная cookie — **снести эту строку**, потом выдать новую (не копить сирот до `expires_at`). Чужие устройства не трогать.

### 5. Seed (data-шаг Auth)

Id: `Core/Auth:seed.bootstrap-groups`. После **всей** схемы CLI.

Идемпотентно:

1. Группа «Администраторы»: `bypass=true`, `permissions=[]`, `active=true`. Есть по имени — не создавать вторую (слот bypass один).
2. Группа «Игрок»: `bypass=false`, `permissions=[]`.
3. Оператор: `auth.operator_login` / `operator_password` / `operator_name` из `local.php` (dist: `admin` / `changeme` / `Администратор`). Нет учётки с этим login → `add` + password identity + членство в «Администраторы» **и** в «Игрок» (как mock-админ: bypass не заменяет обычную группу). Учётка есть, identity нет → дописать hash. Пароль существующего оператора **не** перезаписывать. Членства дописать, если нет.

`setup` в config: **closure локатора** (шагу нужны порты User). `tableClasses()` без БД. `get(IUserAccounts)` в collect до DDL допустим: `open` не требует физики; запись — в `ISetupStep::run()` после графа. Не `get` внутри `tableClasses()`.

Имена групп — строки seed, не магия bypass (bypass = флаг).

### 6. Слои Auth

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `UserIdentityTable` / `AuthSessionTable` | `Table/` | карты | cookie |
| `AuthSchema` | `Schema/` | install двух карт для тестов | seed |
| `AuthModuleSetup` | `Setup/` | `tableClasses` + data-шаги | порт контейнера соседа |
| identity/session repositories | `Repository/` | `IOpenedRecords`, unique key | `open` |
| `AuthSessions` / login-сервис | `Service/` | verify, cookie queue, view | JSON-конверт |
| Actions | `Action/` | `handle` + `ActionException` | `ActionResponse`, `open` |
| `AuthException` + листья | `Exception/` | `AUTH_INVALID`, `AUTH_DUPLICATE`, `AUTH_POLICY` | маскировать DDL |
| `IAuthContainer` | `Interface/Container/` | порты: actions; `AuthService` в карте модуля **один** (singleton контейнера), сосед не `get` сценарий | домен User |

Сосед (пока никто, кроме HTTP) не импортирует Repository Auth. User не знает Auth.

Пароль: `password_hash` / `password_verify` PHP, не порт «Hasher» ради одного вызова.

### 7. Конфиг

`local.php` / dist:

```php
'auth' => [
    'operator_login' => 'admin',
    'operator_password' => 'changeme',
    'operator_name' => 'Администратор',
    'cookie_secure' => false,
],
```

Пустой/битый срез при seed → `SETUP_INVALID` / `AUTH_INVALID` на шаге, не молчаливый skip (иначе установка без оператора). Тесты mysql могут передать AuthSettings в сервис без local.

### 8. Ошибки

Листья `AuthException` **наследуют `ActionException`**: иначе диспетчер не поймает домен и отдаст `INTERNAL` 500 (`UserException` так может, у User ещё нет HTTP). Коды: `AUTH_INVALID`, `AUTH_DUPLICATE`, `AUTH_POLICY`. В action ловить и мапить: `USER_DUPLICATE` → `AUTH_DUPLICATE`; `USER_INVALID` (пустой login, битые поля register) → `AUTH_INVALID`; `USER_NOT_FOUND` на живой сессии → как нет сессии (`null`), не 500. Unique сессии/identity → Auth-слой. DDL / нет таблицы не маскировать.

## Todo

- [x] **st-bigint-cascade** — код [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md) **до** карт Auth. Не `BigIdField`. Не менять `user.id`.
- [x] **kernel-http** — `IRequestContext` + очередь cookie в `ResponseEmitter`; `IRuntimeConfig::section`; тесты emit/cookie без MySQL. Контекст **сбрасывать** на каждый `handle`. `dispatch()` без HTTP cookie не подставляет: mysql login/logout гонять через сервис (+ явный контекст), не «два dispatch подряд». Выдача `csrf-token` (не httpOnly) на login/register.
- [x] **module** — контейнер, autoload `Mifrial\Core\Auth\` в `composer.json`, phpunit suite `auth` в `phpunit.xml.dist`, ключ `auth` в `local.php.dist`, `setup` + routes, README одна строка.
- [x] **tables** — две карты (`IdField::big()` + cascade на сессии); `AuthSchema::install`; FK на `UserTable`.
- [x] **user-find-name** — `IUserGroups::findByName`; тест mysql.
- [x] **login-session** — identity password, сессия, cookie, login/logout/getCurrentUser; пустой id / неактивный / нет учётки / bad password → один `AUTH_INVALID`; мёртвый user на живой сессии → `null`; сборка User JSON (unix + deactivate-поля).
- [x] **register-policy** — register + политика; нет «Игрок» → `AUTH_INVALID`; дубль → `AUTH_DUPLICATE`.
- [x] **seed** — data-шаг; повтор CLI не плодит группы/оператора; phpunit + quality.

## Не входит

Guest. VK / иные `kind`. Reset/forgot, `findUser`. HTTP `user.*` / `user_group.*` и guards. Middleware на все action. Object-ACL. Avatar. Колонка `last_login_at` на `user`. Смена пароля профиля. Remember на фронте. Журнал аудита. `forceUpdateTable`. Extranet.

## Документы захода

этот файл; [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md); [`smarttable.md`](smarttable.md); [`auth-system.md`](auth-system.md) (seed больше не OPEN этого контура); [`user-roadmap.md`](user-roadmap.md); [`TR.md`](TR.md); [`architecture.md`](architecture.md) (cookie emit + request context); [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md) следующий заход; `composer.json`; `phpunit.xml.dist`; `local.php.dist`.

## Следующий заход после кода

HTTP учётки — [`user-plan-03-http.md`](user-plan-03-http.md). Reset-пароля. Guest. VK. Remember на фронте.
