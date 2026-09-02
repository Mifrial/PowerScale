# План Kernel 1 — установка и обновление модулей

**Статус:** сделано, 2026-09-02. Канон модулей — [`architecture.md`](architecture.md). SmartTable DDL — [`smarttable.md`](smarttable.md). Решение «не в SmartTable» — [`smarttable-plan-09-migrations.md`](smarttable-plan-09-migrations.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). User `install()` — [`user-plan-01-account.md`](user-plan-01-account.md).

Цель: CLI приводит физику **всех** модулей каталога к текущим картам SmartTable и прогоняет ещё не применённые **шаги данных**. Не Laravel migrate, не папка `update/` как в Битриксе, не журнал аудита/проблем.

## Термины

| Термин | Смысл |
|---|---|
| Установка / обновление модуля | Этот план. Код: `IModuleSetup`, в Kernel — прогон. |
| Миграция схемы | То же в речи; не путать с картой ТР и с миграцией персонажа на ревизию правил. |
| Реестр шагов | Строки в БД: какие data-шаги уже прошли в **этой** базе. Не лог событий. |
| Журнал | Аудит, проблемы, экономика (`DEC-047`). **Не этот план.** |
| `UserSchema::install()` | Идемпотентная сверка карт **одного** модуля. Тесты и частный вызов. Прод-CLI идёт графом всех модулей. |

Имя отдельного модуля `Core/Migration` / `Journal` / `VersionController` **не** заводим. Это часть **Kernel** + контракт у модуля.

## Решения

### 1. Кто что делает

**Модуль** знает свои карты и свои data-шаги. Не знает порядок соседей.

**Kernel** (composition root + CLI): загружает каталог модулей, собирает карты, **сортирует таблицы по графу `reference` → физ. имя цели**, зовёт `createTable` / `updateTable`, затем непройденные data-шаги, пишет реестр.

HTTP `loadCore()` / ленивые слоты **не** меняются: запрос по-прежнему не ставит схему.

`ApplicationFactory::boot()` сейчас: `loadCore` → bindEager → lazy **слоты** → **freeze**. После freeze новый `requireModule` в граф контейнеров не вставить. CLI **не** «взял HTTP-boot и догрузил Game». Отдельный вход setup: обойти группы на диске (`modules/{Group}/{Name}/module.config.php`), `requireModule` все, bindEager, freeze, прогон. Фикстуры тестов Kernel вне `modules/` не входят. Запись только в `config/modules.php` lazy без каталога на диске — слот HTTP, в setup её нет, пока нет папки модуля.

Модуль без ключа `setup` в граф DDL не входит (нет таблиц: сейчас Ping). **Kernel** ключ `setup` имеет: карта реестра шагов.

`IModuleSetup` **не** порт соседа в карте `get()` для Auth/HTTP. Ключ `setup` в `module.config.php`, читает только CLI/Kernel Setup.

### 2. Схема: граф таблиц, не порядок модулей и не `update/`

Карта PHP-класса **уже** описание DDL. Нумерованные файлы `update/1.2.0/*.php` с колонками **не** заводим: разъедутся с `getMap()`.

Подъём:

1. Загрузить все модули каталога.
2. У каждого setup — class-string карт (`UserTable`, …).
3. Инстанциировать definition (без SQL). Ребро: поле `reference` → `targetTableName()` (class-string или `forTable`).
4. Топологический порядок **физических имён**. Self-ref ребро на себя в сортировке не блокирует: стол создаётся сам.
5. Для каждой таблицы: нет физики → `createTable`, есть → `updateTable`. Не `forceUpdateTable`.
6. Цель FK нет **ни в одной** карте этого прогона и нет физики → отказ (`TABLE_MISSING`). Цель только в словаре — не этот контур.
7. Два модуля, одно физ. имя — отказ setup до DDL. `onDelete === none` ребра в граф не кладём (физического FK нет). Sidecar mfv — часть `createTable` владельца, не вершины графа.
8. Definition только no-arg (как ctor `reference` на class-string).

Порядок модулей в `modules.php` **не** задаёт DDL. Зашитый порядок в `UserSchema::install()` (`user` → группа → членство) остаётся для тестов; CLI тот же порядок получит из графа.

Цикл A→B и B→A: в текущих картах нет. Детект цикла — ошибка setup. Двухпроходный DDL (стол без FK, потом FK) — **не этот заход** (понадобится правка SmartTable).

Словарь runtime в граф **не** входит.

### 3. Слой Kernel и SmartTable

Прогон живёт в `Core/Kernel` (CLI + оркестратор). Он **клиент** `ISmartTableGateway`: `open` + `schema()`. Это не вторая причина меняться у SmartTable (не `ISchemaMigrator` в ST).

Разбор рёбер `ReferenceField` в одном типе Kernel Setup допустим (иначе порт «отсортируй карты» в ST ради одного CLI). Dispatcher и ping SmartTable не импортируют.

Реестр шагов — таблица SmartTable **модуля Kernel** (карта в `Kernel/Table/`), в том же графе, без исходящих FK: создастся рано. Версия кода модуля в отдельном `version.php` не нужна; при желании позже ключ в `module.config` (диск ≠ БД). Факт «шаг прошёл» — только реестр в БД.

### 4. Шаги данных

После **всей** схемы. Порядок data-шагов: модули **отсортированы по `Group/Name`**, не `scandir`; внутри модуля — порядок `dataSteps()`. Это не граф FK. Зависимость двух шагов разных модулей в v1 не моделируем (шаги с общей зависимостью — в одном модуле).

Модуль отдаёт шаги с стабильным id (`Core/User:seed.bypass-group`). Kernel: нет строки в реестре → `run()` → при успехе записать id. Повтор CLI не дублирует seed. Упал `run()` до записи реестра — шаг можно повторить: **идемпотентность на шаге** (или транзакция `run`+реестр, OPEN). Упал DDL в середине графа — повтор CLI: уже созданные столы идут в `updateTable`.

`setup` в config: class-string без аргументов **или** closure локатора (шаги, которым нужны порты). `tableClasses()` не зовёт БД.

DDL MySQL не в одной транзакции (неявный commit). Шаг данных — одна транзакция OPEN (можно без неё в v1, как unique login).

Содержимое seed «Администраторы» / «Игрок» — **не этот заход** (Auth / продуктовый seed). Инфраструктура списка шагов и реестра — да, с фикстурой в тесте Kernel. User в этом заходе: `tableClasses()` + пустой список data-шагов (или без шагов).

Гонка двух CLI — как unique login: без распределённой блокировки в v1; unique на `step_key` → второй проигрывает.

### 5. `UserSchema::install()`

Не удалять. Тесты User по-прежнему ставят тройку столов сами. `IModuleSetup::tableClasses()` User — **те же** три class-string, что `UserSchema` (не второй список). `install()` может гонять их в зашитом порядке для теста; CLI берёт граф.

### 6. Meta словаря

`st_meta_table` / `st_meta_field` — PHP-классы, `table_id` → meta table. SmartTable **обязан** отдать их в `tableClasses()` (иначе CLI не создаст словарь). `installMeta()` каталога — тот же apply двух карт; тесты словаря могут звать его дальше. CLI не зовёт `installMeta()` отдельно от графа. Runtime-строки словаря по-прежнему не git.

## Контракт (черновик)

```text
IModuleSetup
  tableClasses(): class-string<SmartTableDefinition>[]
  dataSteps(): ISetupStep[]     # можно [] 

ISetupStep
  id(): string
  run(): void
```

CLI: `www/mifrial/bin/setup.php`. Свой boot (все модули диска, не HTTP-`boot` + freeze). Не action HTTP.

Ошибки: листья Kernel (`SETUP_*` / существующие Schema/Map). Не маскировать `TABLE_MISSING` в «подождите модуль».

## Todo

- [x] **docs-terms** — канон в этом файле; план 9 «не в ST»; TR/roadmap/architecture. Хвост: «журнал наката» в старых планах 3/10 как история.
- [x] **contract** — `IModuleSetup` / `ISetupStep`; `setup` в `module.config`; не порт контейнера соседа; factory без БД для `tableClasses()`.
- [x] **graph** — диск всех групп; topo; дубль имени / цикл → ошибка; self-ref ок; `none` не ребро; apply create/update.
- [x] **boot** — CLI-boot без HTTP freeze; не `ApplicationFactory::boot()` как есть.
- [x] **registry** — карта Kernel для реестра шагов; в графе.
- [x] **cli** — вход без HTTP; тест: child в списке раньше parent, физика parent первой; phpunit + quality.
- [x] **user-st** — User: те же class-string, что `UserSchema`. ST: meta PHP-классы в `tableClasses`. Data-шаги User пустые. `install()` / `installMeta()` для тестов сохранить.

## Не входит

Журнал аудита/проблем. Laravel/artisan. Bitrix `install/version.php` + `update/`. `forceUpdateTable` / uninstall модуля / drop столов снятого модуля. Down. Циклические FK (двухпроходный DDL). Seed «Администраторы». Auth. Словарь `openByName` как источник git-схемы. Versioned.

## Следующий заход после кода

Auth 1 — [`auth-plan-01-session.md`](auth-plan-01-session.md) (сессия + seed групп). Журнал событий — когда будет UI ленты.
