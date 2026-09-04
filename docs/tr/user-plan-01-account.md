# План 1 — учётка User

**Статус:** сделано, 2026-09-02. Канон — [`user.md`](user.md). Нарезка — [`user-roadmap.md`](user-roadmap.md). SmartTable — [`smarttable.md`](smarttable.md). Накат схемы — [`smarttable-plan-09-migrations.md`](smarttable-plan-09-migrations.md) (не этот модуль). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). UI-сессия — не этот заход.

Цель: модуль `Core/User`, таблица `user`, публичный фасад учётки. **Нет** HTTP, Auth/cookie, групп, identity/пароля. `loadCore()` подхватит каталог сам (код, не схему).

## Todo

- [x] **module** — `modules/Core/User/`: `module.config.php`, `IUserContainer`, autoload `Mifrial\Core\User\` + tests (phpunit suite `user`, `exclude-from-classmap` как у Kernel/SmartTable). Порт в карте: только `IUserAccounts` (не `IUser`, не Repository). Фабрика: один `open(UserTable::class)`, в репозиторий — `records()`. Definition API SmartTable (`Table/`, `Field/`, `Dto/FieldSettings`) — можно в карте. Illuminate / `ITableCatalog` — нет.
- [x] **tables** — `UserTable` имя `user`, поля как [`user.md`](user.md). `login` unique required. `email` unique, не required. `name` required. `active` required, default true. `registered_at` required datetime. Остальное nullable. `UserSchema::install()` на `IOpenedSchema`: нет `exists()` → `createTable`, иначе `updateTable`; не force. Идемпотентный **apply текущей карты**, не журнал версий. Класс не в локаторе.
- [x] **accounts** — `IUserAccounts`: `getById` → `UserRecord` (SmartTable `null` → `USER_NOT_FOUND`); `findByLogin` / `findByEmail` → `?UserRecord` через `getUnique`; `add(NewUser): int`; `update(int, UserPatch)`. **Без `install`.** Методы: 5. `registered_at`: `DateTime::now()` в фасаде. Find trim на фасаде. Коды SmartTable в `UserException` не копируем. Сосед не импортирует `UserRepository` и не зовёт `open`.
- [x] **tests-gates** — MySQL skip как ping SmartTable. `setUp`/`tearDown`: `deleteTable` если `exists()` (как `st_*`; та же `local.php` — не прод с живыми людьми). Две учётки без email; дубль login; два `null` email ок, два одинаковых email → duplicate; `''` email как `null`; пустой login / пустой name → `USER_INVALID`; лишний ключ NewUser → `USER_INVALID`; `findByLogin` miss → `null`; нет id → `USER_NOT_FOUND`; пустой patch → `USER_INVALID`; повторный install через `UserSchema`. `registered_at` — объект DateTime, unix в окне секунд. phpunit + cs/quality. README: одна строка. composer autoload prod+dev.

## DTO и нормализация

`NewUser` / `UserPatch` — из массива **присутствующих** ключей (`fromNormalized` + `fields()`). `UserRecord` — полный профиль геттерами (`getId`, `getLogin`, `isActive`, даты — `DateTime` ядра; пустые строки уже `null`). Карта ключей = колонки только в `fromNormalized` репозитория и в `fields()` New/Patch; отдельного identity-map нет. Репозиторий: `fields()` (+ `registered_at` на add), `UserRecord::fromNormalized` на чтении, ошибки строки → `USER_*`. Публичного `values()` у Record нет (`DEC-079`).

`NewUser`: обязательны `login`, `name`; опционально `email`, `surname`, `nickname`, `active` (нет ключа → true). Не содержит `registered_at`, аватар, deactivate. Неизвестный ключ → `USER_INVALID` (как у patch).

`UserPatch` — **набор присутствующих ключей**. Нет ключа — не трогаем колонку. Ключ `email` => `null` — стереть почту. `deactivated_until` — `DateTime` ядра или `null` (снять срок); не int и не строка. Пустой набор → `USER_INVALID`. Неизвестный ключ / `id` / `registered_at` → `USER_INVALID`. Разрешённые: `login`, `name`, `email`, `surname`, `nickname`, `active`, `deactivated_until`, `deactivate_reason`.

Trim `login`, `email`, `name`, `surname`, `nickname`, `deactivate_reason`. После trim: пустой `login`/`name` → `USER_INVALID`; пустой `email`/`surname`/`nickname`/`deactivate_reason` → `null`. `findByLogin` / `findByEmail` тем же trim; пустой login → `USER_INVALID`; `findByEmail` → `null` без запроса, если после trim нет значения.

Формат login/email (RFC и т.п.) **не** проверяем: любая непустая UTF-8 строка в `maxLength` поля. Уникальность — как UNIQUE в MySQL (обычно ci collation), не PHP `===`.

`active` и `deactivated_*` в плане 1 без инварианта (не выводить бан в репозитории). Смысл — HTTP deactivate / Auth позже.

## Ошибки

Листья `UserException` (`getErrorCode`, как SmartTable): `USER_NOT_FOUND`, `USER_DUPLICATE`, `USER_INVALID`.

Строка: `UniqueConstraintException` → `USER_DUPLICATE`; `FieldRequired` / `FieldInvalid` / `MapInvalid` → `USER_INVALID`; `RowNotFound` на update → `USER_NOT_FOUND`. DDL / нет таблицы / `RowWriteFailed` / `TABLE_EXISTS` **не** маскируем под `USER_*`. Гонка двух `createTable` после `exists()` — `TABLE_EXISTS`.

## Install и накат (граница)

`UserSchema::install()` — сверка карт User с БД (`createTable` / `updateTable`). Хук одного модуля: **тесты** и (когда будет) тот же набор карт через `IModuleSetup`. Переписывание строк (backfill) — data-шаг Kernel setup, не `install()`.

Прод-развёртывание всех модулей — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). `loadCore()` схему не ставит. Не раздувать `install()` в оркестратор.

## Не входит

Auth, cookie, HTTP `user.*`. Группы и bypass. Guest. Identity / hash / VK. Колонки `super_admin` / `last_login_at`. Поле `avatar`. Delete строки. `getList` админки. Extranet. CLI setup Kernel. Seed в `install-local.sh`. Clock-порт. RFC email.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| `UserTable` | `Table/` | карта `user` | identity |
| `UserRecord` / `NewUser` / `UserPatch` | `Dto/` | Record — геттеры; New/Patch — ключи | SQL, `ListQuery` |
| `UserInputNormalizer` | `Service/` | trim/ключи → DTO | SmartTable |
| `IUserAccounts` | `Interface/Service/` | фасад учётки для соседа | схема, `ListQuery` |
| `UserAccounts` | `Service/` | trim find, `DateTime::now()` на add | колонки, SmartTable, `open` |
| `UserRepository` | `Repository/` | коллекция на `IOpenedRecords`, ошибки строки | публичный порт, `open` |
| `UserSchema` | `Schema/` | `IOpenedSchema`: exists → create/update | CRUD, локатор |
| `UserException` + листья | `Exception/` | коды User | маскировать DDL / write fail |
| `IUserContainer` | `Interface/Container/` | карта портов | домен |

## Документы захода

этот файл; [`user.md`](user.md); roadmap; [`TR.md`](TR.md); `composer.json`; `phpunit.xml.dist`; README.

## Следующий заход

Группы и CLI setup закрыты. Auth 1 — [`auth-plan-01-session.md`](auth-plan-01-session.md).
