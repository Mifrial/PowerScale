# План Auth 2 — политика пароля, remember, сброс и смена

**Статус:** сделано, 2026-09-03. Предыдущий заход — [`auth-plan-01-session.md`](auth-plan-01-session.md) (**сделано**). Канон UI — [`auth-system.md`](auth-system.md). Нарезка User — [`user-roadmap.md`](user-roadmap.md). Контракт User JSON — [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md) (**сделано**). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Конвейер — [`architecture.md`](architecture.md). Плагины User — [`architecture.md`](architecture.md) (User — хост профиля/админки).

Цель: закрыть хвост входа после Auth 1 — политика пароля из БД, `remember` на фронте, forgot/reset на боевом API, смена пароля на `/users/:id/edit` (Auth вставляет блок через плагин User). Сессия, cookie, login/register/logout/getCurrentUser **не переписываем**, только расширяем.

Не guest, не VK, не object-ACL, не гибрид mock/real, не SMTP-продакшен (см. §5). Не `auth.findUser`. Не `user.edit` как право на пароль.

## Термины

| Термин | Смысл |
|---|---|
| Политика пароля | Один срез `{ minLength, requireMixedCase, requireDigit, requireSpecialChar }`. Auth 1 — константа; Auth 2 — каталог + привязка к группе. |
| Reset-токен | Одноразовая строка в письме/ссылке. В БД — только `hash('sha256', $raw)`. |
| `startPasswordReset` | Forgot: найти учётку, выпустить токен, инициировать доставку. Не отдаёт `User`. |
| `finalPasswordReset` | Завершение сброса: логин + токен + новый пароль. |
| Смена пароля | Аутентифицированный `auth.setPassword` на edit-странице учётки. Пароль — identity Auth, не поле `user.update`. |

Vue-эскиз `IAuthApi.findUser` / `resetPassword` — не канон Auth 2: `findUser` снимаем; `resetPassword` переименовываем в `finalPasswordReset`.

## Решения

### 1. Граница модулей

**Auth** владеет: политикой пароля, reset-токенами, `auth.startPasswordReset`, `auth.finalPasswordReset`, `auth.setPassword`, hash в `user_identity`, ключом права `auth.user.edit`.

**User** не импортирует Auth. `user.update` **без** password. Хост плагина: реестр секций edit-формы (`registerUserEditSection`), страница `/users/:id/edit` рендерит зарегистрированные блоки.

**Kernel** без изменений конвейера, кроме новых routes Auth.

Проверка политики: `register`, `user.create`, `finalPasswordReset`, `setPassword`. **Не** `login`.

### 2. Политика безопасности (пароль)

Аналог Bitrix: политика — сущность Auth, **назначается группе**. User **не** знает про политики (нет колонки на `user_group`, иначе User→Auth). Связка — таблица Auth `group_id` → `user_group`.

Эффективная политика пользователя — **наибольшая** среди политик его **активных** групп: `max(minLength)`, OR флагов `require*`. Нет привязок → политика с `is_default`. Регистрация / forgot (ещё нет учётки) → default.

| Таблица | Назначение |
|---|---|
| `auth_security_policy` | Много строк: имя, правила пароля, `is_default`. Не unique на `is_default` (иначе один `false`). Чтение default: `is_default=true`, `id` asc. Не `getById(1)`. |
| `auth_group_security_policy` | `group_id` unique → `UserGroupTable` cascade; `policy_id` → политика **restrict**. |

`auth_security_policy`: `id`, `name` required, `min_length` (default 4), три bool require*, `is_default` bool required default false, `updated_at`.

JSON `auth.getPasswordPolicy` без смены формы (без name/is_default):

```json
{ "minLength": 4, "requireMixedCase": false, "requireDigit": false, "requireSpecialChar": false }
```

Опциональный `userId`: нет/0 → default; есть → effective этой учётки (`USER_NOT_FOUND` если нет).

Seed (после bootstrap групп): нет default → insert «По умолчанию» = Auth 1; группы `assign_on_register` без связи → default. Админка политик — не этот план.

Проверка: `register` — default; `user.create` — effective **целевых** group id; `finalPasswordReset` / `setPassword` — effective **цели**. **Не** `login`.

Граф CLI: `auth_security_policy` без FK на user; mapping после `user_group`.

### 3. Таблица reset-токенов

Имя: `auth_password_reset`. Не legacy `password_reset_tokens` из [`data-model.md`](data-model.md).

| Поле | Заметки |
|---|---|
| `id` | `IdField::big()` |
| `user_id` | `reference` → `UserTable`, required, **`cascade`** |
| `token_hash` | string unique required |
| `expires_at` | datetime required |
| `used_at` | datetime nullable; `null` = не использован |

Индекс по `user_id`. Новый `startPasswordReset` для той же учётки: **delete** неиспользованных (не «пометить used» — меньше шума; тест фиксирует).

TTL: **1 час**, константа сервиса.

Сырой токен: `random_bytes` → base64url. В БД — sha256.

### 4. Actions

Существующие:

| Action | Изменение в Auth 2 |
|---|---|
| `auth.login` | Фронт шлёт `remember: bool` (бэк уже принимает). |
| `auth.getPasswordPolicy` | Default или effective по `userId`. |

Новые / замена эскиза:

| Action | Payload | Успех `data` | CSRF |
|---|---|---|---|
| `auth.startPasswordReset` | `loginOrEmail` | `{ status, login?, resetToken? }` см. §4.1 | `false` |
| `auth.finalPasswordReset` | `login`, `resetToken`, `newPassword` | `true` | `false` |
| `auth.setPassword` | `userId`, `newPassword`, `currentPassword?` | `true` | `true` |

Нет `auth.findUser`. Нет публичного lookup User по login/email без сессии.

#### 4.1. `auth.startPasswordReset`

Forgot — **один** вызов.

1. Trim `loginOrEmail`. Пустой → `AUTH_INVALID`.
2. Поиск как login: сначала login, иначе email.
3. Нет учётки / `active === false` → `data: { status: 'not_found' }`.
4. Есть, `email === null` → `data: { status: 'no_email' }`.
5. Иначе: notifier (очередь Mail), затем запись хеша токена; `data: { status: 'sent', login }` — **задача принята**, не «SMTP уже ушёл». Нет события Mail → исключение, строки reset нет. Email в JSON **не** отдавать.

Dev: `auth.expose_reset_token === true` → дополнительно `resetToken` в `data` (см. §5).

**Enumeration:** разные `not_found` / `no_email` — канон [`auth-system.md`](auth-system.md). Rate limit / унификация ответа — **не** Auth 3 (отдельный заход).

#### 4.2. `auth.finalPasswordReset`

1. Trim `login`, `resetToken`.
2. Учётка по login; нет / неактивна / битый токен / просрочен / уже списан / чужой `user_id` → один **`AUTH_INVALID`**.
3. Политика — effective **цели**. Слабый пароль → `AUTH_POLICY`.
4. Обновить `secret_hash` identity `password:{userId}`; consume = **delete по id** (второй параллельный final → `AUTH_INVALID`; `used_at` на живых строках не пишем); **delete все** `auth_session` этой учётки.
5. `true`. Автологин нет (UI → `/login`).

#### 4.3. `auth.setPassword`

Пароль — Auth. Право чужой смены — **`auth.user.edit`**, не `user.edit`.

| Кто | Условие | `currentPassword` |
|---|---|---|
| Себя (`userId` = актор) | живая сессия | **обязателен**, `password_verify` иначе `AUTH_INVALID` |
| Чужой | `hasBypass` **или** ключ `auth.user.edit` | не нужен / игнор |
| Иначе | | `AUTH_DENIED` |

Нет актора → `AUTH_REQUIRED`. Нет учётки `userId` → пробросить **`USER_NOT_FOUND`** (`UserNotFoundException` с фасада User). Нет password-identity → `AUTH_INVALID`. Неактивная учётка: forgot считает `not_found`; **`setPassword` админом разрешён** (разблокировать вход).

Политика на `newPassword`. Обновить hash. Сессии цели: **все кроме текущей cookie актора** (себе — своя жива; чужому — все сессии цели снести).

Страница `/users/:id/edit` и API — разные оси, см. §6–7.

### 5. Доставка токена (v1 без SMTP)

Один порт `IPasswordResetNotifier`: реализация **`LogPasswordResetNotifier`** (login + raw token в `error_log`). Поле `resetToken` в JSON — **не** второй notifier: флаг `auth.expose_reset_token` в `local.php` / `AuthSettings` (dist `false`). Письмо в очередь Mail — [`auth-plan-03-mail-reset.md`](auth-plan-03-mail-reset.md) (не SMTP в Auth).

`sent` отдаёт `login` (нужен query для `final`, если в форму ввели email). Email в JSON нет.

Ссылка: `/reset-password?login={login}&token={raw}`.

Forgot после `sent`: текст без адреса почты. Редирект на reset с `login` (+ `token` если expose).

### 6. Vue

Одна страница `/users/:id/edit` — удобство, **два submit**: UserForm → `user.update`; секция Auth → только `auth.setPassword`. Не смешивать в одном `@submit`.

`UserEditPage` обслуживает и `/users/new`. Секции `registerUserEditSection` монтировать **только в edit** (`:id` есть, не `UserNew`). На create пароль остаётся в UserForm → `user.create`.

| Что | Как |
|---|---|
| `IAuthApi.login` | третий аргумент `remember?: boolean` |
| `LoginPage` | switch уже есть — передать в store/API |
| Снять `findUser` | из `IAuthApi`, store, mock, ForgotPasswordPage |
| `startPasswordReset` / `finalPasswordReset` | вместо `findUser` / `resetPassword` |
| ForgotPasswordPage | один вызов `startPasswordReset`; ветки status |
| ResetPasswordPage | `finalPasswordReset`; query `login`/`token` |
| User хост | `registerUserEditSection` в `Core/User/init.ts`, контракт как `ProfileSection`: `{ id, component }`. User **не** прокидывает пропсы Auth. Монтаж секций **только на edit** (`UserEdit`, есть `:id`), не на `UserNew` |
| Auth донор | секция читает `userId` из `route.params.id`. Поля: новый + подтверждение; себе — ещё текущий пароль; своя кнопка |
| Попадание на страницу | **как сейчас**: владелец **или** `requiresAny: ['user.edit']` (bypass проходит любой ключ). **`auth.user.edit` в meta не добавлять** |
| Видимость блока пароля | владелец **этой** учётки **или** ключ `auth.user.edit` (bypass = ключ есть). Только `user.edit` на чужом профиле — блока нет |
| Моки | те же имена; expose-токен в mock по желанию |

`user.update` не шлёт password. Create по-прежнему `user.create` с password (Auth).

Человек с `auth.user.edit` без `user.edit` и не владелец **на edit не попадает** — сознательно (не плодим форму профиля, которую `user.update` отвергнет).

### 7. Право `auth.user.edit`

Реестр Vue: категория **`key: 'auth'`**, action **`key: 'user.edit'`** → ключ `` `${category}.${action}` `` = **`auth.user.edit`**. `registerPermissionCategory` из Auth `init`. PHP: `RequestActor` (`hasBypass` / `getPermissionKeys`).

Bypass-группа seed — `permissions=[]`; обход ACL закрывает ключ.

Группа с одним `user.edit` не видит блок пароля и не проходит `setPassword` на чужого.

Subset ключей при выдаче группе: User plan 4. Без bypass нельзя выдать ключ, которого нет у актора. Bypass может выдать `auth.user.edit`.

### 8. Ошибки

| Код | Когда |
|---|---|
| `AUTH_INVALID` | битый reset, неверный текущий пароль, пустые поля start, нет password-identity |
| `AUTH_POLICY` | новый пароль не проходит политику |
| `AUTH_REQUIRED` | `setPassword` без актора |
| `AUTH_DENIED` | чужой пароль без bypass и без `auth.user.edit` |
| `USER_NOT_FOUND` | `setPassword` на несуществующий `userId` |

`startPasswordReset`: `not_found` / `no_email` в `data`, не exception.

DDL не маскировать.

### 9. Слои Auth (добавления)

| Тип | Задача |
|---|---|
| `AuthSecurityPolicyTable` | каталог политик |
| `AuthGroupSecurityPolicyTable` | группа → политика |
| `AuthPasswordResetTable` | токены |
| `PasswordPolicyRepository` | default + по id |
| `GroupSecurityPolicyRepository` | bind, policy id групп |
| `PasswordResetRepository` | create, find hash, consume delete, delete unused |
| `PasswordPolicyService` | default / effective / merge; register/create/final/setPassword |
| `PasswordResetService` | start + final |
| `SetPasswordService` | ветки self / admin |
| Actions | `handle` + `IActionInput` |

## Этапы

### A — remember

Vue: `LoginPage` → store → `AuthApi`. Binder `remember` уже есть.

### B — политика в БД

Таблица политик + связь с группой, seed default на `assign_on_register`, effective = max.

### C — forgot / reset

Таблица токенов, `startPasswordReset`, `finalPasswordReset`, notifier, Vue forgot/reset, снять `findUser`. MySQL: happy, expired, used, wrong login, policy, not_found, no_email.

### D — смена пароля

`auth.setPassword`; `registerUserEditSection`; два submit на `UserEditPage`; блок пароля только владелец / `auth.user.edit`; mysql: self + current, self без current отказ, чужой + bypass, чужой без права отказ, чужой + ключ без bypass, неактивный user админом ок.

### E — доки и ворота

`auth-system.md` (пароль не `user.edit`; имена start/final; нет findUser), `user-roadmap` §7, `TR.md`, `user.md` если ключ каталога, quality PHP+Vue.

## Todo

- [x] **A-vue-remember** — `IAuthApi.login` + LoginPage + store.
- [x] **B-table-policy** — `auth_security_policy` + `auth_group_security_policy`; schema + seed; defaults v1.
- [x] **B-service-policy** — `PasswordPolicyService`; register/create/getPasswordPolicy.
- [x] **B-tests-policy** — mysql read; register reject; JSON camelCase.
- [x] **C-table-reset** — `auth_password_reset`; cascade; repo.
- [x] **C-actions-reset** — start/final; csrf false; снять findUser с контракта Vue.
- [x] **C-notifier** — Log; `expose_reset_token` в `AuthSettings` / dist.
- [x] **C-vue-forgot** — Forgot/Reset на start/final; status; expose token в query.
- [x] **C-tests-reset** — mysql §4.1–4.2.
- [x] **D-set-password** — action; категория `auth` / action `user.edit`; plugin + два submit; mysql.
- [x] **E-gate** — доки; `composer quality`; vue format/lint/tsc/vitest.

## Не входит

Guest / VK. SMTP (очередь Mail — Auth 3, сокет — Mail E). Admin UI политики. Rate limit / CAPTCHA. Унификация forgot против enumeration. Пароль в `user.update`. Смена login. TTL remember в `local.php`. `auth.findUser`. Право `user.edit` на пароль. Middleware на все action. Object-ACL. Журнал. Гибрид mock/real.

## Документы захода

этот файл; [`auth-plan-01-session.md`](auth-plan-01-session.md); [`auth-system.md`](auth-system.md); [`user-roadmap.md`](user-roadmap.md); [`user-plan-03-http.md`](user-plan-03-http.md); [`user-plan-04-groups-http.md`](user-plan-04-groups-http.md); [`user-plan-05-no-catalog-dump.md`](user-plan-05-no-catalog-dump.md); [`kernel-plan-02-action-input.md`](kernel-plan-02-action-input.md); [`architecture.md`](architecture.md) (User хост плагинов); [`data-model.md`](data-model.md) (legacy reset — не канон); [`TR.md`](TR.md); `draft-front_1.2ds/frontend-rules.md`; `www/mifrial/modules/Core/Auth/`.

## Следующий заход после кода

Почта сброса — [`auth-plan-03-mail-reset.md`](auth-plan-03-mail-reset.md) (**сделано**). Гость / члены — [`core-tails-roadmap.md`](core-tails-roadmap.md). Гибрид `VITE_API_MODE`. SmartTable plan 11 или первый Roleplay-модуль.

## Альтернативы, если этот план не берём

1. **Только remember** — политика остаётся константой.
2. **Оставить `auth.findUser`** — enumeration полного User; отклонено.
3. **Политика в `local.php`** — расходится с «таблица политики»; отклонено.
4. **Пароль через `user.update` + `user.edit`** — пароль не колонка User; отклонено.
5. **Имена `requestPasswordReset` / `resetPassword`** — неочевидная пара start/finish; отклонено в пользу `startPasswordReset` / `finalPasswordReset`.
