# Core\Auth

Карта входа: как пользоваться аутентификацией. Пути даны от корня репозитория.

**Роль:** вход, регистрация, гостевая сессия, выход, «кто я», политика пароля,
сброс и установка пароля, админское создание учётки. Держит сессионные cookie и
кладёт актора запроса (`RequestActor`) в контекст, откуда его читает система
прав `Core/User`.

**Канон:** [`auth-system.md`](../../../../../docs/tr/auth-system.md).
Протоколы работ (всё `сделано`, читать как историю) —
[`auth-plan-01-session.md`](../../../../../docs/tr/auth-plan-01-session.md) …
[`auth-plan-04-guest.md`](../../../../../docs/tr/auth-plan-04-guest.md).

**Сверено по коду:** 2026-10-18.

## Ключевые типы

| Тип | Путь | Зачем |
|---|---|---|
| `AuthService` | `Service/AuthService.php` | Порт: `login`, `register`, `logout`, `openGuest`, `getCurrentUser`, `resolveActor`, `getPasswordPolicy` |
| `AuthSessionBinder` | `Service/AuthSessionBinder.php` | Порт `request_bind`: кладёт актора в `IRequestContext` |
| `UserCreateService` | `Service/UserCreateService.php` | Админское создание учётки (требует `user.create`) |
| `SetPasswordService` | `Service/SetPasswordService.php` | Смена пароля: себе всегда, чужому — bypass или `auth.user.edit` |
| `PasswordResetService` | `Service/PasswordResetService.php` | Старт и финализация сброса |
| `IPasswordResetNotifier` | `Interface/Service/IPasswordResetNotifier.php` | Точка подмены уведомителя |
| `MailPasswordResetNotifier` / `LogPasswordResetNotifier` | `Service/*.php` | Реализации: Mail-событие / запись в лог |
| `AuthServiceFactory` | `Service/AuthServiceFactory.php` | Сборка портов, action-ов и setup |
| `AuthSessionRuntime` / `AuthCookieIssuer` | `Service/*.php` | Строки сессии и выдача cookie |
| `IAuthContainer` / `AuthContainer` | `Interface/Container/IAuthContainer.php`, `Container/AuthContainer.php` | Локатор модуля |
| Action-классы | `Action/*.php` | 10 маршрутов (см. ниже) |
| DTO входов | `Dto/Action/*.php` | `LoginInput` и др. через `IActionInput` |
| `AuthSettings` / `PasswordPolicy` | `Dto/*.php` | Срез `auth` из `local.php`; правила пароля |
| Repository | `Repository/*.php` | Доступ к 5 таблицам Auth |
| Table / `AuthSchema` / `AuthModuleSetup` | `Table/*.php`, `Schema/AuthSchema.php`, `Setup/AuthModuleSetup.php` | Карты SmartTable и накат |
| Setup-шаги | `Setup/BootstrapGroupsStep.php`, `Setup/SeedPasswordPolicyStep.php` | Data-шаги |
| Исключения | `Exception/*.php` | `AuthException`, `AuthInvalidException`, `AuthDuplicateException`, `AuthPolicyException` |

## Как пользоваться

### Порт из фабрики

```php
/** @var AuthService $auth */
$auth = $serviceLocator->get(IAuthContainer::class)->get(AuthService::class);
```

### Сессия и актор

```php
$auth->login('alice', 'secret', false);   // remember: false → 86400 c, true → 2592000 c
$auth->getCurrentUser();                  // ['kind' => 'user'|'guest', ...] | null
$auth->resolveActor();                    // ?RequestActor (гость → null)
$auth->logout();
```

На HTTP-запросе актора ставит `AuthSessionBinder::bind(IRequestContext)` —
модуль объявляет его ключом `request_bind`. Права дальше проверяет
`Core/User` (`IUserAccess::requireKey(...)`).

### Маршруты

| Action | `csrf` |
|---|---|
| `auth.login`, `auth.register`, `auth.getCurrentUser`, `auth.guest`, `auth.getPasswordPolicy`, `auth.startPasswordReset`, `auth.finalPasswordReset` | `false` |
| `auth.logout`, `user.create`, `auth.setPassword` | `true` |

### Setup

`AuthSchema` накатывает 5 карт: `user_identity`, `auth_session`,
`auth_security_policy`, `auth_group_security_policy`, `auth_password_reset`.

## Инварианты

- Cookie: `mifrial-session` (HttpOnly) и `csrf-token`. Сырые токены в БД — только
  как `sha256`. TTL сессии 86400 c, «remember» 2592000 c, reset-токен 3600 c.
- `register`, `user.create`, `setPassword`, `finalPasswordReset` выполняются в
  транзакции через `ITransactionRunner` (реализация — `Core/SmartTable`); сбой →
  откат.
- `register` требует хотя бы одной группы с флагом `assign_on_register`, иначе
  `AuthInvalidException`. Учётка создаётся активной, `name = login`, email —
  после trim и только если непустой.
- Пустой `login` при входе → `AuthInvalidException`; вход отвергается при
  неактивной учётке или несовпадении identity/пароля.
- `setPassword` для чужой учётки требует bypass или ключа `auth.user.edit`; себе
  — без доп. проверок.
- `UserCreateService::create` требует ключ `user.create` и отдельно проверяет
  право назначать bypass-группы.
- Политика по умолчанию: `min_length = 4`, остальные флаги `false`;
  `strictest()` берёт максимальную длину и OR-объединение флагов.
- `getPasswordPolicy(null)` — политика по умолчанию; `getPasswordPolicy($userId)`
  требует существования учётки.
- `AuthSettings::fromSection`: `null` → пустой срез (`cookie_secure = false`);
  не-массив → `SetupException('SETUP_INVALID')`; оператор требует непустые
  `operator_login`/`operator_password`/`operator_name`.
- Auth не импортируется прикладными модулями напрямую: права и учётки идут через
  порты `Core/User`.
- Транзакция и запись строк — только через порты `Core/SmartTable`; SQL в
  репозиториях Auth нет.

## Точки расширения

- **Новый и/или подмена порта:** `module.config.php` модуля Auth
  (`ports`) — Action-ы и сервисы объявлены там же.
- **Другой уведомитель сброса:** реализация `IPasswordResetNotifier`; в коде есть
  Mail-вариант и лог-вариант.
- **Политики:** таблицы `auth_security_policy` / `auth_group_security_policy` и
  соответствующие репозитории.
- **Data-шаги setup:** `implements ISetupStep`, регистрируются в
  `AuthModuleSetup::getDataSteps()`.

## Зависимости

- Импортирует: `Core/Kernel` (`ITransactionRunner`, `RequestActor`, `DateTime`,
  `IRequestBinder`, `IRequestContext`), `Core/User` (`IUserAccounts`,
  `IUserGroups`, `IUserViews`, `IUserAccess`), `Core/SmartTable`.
- Импортируется: только тестами и setup-коллектором Kernel; прикладные модули
  работают через `Core/User`.

## Тесты

- `tests/AuthMysqlTest.php` — login/register/logout/guest/getCurrentUser/
  resolveActor, cookie и строки сессии (MySQL, self-skip без БД).
- `tests/AuthWorkflowAtomicityMysqlTest.php` — атомарность `register`/
  `user.create`/`setPassword` и откаты.
- `tests/AuthModuleSetupTest.php` — setup отдаёт те же class-string, что
  `AuthSchema`.
- `tests/AuthSettingsTest.php` — разбор среза `auth`, `cookie_secure`,
  `SETUP_INVALID`.
- `tests/PasswordPolicyTest.php` — `defaults`, `strictest`, проверки, `toJson`.
- `tests/MailPasswordResetNotifierTest.php` — событие `auth.password_reset`.

## Не описано / OPEN

- `Setup/GrantAssignOnRegisterPermissionsStep.php` существует (id
  `Core/Auth:seed.assign-on-register-create-keys`), но **не зарегистрирован** в
  `AuthModuleSetup::getDataSteps()` — фактически мёртвый шаг.
- Не реализовано в модуле: общая проверка прав (живёт в `Core/User`), OAuth/2FA,
  rate-limit, аудит входов (см. `Core/Logger`).
