# План Mail 1 — события, шаблоны, очередь

**Статус:** сделано, 2026-09-03. Транспорт v1 — `LogMailTransport`; SMTP — не этот заход. Нарезка — [`mail-roadmap.md`](mail-roadmap.md). Agent — [`agent-plan-01-tick.md`](agent-plan-01-tick.md) (**блокер**). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Forgot — [`auth-plan-02-password-flow.md`](auth-plan-02-password-flow.md) (SMTP не этот файл).

Цель: модуль `Core/Mail` принимает «событие + поля», пишет очередь, подставляет плейсхолдеры в шаблоны, отдаёт транспорт. Тик очереди — агент `mail.flush`, не cron в Mail. Auth **не** импортирует таблицы Mail; Auth 3 — следующий план.

На диске `Core/Mail` с container, setup, без HTTP routes в этом заходе.

## Термины

| Термин | Смысл |
|---|---|
| Событие | Строка каталога `mail_event`: `code` + имя (+ описание). Не письмо. |
| Шаблон | Как собрать from/to/subject/body для события. Несколько активных — несколько писем на job. |
| Job | Экземпляр очереди: событие + карта полей + статус. Рендер **в flush**, не в trigger. |
| Плейсхолдер | `{{name}}` или `{{name@default:'текст'}}` в любом из from/to/subject/body. |

## Решения

### 1. Граница модулей

**Mail** владеет тремя картами, рендером, транспортом.

Публичный порт соседа **`IMail`**: только `trigger`. `flush` / `flushJob` — внутренний сервис Mail, его зовут обработчик агента и тесты. Auth не сбрасывает очередь.

**Agent:** data-шаг `ensureAgent('mail.flush', 60)`. В `module.config` Mail: `'agents' => ['mail.flush' => MailFlushHandler::class]`. Handler в `ports` контейнера Mail. Mail не импортирует `Agent/Service` (только `IAgents` на ensure). Cron не пилит.

**Auth** в этом плане не трогаем. `LogPasswordResetNotifier` остаётся. User Mail не импортирует. Kernel SMTP не знает.

**Транспорт** внутри Mail. Не публиковать PHP `mail()` / сокет в `Interface/` соседа.

### 2. Три карты SmartTable

Имена: `mail_event`, `mail_template`, `mail_job`. Не `MailActions` (путать с HTTP action).

**`mail_event`**

| Поле | Заметки |
|---|---|
| `id` | |
| `code` | string unique required; точка в коде ок (`auth.password_reset`) |
| `name` | string required |
| `description` | text nullable |

**`mail_template`**

| Поле | Заметки |
|---|---|
| `id` | |
| `event_id` | `reference` → `MailEventTable`, required, **restrict** |
| `name` | string required (человекочитаемый ярлык шаблона) |
| `email_from` | string required, maxLength 255; после подстановки не пустой; без CR/LF |
| `email_to` | string required, maxLength 255; после подстановки не пустой; без CR/LF; v1 одно поле (запятые как сырая строка транспорту) |
| `subject` | string required, `maxLength` 1024 |
| `body` | **text** required |
| `active` | bool required, default true |

Несколько шаблонов на одно событие. Не unique на `event_id`.

**`mail_job`**

Очередь растёт. PK — **`IdField::big()`**.

| Поле | Заметки |
|---|---|
| `id` | `IdField::big()` |
| `event_id` | `reference` → `MailEventTable`, required, **restrict** |
| `payload` | json required; карта `string => string` (на входе скаляр → stringify; массив/объект → `MAIL_INVALID`) |
| `status` | string required; v1: `pending` / `sent` / `failed` |
| `created_at` | datetime required, default now |
| `sent_at` | datetime nullable |
| `attempts` | int required, default 0 |
| `last_error` | text nullable |

Индекс по `status` (и `created_at` — сортировка flush). Удалять sent в v1 не надо. `failed` **терминальный**: flush его не берёт (иначе повтор удвоит уже ушедшие шаблоны). Ручной retry / пошаблонный статус — не v1.

Граф CLI: event → template и job. Agent FK нет. `MailSchema::install()` для mysql-тестов. Data-шаг Mail: агент `mail.flush` (interval 60). Каталог событий Auth **не** сеем здесь.

### 3. Фасад

**`IMail`** (карта портов, сосед):

| Метод | Смысл |
|---|---|
| `trigger(eventCode, payload)` | Trim code; нет события → `MAIL_INVALID`; insert job `pending`. Не шлёт почту |

**Внутри Mail** (`MailFlushService`, не порт Auth):

| Метод | Смысл |
|---|---|
| `flush(?limit)` | Pending, `created_at` asc, страницы до 500 |
| `flushJob(id)` | Один pending job (inline) |

Нет события — job не создаём. Нет активных шаблонов на flush — `failed`, `last_error`. Рендер падает (пустой from/to, CR/LF в from/to, битый `{{`) — job `failed`, если **хотя бы один** шаблон не ушёл. Частичный sent: уже ушедшие не отзываем; job `failed`; **не** возвращаем в pending.

`flush_inline` в срезе `mail` `local.php` (dist **false**): после `trigger` — `flushJob` этого id. Тесты могут включить. Прод / HTTP forgot — агент, иначе SMTP на запросе.

Сосед не `open` таблицы Mail.

### 4. Плейсхолдеры

Формат v1, без вложенности:

```text
{{ident}}
{{ident@default:'текст по умолчанию'}}
```

- `ident`: `[A-Za-z_][A-Za-z0-9_]*`.
- Пробелы внутри `{{` … `}}` запрещены.
- В default — любые символы кроме `'`. Экрана нет. Нужна кавычка в default — не этот заход.
- Значение из `payload[ident]`; пустая строка payload = пусто → default, если он есть, иначе `''`.
- Ключа нет → как пусто.
- Лишние ключи payload игнор.
- Неразобранный `{{` как текст не оставляем: неизвестный/битый токен → `MAIL_INVALID` на flush этого шаблона (job failed).
- Подстановка во **всех** четырёх полях шаблона.
- После рендера `email_from` / `email_to`: trim; пусто или содержат `\r`/`\n` → `MAIL_INVALID` (header injection).

Не Mustache, не Twig, не HTML-sanitize в v1 (body как есть).

### 5. Транспорт

Порт **внутри** Mail: `IMailTransport` в `Service/` (не `Interface/` соседа). `send(from, to, subject, body): void`.

v1 реализации:

1. **`LogMailTransport`** — `error_log` (как нынешний reset-notifier). Dist / тесты.
2. **SMTP** — этап E того же модуля, не блокер A–D. Нет smtp в конфиге → лог. Решение клиента (сокет vs одна зависимость) — при коде E, не Symfony «на всякий случай». `mail()` PHP не канон.

### 6. Агент `mail.flush`

Code: `mail.flush`. Interval: 60 сек. Handler: `MailFlushHandler` → `MailFlushService::flush()`. Ключ `agents` + data-шаг `ensureAgent`. Биндинг handler — `bin/agent.php`, не HTTP boot.

Mail без агента всё ещё принимает `trigger`; письма уйдут после тика или inline.

### 7. Ошибки

| Код | Когда |
|---|---|
| `MAIL_INVALID` | Нет event code, битый payload, битый плейсхолдер, пустой/CRLF from/to |
| `MAIL_TRANSPORT` | Транспорт не смог отправить (опционально тот же failed-path без отдельного кода наружу, если нет HTTP) |

HTTP routes нет — листья для mysql-тестов и будущего Auth 3. Не маскировать DDL.

### 8. Слои

| Тип | Задача |
|---|---|
| `MailEventTable` / `MailTemplateTable` / `MailJobTable` | карты |
| репозитории | event by code, templates by event, jobs pending |
| `PlaceholderRenderer` | только подстановка, без ST |
| сервис `trigger` (`IMail`) | порт соседа; insert job |
| `MailFlushService` | `flush` / `flushJob` |
| `LogMailTransport` | адаптер v1 |
| `MailFlushHandler` | `IAgentHandler`, порт контейнера Mail |

## Этапы

### A — модуль и DDL

Три карты, schema, setup, граф FK. Mysql create.

### B — trigger

Фасад, payload json, неизвестный code. Без отправки.

### C — рендер и flush

Плейсхолдеры, шаблоны, статусы, Log-транспорт. Inline-флаг.

### D — агент

ensure + bind; mysql: tick Agent зовёт flush. CLI уже из плана Agent.

### E — SMTP (не блокер лога) и доки

`local.php.dist` срез `mail`; autoload + suite `mail`; architecture; TR; README. Quality + phpunit.

## Todo

- [x] **A-tables** — три карты; schema; setup; composer autoload; suite `mail`.
- [x] **B-trigger** — `IMail::trigger`; mysql unknown code / insert.
- [x] **C-flush** — renderer; CR/LF; active templates; pending→sent/failed; `flushJob`; inline.
- [x] **D-agent** — ключ `agents`; `ensureAgent`; mysql tick → flush.
- [x] **E-gate** — dist `mail`; лог-транспорт; SMTP отдельно; доки; quality.

## Не входит

Auth 3 / смена notifier — [`auth-plan-03-mail-reset.md`](auth-plan-03-mail-reset.md). Админка событий и шаблонов. HTTP `mail.*`. Вложения. Очередь не-email (SMS). Мультиязычность шаблонов. Mustache/Twig. Rate limit forgot. Vue. Purge sent. Параллельный flush двух процессов (без lock в v1).

## Документы захода

этот файл; [`mail-roadmap.md`](mail-roadmap.md); [`agent-plan-01-tick.md`](agent-plan-01-tick.md); [`auth-plan-02-password-flow.md`](auth-plan-02-password-flow.md); [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md); [`smarttable-plan-15-bigint.md`](smarttable-plan-15-bigint.md) (`mail_job` big id); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md); [`TR.md`](TR.md).
