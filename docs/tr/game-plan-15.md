# План Game 15 — доставка

**Статус:** доставка в PHP сделана, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameDeliveryMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G15. Канон — [`game-system.md`](game-system.md): ответ команды и доставка — разные границы; SSE разносит уже принятый итог и не является источником истины; повтор доставки не повторяет мутацию. Проекции — [`game-plan-14.md`](game-plan-14.md): roster и лист по ключам уже есть; этот шаг их не заменяет потоком. Удар — [`game-plan-13.md`](game-plan-13.md): эффект применяется один раз по ключу команды. Экономика — [`game-plan-11.md`](game-plan-11.md): тот же ключ возвращает сохранённый итог. Индекс — [`TR.md`](TR.md). Границы — [`architecture.md`](architecture.md): `IEventManager` синхронный и process-local; `afterCommit`, async queue и outbox Core — `DEFERRED`. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Появляется доставка уже зафиксированного итога G11 и G13: строка outbox, кадр SSE с ключами и listener EventManager, который эту строку дописывает. Повтор кадра лист, `npc.version`, экономику и удар не пишет. Ошибка доставки уже применённую команду не откатывает. Roster и лист по-прежнему читаются действиями G14.

Зависимость шага — G14. Команда уже применена G11 и G13; этот шаг её не пересчитывает. `1 → N`, `N → 1`, DOT, каст, движение и battleground не входят. Чаты и `returnMessageId` не входят.

## Физическая схема

Схему до этого плана не фиксировали. Этот план выбирает её явно.

**Outbox — таблица Game, отдельный commit после команды.** Строка `game_delivery` хранит курсор игры, вид команды, id уже лежащей строки журнала и ключи `{ type, id, actualVersion }`. Листа, `choices` и `sheet` в строке нет. Запись outbox не входит в транзакцию G11 и G13: к моменту вставки команда уже закоммичена. Падение вставки команду не откатывает.

**Догон читает журналы, не мутаторы.** Если процесс умер между commit команды и строкой outbox, следующий sync вставляет недостающие строки из уже сохранённого итога `game_economy_operation` и `game_strike_command`. Вызовов `GameEconomyApply`, `ICharacterActualMutations::apply`, `replaceVersion`, `declareStrike` и `resolveStrike` на этом пути нет.

**SSE — отдельный GET, не action.** `/api/game/sync`, cookie, без CSRF, не `game.sync` в `routes`. Кадр — курсор и ключи. Полный лист клиент берёт `game.getSheets` / `game.getRoster` / `game.getBattleSheets`. Поток не подменяет эти чтения.

**Listener EventManager — сигнал в том же процессе, не транспорт между воркерами.** После успешного нового прохода Game вызывает `IEventManager::fire` с именем `Roleplay\Game.Delivery::Recorded`. Это имя проходит `EventManager::assertEventName`. Подписчик реализует `IEventListener`. Payload — `gameId`, `source`, `sourceId`, `keys`. Слушатель вставляет строку outbox, если пары `source` + `sourceId` ещё нет. Пойманный сбой `fire` не меняет HTTP команды: клиент получает уже собранный итог. Ключ `events` в `module.config.php` слушателя не регистрирует: `ModuleManager` проверяет только то, что конфиг — массив, а `GamePortBootTest` ждёт `[]`. Регистрация — явный `IEventManager::on` в том же запросе, до `fire`. Готового класса payload в `Core/Event` нет: Game реализует `IEventPayload` (`has` / `get`). `IEventManager` не знает SmartTable и SSE и не переживает границу FPM-воркера: кадр другому соединению везёт только таблица. `afterCommit` Core этим шагом не включается. `fire` не глотает исключение listener: `dispatchEntry` пробрасывает `MifrialException` и оборачивает прочий `Throwable` в `EventException`. Поэтому `try/catch` стоит вокруг самого `fire`, уже после commit команды. Пойманная ошибка наружу как откат команды не уходит.

Повтор доставки — повторное чтение уже лежащих строк outbox тем же курсором. Второй `fire` на replay команды не ставится: ветки `replay` G11 и G13 доставку не вызывают.

## Сверка G11, G13, G14

- `GameEconomy::apply` при уже лежащем ключе возвращает `replay()` до транзакции и лист второй раз не пишет.
- `GameStrikes::declareStrike` и `resolveStrike` при `ready['replay'] !== null` возвращают сохранённый итог до `open` / `close`.
- Журналы хранят тело и итог. Доставка читает их и ключи с версиями из итога, не пересобирает эффект.
- `IGameProjections` отдаёт roster без листа и лист по названным ключам. Боевой subset читает состав `battleId`. Эти три action тело не расширяют и в кадр не вкладываются.
- `module.config.php` Game держит `'events' => []`. Этот массив подписку не включает. `GamePortBootTest` это пустое значение проверяет и этим шагом не меняется.
- Character модуль Game не импортирует. `CharacterChanged` в outbox игры не превращается: чужое сохранение листа между сессиями этот шаг не разносит.

## Зафиксировано (модель и права)

**Граница.** Команда отвечает своим HTTP-конвертом, как сейчас. Доставка этого ответа не заменяет и урон заново не считает. Кадр говорит, какой ключ и какая `actualVersion` уже лежат. Авторитет листа остаётся у порта G10 и у `npc.version`.

**Курсор.** Это `IdField` строки `game_delivery`, не отдельный счётчик с 1 на игру. У двух игр номера идут одним рядом, внутри одной игры номер только растёт. `eventId` — строка `<gameId>.<cursor>`. Строки не удаляются, поэтому дырки нет и кадра `reset` нет. Query `lastCursor` — целое ≥ 0 или отсутствие ключа. Нет ключа — один кадр с текущим максимумом этой игры и пустым `events`, затем цикл ждёт новые строки. Есть ключ — события этой игры с курсором строго больше него. Ключ больше максимума — пустой `events` и этот максимум. Отрицательное и нечисло — `INVALID_PARAMS` до потока.

**Кадр.** `{ cursor, events }`. Элемент — `{ eventId, cursor, source, sourceId, keys }`. `source` — `economy` или `strike`. `keys` — список `{ type, id, actualVersion }`. `type` — `character` или `npc`. Позиции магазина в этот список не входят: итог экономики хранит их отдельно как `positions[].ruleCode`. Повтор того же курсора отдаёт те же события и строки листа не трогает.

**Откуда ключи.** У экономики сохранённый `result` — это `versions`: `characters[]` с `characterId` и `actualVersion`, `npcs[]` с `npcId` и `actualVersion`. Догон берёт ключи оттуда. У удара сохранённый итог — `battleId`, `strikeId`, `version`, `sheetVersion`. Типа и id листа в нём нет. Пока `sheetVersion` равен `null`, ключей листа у этой строки нет, и догон их не выдумывает из `game_strike`. Атака лист не меняет, и у неё ключей листа нет.

**Видимость кадра.** Карточка скрыта — до потока JSON `GAME_NOT_FOUND`, как у `game.getRoster`. В кадре нет секций. Чужая секция не доезжает событием: клиент режет её ответом G14. Ключ NPC вне scope в кадр этого актора не кладётся. `GameProjections::scopeAllows` и `GameNpcHttp::scopeAllows` приватные, фасад их не вызывает и эти классы не меняет. Предикат читается заново в доставке: владелец игры и `gm` видят ключ; `scope === all` пускает участника с ролью (`IGames::getMember`); `scope === users` пускает id из `userIds`. Строка NPC берётся `GameNpcRepository::getListByGame`. Роль `null` и scope `gm` ключ скрывают.

**Права.** Чтение потока — карточка видна. Новый код прав не заводится. Запись команды остаётся у G11 и G13.

**Чего шаг не делает.** Не пишет лист, `npc.version`, экономику и удар на пути доставки. Не меняет idempotency этих команд. Не открывает `1 → N`, сцену, DOT, каст и движение. Не кладёт roster и листы в `game.get` и в кадр. Не копирует чатовый кадр и не возит сообщения. Vue не трогает.

## Что даёт этот заход

Фасад `IGameDelivery`: запись строки по уже применённой команде, догон журналов, чтение хвоста для актора. Listener на `Roleplay\Game.Delivery::Recorded`. Entrypoint `/api/game/sync`.

## Что не закрыто

- `1 → N`, `N → 1`, DOT, каст, движение, battleground.
- Вырезание секций в `game.getCharacter`.
- Доставка правок листа владельцем вне команд G11 и G13.
- Чат игры.
- Vue поверх этого PHP.
- `afterCommit` и очередь Core.

## Точки кода G1–G14

Старые планы не переписываются. Character не импортирует Game. Мутаторы, маски, проекции и разбор удара не меняются. Доставка подписывается только на уже выполненную команду, после commit, и только если это не replay.

- `GameEconomy::apply`. После возврата из `transaction`, когда ключа ещё не было, один вызов `fire`. Ключи — из уже возвращённых `versions.characters` и `versions.npcs`, без `positions`. Ветка `replay()` этот вызов не получает: снаружи replay неотличим, оба пути возвращают `operationId`, `idempotencyKey`, `versions`. `GameEconomyApply` не меняется. `IEventManager` — восьмой аргумент конструктора. Сейчас их семь, у `GameStrikes` шесть, а `ClassQualitySniff` считает ошибкой больше шести. Тот же sniff уже видит оба класса длиннее 500 строк (`GameEconomy.php` 544, `GameStrikes.php` 610). Класс ради `fire` не дробится: вызов стоит на ветке, которую фабрика снаружи не видит. Эти ошибки sniff шаг принимает.
- `GameStrikes::declareStrike` и `GameStrikes::resolveStrike`. Вызов `fire` стоит после `open` / `close`, не в ветке `ready['replay'] !== null`. `open` и `close` свои транзакции не расширяют записью outbox. Расчёт удара и порт G10 не вызываются заново. В payload удара ключей листа нет, пока итог не содержит `sheetVersion` отличный от `null`. `IEventManager` — седьмой аргумент конструктора, тот же sniff. `GameStrikeCommandRepository::add` id не возвращает, а `findByKey` отдаёт только `body` и `result`. После commit `sourceId` берётся из id строки, которую `findByKey` начинает возвращать вместе с этой парой. Сверка тела по-прежнему читает `body`.
- `GameEconomyOperationRepository` и `GameStrikeCommandRepository`. Снаружи у экономики есть `findByKey` и `add` (он возвращает запись с `getId()`). У удара списка строк игры нет. Догон добавляет чтение строк одной игры. `add` не меняется. `source_id` в outbox — обычное целое, не `ReferenceField` на журнал: `deleteBySession` снимает команды удара и не должен упираться в строку доставки.
- `GameModuleSetup::getTableClasses` и `GameMysqlFixture`. Новая таблица ставится тем же слиянием, что `GameStrikeSchema`. `dropGameTables` снимает её до журналов команд.

Остальной код G1–G14 не меняется. `www/.htaccess` — новый контур: рядом с `api/chat/sync` правило на `api/game/sync`, `SetEnvIf` `no-gzip` и допуск `game-sync.php` в запрете `mifrial/`. Сейчас allowlist — только `action.php` и `chat-sync.php`.

## Модуль

Доставка в `Roleplay/Game`, `Interface/Service/IGameDelivery`. Сборщик — `GameDeliveryPortFactory` с `create` и `createHttp`. Публичных `create*` у `GamePortFactory` не прибавляется. `events` модуля остаётся `[]`. `GameEconomyPortFactory::create` и `GameStrikePortFactory::create` берут `IEventManager` из локатора, вызывают `on` и передают тот же порт в конструктор фасада. `IEventManager` список подписчиков наружу не отдаёт, поэтому второй `create` в том же процессе вешает второго слушателя. Повторный `record` по паре `source` + `sourceId` строку не пишет. Догон пишет строку через `record` и `fire` не вызывает: в процессе SSE слушатель не нужен. `GameContainer` список портов не хранит.

**DAG:** Game → `IEventManager`. Character Game не импортирует. Новый порт Character не появляется.

Конструктор фасада, не больше шести: шлюз, `IGames`, `GameCardAccess`, репозиторий доставки, репозиторий операций, репозиторий команд удара. `GameNpcRepository` для фильтра ключей фасад создаёт из шлюза, как экономика создаёт свои репозитории. Фасад в локатор не ходит. Запись outbox — своя короткая транзакция. Транзакция команды снаружи не открывается.

Ошибки потока до SSE: `GAME_NOT_FOUND`, `AUTH_REQUIRED`, `INVALID_PARAMS`. `GAME_CONFLICT` доставке не принадлежит: версии листа она не сверяет. Ошибка вставки outbox в listener наружу команды не превращается.

| Источник | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта | `GAME_NOT_FOUND` |
| кривой `lastCursor`; лишний ключ query | `INVALID_PARAMS` |

## Фасад

`IGameDelivery`. Не метод `IGameStrikes`, не метод экономики и не метод `IGameProjections`.

- `record(int $gameId, string $source, int $sourceId, array $keys): void` — вставка, если пары `source` + `sourceId` ещё нет. Повтор ничего не пишет в лист.
- `catchUp(int $gameId): void` — недостающие строки из журналов.
- `tail(int $gameId, int $actorUserId, bool $viewAll, ?int $lastCursor): array` — кадр для актора.

`keys` — `{ type, id, actualVersion }`.

## HTTP

Своего action «отправить событие» нет: клиент команду шлёт прежними `game.applyEconomy`, `game.declareStrike`, `game.resolveStrike`. Поток — GET `/api/game/sync?gameId&lastCursor`. Тело не читается. CSRF не требуется. Кадр не `ActionResponse`. Сборка как у `API/chat-sync.php`: `prepareHttp`, затем `SseEmitter`, ошибка до старта байтов — `emitHttpError`. Соединение держится, пока клиент его не закрыл: цикл в том же виде, что `ChatSseService::run`, без его пауз. После каждого тика пауза 1 с, и когда кадр ушёл, и когда хвоста нет. Лестницы backoff нет. Если JSON не уходил 15 с, пишется SSE-комментарий и курсор не двигается. Часы паузы и обрыва — интерфейс Game. `ISseClock` чата Game не импортирует: он в `Messages/Chat`. В тесте пауза ничего не ждёт.

Каждый тик сначала вызывает `catchUp`, затем `tail`. Успех потока — `text/event-stream`, событие `sync` с телом кадра. Нет актора и скрытая карточка — JSON до потока.

Дефекты эскиза `draft-front_1.2ds`, в схему не копировать:

- `MockGameRealtimePort.sync` на устаревшем курсоре возвращает `GameStateSnapshot` и projections с `projectionLevel`. Это чтение, смешанное с доставкой. В PHP строки outbox не стираются, отдельного кадра-снимка нет: хвост — только ключи новее `lastCursor`.
- `MockGameCombatCommandService` публикует realtime в той же попытке, где пишет лист, и при исключении откатывает лист вместе с несостоявшейся публикацией. В PHP ошибка доставки уже записанный лист не откатывает. Повтор команды в моке событие не шлёт повторно — эту часть повторного применения мок не ломает. Ломает смешение границ: доставка живёт внутри мутации, а не после неё.
- Кольцо на 100 событий в памяти — не outbox. Потеря хвоста в моке не восстанавливается из журнала команды.
- Подписка мока на `characterChangePort` разносит любое изменение персонажа по играм. Этот шаг так не делает.

## Тесты

Suite `game`. Suite `character` не расширяется.

Mysql команды. Успешные `game.declareStrike`, `game.resolveStrike` и `game.applyEconomy` оставляют по одной строке outbox. У атаки и у текущей защиты `keys` пусты. Повтор того же ключа вторую строку не создаёт и `actual_version` не увеличивает. Исключение внутри listener после commit оставляет лист, строку журнала и успешный ответ команды; повторный догон вставляет outbox один раз и лист снова не пишет.

Mysql потока. Хвост строго новее `lastCursor`. Повтор того же `lastCursor` отдаёт те же `eventId` и число строк листа не меняет. `lastCursor` больше максимума — пустой `events` без `choices` и `sheet`. Скрытая карточка — `GAME_NOT_FOUND` до потока. NPC вне scope в кадре этого актора нет.

Mysql проекций. После кадра `game.getSheets` по ключу из события возвращает ту же `actualVersion`, что в кадре. `game.get` по-прежнему без roster и листов.

## Todo

- [x] **outbox** — таблица и вставка отдельным commit. Уникальность `source` + `sourceId`.
- [x] **hooks** — `fire` только после нового прохода `GameEconomy::apply` и `GameStrikes::declareStrike` / `resolveStrike`. Replay молчит.
- [x] **listener** — подписчик пишет outbox. Ошибка наружу команды не откатывает.
- [x] **sse** — GET `/api/game/sync`. Кадр ключей. Догон на тике. Пауза 1 с, комментарий на 15 с.
- [x] **gates** — phpunit. Повтор доставки лист не пишет. Сбой доставки команду не снимает.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| outbox | Game `Repository/` | строка доставки |
| фасад | Game `Interface/Service/` `Service/` | record, catchUp, tail |
| listener | Game `Service/` | `IEventListener`, `on` из фабрик команд |
| поток | `API/game-sync.php`, `www/.htaccess` | GET, не action |
| точки подписки | `GameEconomy::apply`, `GameStrikes` | fire после commit, не на replay |
| чтение журналов | `GameEconomyOperationRepository`, `GameStrikeCommandRepository` | список строк игры для догона |
| установка | `GameModuleSetup`, `GameMysqlFixture` | таблица outbox |

## Acceptance G15

- Повтор доставки не пишет лист, `npc.version`, экономику и удар второй раз.
- Ошибка доставки не откатывает уже применённую команду.
- Кадр несёт ключи и версии. Полный лист остаётся у G14.
- SSE, outbox и listener выбраны этой схемой, не моком Vue.
- Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G15; [`game-system.md`](game-system.md); [`game-plan-14.md`](game-plan-14.md); [`game-plan-13.md`](game-plan-13.md); [`game-plan-11.md`](game-plan-11.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
