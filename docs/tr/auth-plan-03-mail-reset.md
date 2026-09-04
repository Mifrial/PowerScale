# План Auth 3 — сброс пароля через Mail

**Статус:** сделано, 2026-09-03. Предыдущий заход — [`auth-plan-02-password-flow.md`](auth-plan-02-password-flow.md) (**сделано**). Очередь — [`mail-plan-01-queue.md`](mail-plan-01-queue.md) (**сделано**). Нарезка — [`mail-roadmap.md`](mail-roadmap.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: forgot кладёт письмо в очередь Mail, а не только в `error_log` Auth. JSON `startPasswordReset` / `finalPasswordReset`, Vue, таблица `auth_password_reset`, политика пароля **не** меняются.

Это **не** SMTP-клиент и **не** rate limit. Транспорт Mail v1 по-прежнему `LogMailTransport`; письмо уходит в `error_log` после `mail.flush` (cron) или не уходит на HTTP-запросе. Настоящий SMTP — этап Mail, не этот файл.

## Термины

| Термин | Смысл |
|---|---|
| Событие сброса | Строка `mail_event.code` = `auth.password_reset`. |
| Notifier | `IPasswordResetNotifier`: Auth вызывает `notify`; Mail не знает про токены Auth. |
| Каталог Mail | Событие + шаблон. Auth **не** `open` таблицы Mail. |

## Решения

### 1. Граница модулей

**Auth** зовёт только `IMail::trigger`. Не flush, не таблицы Mail, не `IMailTransport`.

**Mail** не импортирует классы Auth. Код события `auth.password_reset` — соглашение: seed в Mail, та же строка в `MailPasswordResetNotifier`.

**User** без изменений. **Kernel** без SMTP. **Vue** без нового action.

`flush_inline` dist **false** не включать в Auth 3: forgot на HTTP не должен ждать транспорт.

### 2. Notifier

Заменить сборку в `AuthServiceFactory::createPasswordReset`: вместо одного `LogPasswordResetNotifier` — **`MailPasswordResetNotifier`**.

Контракт `IPasswordResetNotifier` расширить email (он уже есть в `startPasswordReset` до `notify`):

```text
notify(login, rawToken, email): void
shouldExposeRawToken(): bool
```

`LogPasswordResetNotifier` оставить для mysql-тестов Auth без модуля Mail (как сейчас). Боевой контейнер — Mail-реализация.

`MailPasswordResetNotifier`:

- `shouldExposeRawToken` — по-прежнему `AuthSettings` / `expose_reset_token`. JSON `resetToken` **не** второй канал доставки.
- `notify` → `IMail::trigger('auth.password_reset', payload)`.
- Payload v1, все значения строки: `login`, `token`, `email`.
- Нет `flush`. Нет абсолютного URL сайта в PHP (в шаблоне относительный путь).

Если события нет — `IMail` бросает `MAIL_INVALID`. **Не** маскировать в `AUTH_INVALID`. Хеш токена пишется **после** успешного `trigger`; при исключении строки `auth_password_reset` нет. HTTP — `INTERNAL` (MailException не ActionException).

`status: sent` в JSON — очередь принята, не факт доставки транспортом.

Не писать параллельно token в `error_log` Auth: дубль с `LogMailTransport` после flush.

### 3. Seed каталога (без таблиц Mail в Auth)

`IMail` остаётся **только** `trigger` (Mail 1). Для идемпотентного seed нужен второй порт соседа Mail:

**`IMailCatalog`** (карта `ports` Mail, не Auth-таблицы):

| Метод | Смысл |
|---|---|
| `ensureEvent(code, name, ?description)` | Нет строки → insert; есть → не менять name/description |
| `ensureTemplate(eventCode, name, emailFrom, emailTo, subject, body)` | Нет шаблона с этой парой event+name → insert `active=true`; есть → не менять поля |

Auth не передаёт `event_id`. Mail репозитории внутри каталога.

Data-шаг Auth `Core/Auth:seed.mail-password-reset`:

- событие `auth.password_reset`, имя «Сброс пароля»;
- шаблон имя `default`: `email_from` = `noreply@localhost`, `email_to` = `{{email}}`, subject/body с `{{login}}` и `{{token}}`, ссылка `/reset-password?login={{login}}&token={{token}}`.

Порядок CLI: все DDL, затем data-шаги по `Group/Name` (`Core/Auth` раньше `Core/Mail`). **Проблема:** шаг Auth, который пишет в Mail-таблицы, не может идти до DDL Mail.

**Решение:** шаг seed каталога живёт в **Mail**, не в Auth: `Core/Mail:seed.auth-password-reset`. Mail **знает строковый код** `auth.password_reset` как соглашение (как Agent знает, что донор сам пишет `ensureAgent`). Auth в setup Mail не импортирует. Альтернатива «Auth-шаг после Mail» ломает сортировку `Group/Name` без отдельного механизма порядка шагов — **не** вводим.

Итого seed — **data-шаг Mail** с фиксированным кодом события Auth. Auth 3 в PHP Auth только notifier + factory. Документировать соглашение кода в обоих планах.

(Отклонено: Auth `open(MailEventTable)`.)

`IMailCatalog` всё равно нужен, чтобы Mail-шаг не ходил в ST в обход репозиториев? Шаг Mail **может** звать репозитории Mail напрямую — это тот же модуль. Тогда **`IMailCatalog` не нужен в Auth 3**. Порт каталога — когда появится админка писем, не сейчас.

**Канон Auth 3:** `EnsureAuthPasswordResetMailStep` в `Core/Mail/Setup`, репозитории Mail, идемпотентно. Auth — только `MailPasswordResetNotifier`.

### 4. `AuthModuleSetup` и лимит ctor

В Auth 3 **не** добавлять 7-й аргумент setup. Seed почты не в Auth setup.

### 5. Тесты

- Auth mysql: fake `IMail` (запись trigger); `notify` с login/token/email; expose как сейчас.
- Mail mysql: шаг seed создаёт событие+шаблон; повтор шага не дублирует; `trigger('auth.password_reset', …)` + flush → to=email, body содержит token.
- Не требовать SMTP. Не включать `flush_inline` в Auth-тестах HTTP.

Vue / JSON status `not_found` / `no_email` / `sent` без изменений.

### 6. Ошибки и JSON

Листья HTTP те же, что Auth 2. Новый код Auth не вводим. `MAIL_INVALID` с незасеянным событием не переименовывать.

### 7. Что сознательно не этот план

Настоящий SMTP (Mail E). Rate limit / CAPTCHA / один ответ forgot против enumeration (в Auth 2 ошибочно помечены «Auth 3» — **снимаем** с этого файла, остаются «не входит» без номера). Админка шаблонов. Абсолютный `publicUrl` в `local.php`. `flush_inline` на forgot. Смена Vue. Guest.

## Этапы

### A — seed Mail

Шаг `Core/Mail:seed.auth-password-reset`. Mysql идемпотентность.

### B — notifier Auth

`notify(..., email)`, `MailPasswordResetNotifier`, factory. Mysql Auth с fake IMail. Log-notifier в тестах Auth без Mail.

### C — доки

Этот файл; roadmap; Auth 2 «следующий»; TR; architecture ребро Auth → `IMail`. Quality + phpunit `auth` + `mail`.

## Todo

- [x] **A-seed** — Mail data-шаг события/шаблона `auth.password_reset`.
- [x] **B-notifier** — email в `notify`; Mail-реализация; factory.
- [x] **C-gate** — доки; quality; phpunit.

## Не входит

SMTP-сокет. Rate limit. Унификация enumeration. Vue. Админка Mail. `IMailCatalog` как публичный порт (отложен до админки). `publicUrl`. `flush_inline` для forgot.

## Документы захода

этот файл; [`auth-plan-02-password-flow.md`](auth-plan-02-password-flow.md); [`mail-plan-01-queue.md`](mail-plan-01-queue.md); [`mail-roadmap.md`](mail-roadmap.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md).
