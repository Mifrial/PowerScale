# План Game 9 — летопись

**Статус:** летопись в PHP сделана, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameChronicleMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G9. Строка игры — [`game-plan-01.md`](game-plan-01.md): летописи на ней нет. Границы — [`game-system.md`](game-system.md) (GameTime и летопись; persistence и права `OPEN`), индекс [`TR.md`](TR.md), [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход, который G1 не хранил. У игры появляется список записей со структурным `GameTime`. Порядок списка — смещение от эпохи в минутах, не `created_at` и не ручной `sort_order`. Создание записи не пишет лист, сессию и бой.

Зависимость шага — G1. G6, G7 и G8 этот заход не читает и не пишет.

## Сверка G1–G8

- Строка `game` есть. Колонки времени и летописи на ней нет. Историческое строковое поле времени из старой схемы не берётся.
- `IGames` — десять публичных методов, потолок. Летопись в этот порт не кладётся.
- `GameSchema` — шесть зависимостей конструктора, потолок. Новая карта в этот конструктор не входит.
- `GameHttp` — шесть зависимостей конструктора. Публичных методов девять: конструктор входит в счётчик phpcs, дальше восемь действий. Десятый метод влез бы, сценарии летописи в этот класс всё равно не ставятся.
- `GamePortFactory` — десять публичных методов, потолок после G8. Одиннадцатый `create*` сюда не встанет. Сборку летописи делает новый класс.
- `GamePermissionKeys` каталог не расширяет. Константа `EDIT` (`game.edit`) уже есть: `keysFor` отдаёт её владельцу и роли `gm`, не `player`. Это не глобальный ключ вроде `game.edit_all`. План не назначает `game.edit` молча: мутация ниже названа явно.
- Дружба, whitelist, секции и NPC этим заходом не толкуются.

## Зафиксировано (модель и права)

Канон persistence и прав — `OPEN`. Этот план выбирает оба.

**Хранение времени.** Одна целочисленная колонка `offset_minutes`, минимум 0. Минута — младшая единица. Колонки `sort_order` нет. Колонки `event_time` и текстового времени нет. Шести отдельных полей единиц нет: каноническая форма собирается из минут при ответе.

Единицы те же, что в эскизе `draft-front_1.2ds` `Utils/gameTime.ts`, от года до минуты:

| Единица | В следующей |
|---|---|
| год | 10 месяцев |
| месяц | 3 декады |
| декада | 10 дней |
| день | 30 часов |
| час | 60 минут |

Ход и секунда в комментарии утилиты (`GAME_TIME_TURNS_PER_MINUTE`, `GAME_TIME_SECONDS_PER_TURN`) в летопись не входят и в колонку не пишутся. Сортировка по секундам эскиза совпадает с сортировкой по минутам: множитель постоянный.

Запись нормализуется до insert и до update: 13 месяцев — это 1 год и 3 месяца в минутах. Ответ отдаёт неотрицательные поля `years`, `months`, `decades`, `days`, `hours`, `minutes`, каждое ниже порога следующей единицы (месяцы ≤ 9, декады ≤ 2, дни ≤ 9, часы ≤ 29, минуты ≤ 59). Дробь, отрицательное число и нечисло — `GAME_INVALID`. Сумма минут выше верхней границы знакового 32-битного целого — `GAME_INVALID`, строка не пишется.

**Эпоха.** Одна точка отсчёта `adventure_start`. Отдельной таблицы шапки летописи нет. `game.getChronicle` не вставляет строку: объект собирается из игры (`id` равен `gameId`, `name` — `null`, `epoch` — `adventure_start`).

**Права.** Чтение — тот же разбор карточки, что `game.get` (`GameCardAccess`): нет актора — `AUTH_REQUIRED`; карточка вне фильтра — `GAME_NOT_FOUND`.

Мутация (создать, изменить, удалить): владелец `game.owner_id`, строка `game_member` с ролью `gm`, или глобальный `game.edit_all`. Это тот же набор, что `game.edit` у владельца и `gm` плюс обход `edit_all`. `player` карточку может читать и запись не пишет: `AUTH_DENIED`. Новый ключ прав не заводится. `game.moderate` и `game.manage` летопись не открывают.

Игра `completed` и текущая сессия мутацию летописи не запрещают. Статус и сессия этим действием не меняются.

## Что даёт этот заход

Таблица `game_chronicle_entry`. Пять действий: прочитать шапку, прочитать список, создать, изменить, удалить. Список упорядочен по `offset_minutes`, при равенстве по `id`.

## Что не закрыто

- Порт мутации actual (G10), экономика, бой, проекции (G14), доставка (G15).
- Чаты и `returnMessageId`. `personalNotes`.
- Проверка, что `[[character:id]]` и `[[npc:id]]` существуют. Токен остаётся текстом `content`. `related` в ответе — разбор текста, без чтения листа и без строки NPC.
- Настройка единиц правилами. Пока единицы фиксированы этим планом.
- Вторая эпоха и имя летописи. `name` в ответе всегда `null`.
- Межигровая летопись и вынос в свой модуль. Запись этого шага принадлежит одной игре.
- Vue поверх этого PHP.

## Точки кода G1–G8

Старые `game-plan-01.md`–`game-plan-08.md` не переписываются. Подача, модерация, бонус, сессия, NPC, секции, фильтр карточки и вступление не меняются. `IGames`, `GameSchema`, `GameHttp` и `GamePortFactory` не растут.

Без этих точек запись некуда положить и тест её не поднимет:

- `GameModuleSetup::getTableClasses`. Сейчас сливает `GameSchema` и `GameAdmissionSchema`. Сюда дописываются карты нового установщика. Колонки `game` не появляются.
- `module.config.php`. Новые `ports` и `routes` зовут новый сборщик, не одиннадцатый метод `GamePortFactory`. Старые action не переименовываются.
- `GameMysqlFixture::installGameSchemas`. Рядом с уже открытыми картами открывается новый установщик. Иначе suite `game` таблицу не видит.

`GameCardAccess` вызывается как есть и не меняется. `Games::addMember`, сессия и лист не вызываются из летописи.

## Модуль

Тот же `Roleplay/Game`. Отдельного модуля летописи нет: в архитектуре и в `modules/` его нет, действия эскиза уже лежат в Game. Межигровая летопись этим заходом не проектируется и модуль под неё не заводится. Новый порт `IGameChronicles` в `Interface/Service/`. Сценарии в `Service/` Game. `events` остаётся `[]`. Suite `game`.

**DAG:** Game → SmartTable + User + RuleSpace + Character, как после G3. Нового ребра нет. Character не импортирует Game.

Ошибки прежние: `GAME_INVALID`, `GAME_NOT_FOUND`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. `GAME_CONFLICT` и `CHARACTER_*` этим действиям не принадлежат.

| Источник | Лист |
|---|---|
| нет игры; карточка вне фильтра; нет записи в этой игре | `GAME_NOT_FOUND` |
| время не целое, отрицательное, не влезает в signed INT, нет единицы или внутри `offset` лишний ключ; пустой title; title длиннее 255 | `GAME_INVALID` |
| тело не того типа; лишний ключ; нет обязательного поля | `INVALID_PARAMS` |
| нет актора | `AUTH_REQUIRED` |
| карточка видна, актор не владелец, не `gm` и без `game.edit_all` | `AUTH_DENIED` |

Чужая карточка не отличается от отсутствующей игры. `update` и `delete` ищут строку по `entryId`, игры в теле нет. Нет строки или карточка её `game_id` закрыта — `GAME_NOT_FOUND`.

## Таблицы

Новый установщик `GameChronicleSchema` в `Schema/`. Конструктор — один `IOpenedSchema`, как у `KeywordSchema`: `gateway->open(GameChronicleEntryTable::class)->schema()`. Это не сам шлюз. `GameSchema` остаётся на шести. `install()` приводит одну карту. DDL: `createTable` / `updateTable`.

Имя физическое: `game_chronicle_entry`. Таблиц `game_chronicle`, `chronicle` и колонки времени на `game` нет.

| Поле | Тип | Смысл |
|---|---|---|
| `id` | IdField PK | Суррогат. |
| `game_id` | reference `GameTable`, `restrict`, required, indexed | Игра. |
| `title` | `StringField`, required, maxLength 255 | Заголовок, не пустой после обрезки пробелов. 255 — аргумент по умолчанию конструктора `StringField`. Длиннее — `MapInvalidException`, наружу `GAME_INVALID`. |
| `content` | `TextField`, required | Тело. Пустая строка для `TextField` допустима. |
| `offset_minutes` | `IntField`, required, min 0, max 2147483647, не BIGINT | Смещение от эпохи. Верх — signed INT. Сортировка по нему. |
| `created_by` | `forTable` user, `restrict`, required | Кто создал. На update не меняется. |
| `created_at`, `updated_at` | DateTime Kernel | Как у `game`. Порядок списка их не использует. |

`defineUniqueKeys()` нет: две записи с одним смещением допустимы, порядок между ними — `id ASC`.

`related` колонкой не хранится.

## Фасад

`IGameChronicles`. Не методы `IGames`.

Класс `GameChronicles` (реализация порта) и HTTP-класс `GameChronicleHttp`. Разбор времени — `GameTimeOffset` в `Service/`: из шести неотрицательных целых в минуты и обратно. Публичных методов два. Единицы — константы этого класса, те же коэффициенты, что в таблице выше.

`GameChroniclePortFactory` собирает порт и HTTP двумя методами: `create` и `createHttp`. В `GamePortFactory` этих методов нет. Конструктор входит в счётчик публичных методов phpcs (`GameAdmissionHttp`). У `GameChronicleHttp` конструктор и пять действий — шесть, потолок десять. Отдельный `phpcs:ignore` не нужен.

Кто читает: актор, для которого `GameCardAccess::isVisible` открывает карточку. Ключ обхода — `game.view_all`, как у `GameHttp::get`. `game.edit_all` фильтр чтения не обходит. `game.getChronicle` строку летописи не создаёт. `game.getChronicleEntries` читает `getList` по `game_id`. Сортировка `ListQuery` — `['offset_minutes' => 'ASC', 'id' => 'ASC']`: `ListQueryCompiler::applyOrder` вешает ключи в порядке массива. Пустой sort компилятор заменяет на `id`, поэтому оба ключа передаются. `ListQuery::MAX_LIMIT`, offset 0, без total. Свыше 10000 строк страница обрезается, total нет — как у списков G8.

Кто пишет: владелец, `gm` или `game.edit_all`, и карточка ему открыта. Иначе при видимой карточке — `AUTH_DENIED`. Create пишет одну строку. Update меняет `title`, `content`, `offset_minutes`, `updated_at`. Delete удаляет строку. Ни одно из трёх не пишет `game_character`, `game_session`, бой, NPC и лист.

`related` в JSON: из `content` токены `[[character:N]]` и `[[npc:N]]`, N — положительное целое. Порядок первого вхождения, повтор того же kind и id один раз. Другой текст токеном не считается. Существование персонажа и NPC не проверяется.

Ответ собирает новый метод класса летописи, не `GameViewAssembler`.

## HTTP

Свой DTO на действие, `csrf` true. Класс `GameChronicleHttp`.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.getChronicle` | кто видит карточку | `{ gameId }` | `{ id, gameId, name: null, epoch: adventure_start }`. Строки в таблице нет |
| `game.getChronicleEntries` | кто видит карточку | `{ gameId }` | список записей по смещению |
| `game.createChronicleEntry` | владелец, `gm` или `game.edit_all` | `{ gameId, title, content, offset }` | запись. `offset` — объект шести единиц |
| `game.updateChronicleEntry` | те же | `{ entryId, title, content, offset }` | запись |
| `game.deleteChronicleEntry` | те же | `{ entryId }` | `null`, как `RemoveGameMemberAction` |

Поля записи: `id`, `gameId`, `title`, `content`, `offset` (шесть единиц), `related` (`kind`: `character` \| `npc`, `id`), `createdBy`, `createdAt`, `updatedAt`. `createdAt` и `updatedAt` — целые unix, `DateTime::toUnix()`, как `GameViewAssembler`. `chronicleId` в контракте нет: шапки-строки нет, запись принадлежит `gameId`.

`offset` в DTO — `array`. Верхний лишний ключ тела ловит `ActionNamedParameterBinder` как `INVALID_PARAMS`. Ключи внутри `offset` биндер не разбирает: нет единицы, нецелое и лишний ключ объекта времени — `GAME_INVALID` в `GameTimeOffset`.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- Ответ записи несёт `chronicleId` и шапка — отдельная ленивая строка при `game.getChronicle`. Сервер шапку не хранит и на чтении не вставляет строку. `id` шапки в ответе равен `gameId`.
- `related` эскиз считает производным на границе и сверяет с членством и NPC. Сервер отдаёт разбор токенов и id не проверяет. Создание записи лист, NPC и сессию не читает.
- Утилита времени хранит под минутой ход и секунду. Контракт летописи минуту не дробит.
- `createdAt` и `updatedAt` в эскизе — строки. Контракт — unix, как у карточки игры.
- Вкладка летописи рядом запрашивает персонажей и сводку NPC. Эти вызовы — не часть действий летописи и этим планом не проектируются.

## Тесты

Mysql времени. 13 месяцев сохраняются как минуты 1 года и 3 месяцев; ответ — `years: 1`, `months: 3`, остальные нули. 61 минута — 1 час и 1 минута. Ноль — все нули. Отрицательное и дробь — `GAME_INVALID`, строки нет. Две записи с одним смещением и разным `id` идут по возрастанию `id`. Запись с большим смещением, созданная раньше, стоит после меньшего смещения.

Mysql прав. Владелец, `gm` и `game.edit_all` создают, меняют и удаляют. `player` читает список и на create получает `AUTH_DENIED`, строки нет. Посторонний при `visibility` не `all` — `GAME_NOT_FOUND` и на чтение, и на запись. `game.getChronicle` до и после create оставляет число строк записей тем же, пока create сам не добавил одну.

Mysql границ. Create не пишет `game_character`, не меняет сессию и не создаёт бой. `section_visibility`, `game_npc` и whitelist не меняются. `related` для `[[character:1]]` и повторного того же токена — один элемент, без чтения листа.

Suite `game`. Suite `character` не расширяется.

## Todo

- [x] **schema** — `GameChronicleSchema` и карта `game_chronicle_entry`. `offset_minutes`, без `sort_order` и без текстового времени. `GameSchema` без новой зависимости.
- [x] **time** — `GameTimeOffset`: шесть единиц в минуты и обратно. Ход и секунда не хранятся.
- [x] **entries** — чтение по фильтру карточки; мутация владельцем, `gm` или `game.edit_all`. Список по смещению.
- [x] **gates** — phpunit `GameChronicleMysqlTest`. Create не пишет лист, сессию и бой.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| карта | Game `Schema/` `Table/` | запись и смещение в минутах |
| время | Game `Service/` | нормализация шести единиц |
| записи | Game `Service/` `Repository/` | список, create, update, delete |
| HTTP | Game `Service/` `Action/` | `GameChronicleHttp`, не метод `GameHttp` |
| сборка | Game `Service/` | `GameChroniclePortFactory`, не одиннадцатый метод `GamePortFactory` |
| карточка G8 | Game `Service/` | `GameCardAccess` без смены разбора |

## Acceptance G9

- Список записей упорядочен по смещению от эпохи. `created_at` и `sort_order` порядок не задают.
- Минута — младшая хранимая единица. 13 месяцев в ответе — 1 год и 3 месяца.
- `game.getChronicle` строку не создаёт.
- Create, update и delete не пишут лист, сессию и бой.
- Мутацию делает владелец, `gm` или `game.edit_all`. `player` получает `AUTH_DENIED`. Ключ `game.edit` каталогом прав не расширяется.
- Character не импортирует Game.
- Экономика, бой, проекции и доставка этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G9; [`game-plan-01.md`](game-plan-01.md); [`game-system.md`](game-system.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
