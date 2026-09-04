# План Kernel 2 — DTO входа action

**Статус:** сделано, 2026-09-03. Канон конвейера — [`architecture.md`](architecture.md) (`DEC-074`). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Актор — не этот план ([`user-plan-03-http.md`](user-plan-03-http.md)). HTTP групп — не этот план ([`user-plan-04-groups-http.md`](user-plan-04-groups-http.md)).

Цель: `ActionParameterBinder` собирает **входной DTO** из JSON. Толстый `handle` с семью `?T = null` уходит. Скалярный `handle(int $id)` остаётся. `handle(mixed $payload)` по-прежнему запрещён.

До захода `?string $name = null` не отличал «ключа нет» от JSON `null`. Для `user.update` guard смотрел `$groups !== null`: это не «ключ есть» из плана 3. DTO + `isPresent()` закрывает разрыв.

## Термины

| Термин | Смысл |
|---|---|
| Input-DTO | `final` класс в **`Dto/Action/`** модуля, маркер `IActionInput`. Имена **параметров конструктора** = ключи JSON. Не Record/Patch, не сервис. |
| `Optional*` | Листья в `Kernel\Value\Optional\`. Предок `OptionalValue`: `isPresent()` / `assertPresent()`. |
| Binder | `ActionParameterBinder`. Не `IRequestBinder`. |

Имя параметра `handle(UpdateUserInput $input)` **не** ключ JSON. Тело `{id, name, …}`, не `{input: {…}}`.

## Решения

### 1. Один JSON-корень, две формы `handle`

Один объект тела `runAction` → **один** корень гидрации. Смысловые группы (профиль / группы / active) — **поля одного** DTO, не `handle(Profile $a, Membership $b)` и не вложенный JSON.

1. **Скаляры / array / backed enum** — как сейчас.
2. **Ровно один** параметр `IActionInput`, без default и без `?`. Extra/missing — по **конструктору** DTO.

Смесь с скалярами, два input, `stdClass`, сервис, класс без маркера, **`DateTime` ядра** — `Unsupported parameter`. `DateTime` в binder **не** этот заход (`Y-m-d` на deactivate по-прежнему парсит HTTP-сценарий).

Пустой `handle()` не трогаем. `null`/`{}` тела = пустой объект; нет обязательного `id` → `Missing parameter: id`.

### 2. Контракт DTO

- Папка **`Dto/Action/`** (не общая куча с `UserRecord` / `UserPatch`: другая причина меняться). Не зеркало в `Interface/`.
- `final`, публичный конструктор, `implements IActionInput` (`Kernel\Interface\Action`, не порт контейнера).
- Promoted `readonly`. Не `values()`.
- Параметры ctor: `int|float|string|bool|array`, `?string` (nullable скаляр **с default**: нет ключа = JSON `null` = «не пишем»), backed enum, листья `OptionalValue`.
- Нет: `IOptional`, вложенный `IActionInput`, `DateTime`, union, `mixed`, `?OptionalString`. `OptionalInt` позже = новый лист, binder только `is_a(OptionalValue)`.
- Default на не-Optional (пример: `array $groups = []` на create) — нет ключа → default.
- Типы скаляров как сейчас (`1.0` → int ок).

Гидрация только с reflection `handle` → ctor; не class-string из JSON.

### 3. Когда `Optional*`, когда `?string = null`

`Optional*` — **только если** сценарий различает «ключа нет» и «ключ есть» (update / deactivate).

Иерархия: абстрактный `OptionalValue` (не интерфейс) + `final` листья в `Value/Optional/`. На предке — флаг и `isPresent()` / `assertPresent()`. Binder: `is_a(..., OptionalValue::class)`, не список классов. Типизированный `present` и `fromJson(mixed): static` — на листе (плохой JSON → исключение, binder → `INVALID_PARAMS`). `getValue()` на листе зовёт `assertPresent()`.

| JSON | `OptionalString` | `OptionalBool` / `OptionalArray` |
|---|---|---|
| нет ключа | `absent()` | `absent()` |
| `null` | `present(null)` | `INVALID_PARAMS` |
| значение типа | `present('Ann')` | `present(false)` / `present([])` |

`getValue()` на absent — баг сценария, не клиент. Не `ActionException` (стало бы 400). **`KernelException` с кодом `OPTIONAL_ABSENT`**: в Kernel инфрасбои уже так (`PORT_TYPE`, `INTERNAL`), без листа на каждый код. Отдельный `OptionalAbsentException` не заводим. PHP-default у `Optional*` нет.

**Create** (surname/nickname): различия нет — **не** `OptionalString`. `?string $surname = null`: нет ключа и JSON `null` → в профиль **не пишем** (как сейчас `!== null`).

**Update:** `isPresent()` на groups/active для self-edit и replace членства. `present(null)` у email/surname/nickname/name — ключ был, в patch кладём `null` (стереть, если нормализатор пускает). Форма Vue ключ с `null` не шлёт.

Скалярный короткий `handle(?string $x = null)` не меняем.

Vue: `undefined` выкидывается → absent. Явный `"groups": null` — `INVALID_PARAMS` (`UserApi` так не шлёт).

### 4. Слои

| Тип | Задача | Не делает |
|---|---|---|
| `IActionInput` | маркер | поля |
| `OptionalValue` + листья | присутствие ключа; `fromJson` на листе | HTTP-конверт, SQL, DateTime |
| Binder | JSON → `handle` / ctor DTO | актор, CSRF, `fromUnix` |
| `Dto/Action/` | форма JSON action | колонки таблицы |
| Action | `handle(UpdateUserInput $input)` | семь nullable |
| HTTP-сценарий | `isPresent()` / скаляры; `Y-m-d` | `ListQuery` |

Сервисы принимают тот же input. Тесты — тоже.

### 5. Миграция в том же заходе

| Action | Класс | Поля |
|---|---|---|
| `user.update` | `User\Dto\Action\UpdateUserInput` | `int $id`; name/surname/nickname/email `OptionalString`; groups `OptionalArray`; active `OptionalBool` |
| `user.create` | `Auth\Dto\Action\UserCreateInput` | `name`, `login`, `email`, `password` string; `array $groups = []`; `?string $surname = null`, `?string $nickname = null` |
| `user.deactivate` | `User\Dto\Action\DeactivateUserInput` | `int $id`; reason / deactivatedUntil `OptionalString` (строка `Y-m-d`, не `DateTime`) |

Не обязаны: get / getByIds / getList, login / register, ping.

Тесты Kernel: плоский JSON на DTO; extra; missing; Optional absent/`null`/значение; смесь и `stdClass`; `DateTime` на `handle` unsupported; `null` тела без id. Mysql user/auth зелёные.

### 6. Канон при коде

`architecture.md` и `DEC-074`: JSON на параметры `handle` **или** на конструктор единственного `IActionInput` (ключи ctor). Сервисы в `handle` нельзя. Value `DateTime` — по-прежнему unix-instant, не парсер HTTP.

## Ошибки

Binder: `INVALID_PARAMS`. Домен — после гидрации. `getValue()` на absent — INTERNAL.

## Todo

- [x] **optional-value** — `OptionalValue` + три листа в `Value/Optional/`; unit.
- [x] **binder-dto** — один input; ctor; default; отказ смеси / DateTime / чужих классов; unit.
- [x] **migrate-update-create-deactivate** — `Dto/Action/` + сервисы; guard `isPresent()`; create `?string` не пишет поле.
- [x] **canon** — `architecture.md` / `DEC-074`.
- [x] **quality** — phpcs-quality + phpunit `kernel,user,auth`.

## Следующий заход после кода

Таблица политики пароля Auth; `remember` на Vue — если спросят. Журнал событий — когда будет UI ленты.

## Не входит

Вложенные DTO / второй корень JSON. Binder→`DateTime` / `fromUnix`. `OptionalInt`. Union / `mixed`. `handle(mixed)`. Vue. HTTP групп. Политика пароля. DTO ответа. Переименование `DateTime`. Attributes.

## Документы захода

этот файл; [`architecture.md`](architecture.md); [`decisions.md`](decisions.md); [`php-coding-standards.md`](php-coding-standards.md); [`user-plan-03-http.md`](user-plan-03-http.md); [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md).
