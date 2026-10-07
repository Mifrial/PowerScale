# План Game 16 — чаты донора

**Статус:** чаты донора в PHP сделаны, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameDonorChatMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G16. Канон — [`game-system.md`](game-system.md): Game Chat — общий чат стола; return оставляет membership и пишет причину; отмена process при return в этот шаг не входит. Хост — [`chat-roadmap.md`](chat-roadmap.md) шаг 7 и [`chat-plan-07.md`](chat-plan-07.md): `IChatTypeRegistry` и `IChats::addTyped` уже есть; inbox и `/api/chat/sync` только `private` и `group`. Границы — [`architecture.md`](architecture.md): Game → Chat как плагин; Character не импортирует Game; Chat не импортирует Roleplay. Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Game регистрирует строки `game`, `game_discussion` и `character_discussion` и создаёт чаты через `IChats`. На игре появляются чат стола и чат обсуждения; ответы, где уже есть `gameChatId` и `discussionChatId`, начинают отдавать эти id. Чат строки персонажа создаёт Game. Return пишет в него сообщение и `returnMessageId`. Состав членов меняют переходы участника и строки персонажа. Inbox мессенджера и `/api/chat/sync` эти чаты не показывают.

Зависимость — G1, G2, G3 и уже сделанный шаг 7 Chat. G6–G15 этот шаг не ждёт и их код не пересчитывает. Отмена process при return, вкладка Discussion на фронте, `sendSystemMessage`, thread, Files и SSE battleground не входят.

## Физическая схема

Схему id до этого плана не фиксировали. Этот план выбирает её явно.

**Указатели лежат в таблицах Game.** Колонки `game_id` в таблицах чата нет и не появляется. На строке `game` — два необязательных целых `game_chat_id` и `discussion_chat_id`. На строке `game_character` — необязательные целые `discussion_chat_id` и `return_message_id`. Это не `ReferenceField` на `chat` и не `chat_message`: порядок `drop` Game не зависит от таблиц Chat, схема Chat этим шагом не меняется.

**Имена типов — константы модуля Game.** `game`, `game_discussion`, `character_discussion`. В модуль Chat и в его тесты их не зашивать. Реестр по-прежнему не хранит каталог Roleplay: он помнит строку, которую ему передали.

**Регистрация — вызов порта в том же процессе, до `addTyped`.** `boot()` нет. `GameDonorChats` при сборке берёт единственный `IChatTypeRegistry` контейнера Chat и трижды вызывает `register`. Повтор той же строки идемпотентен. `private` и `group` не регистрируются.

**Создание — `IChats::addTyped`, не HTTP.** Каждый вызов — новая строка чата. Id пишет Game в свою колонку отдельным update после вставки: `GameRepository::add` возвращает id до того, как чаты существуют. Идемпотентности пары у `addTyped` нет и здесь не добавляется. `pair_key` не задаётся. `chat.addPrivate` и `chat.addGroup` Game не вызывает. Строки, созданные до этого шага, остаются с пустыми id: колонки необязательные, `fromNormalized` читает отсутствие как `null`.

**Сообщение return — `IChats::send`.** Автор — актор return. Сейчас `returnToOwner` этого id не принимает: аргументы — игра, персонаж, revision и причина. Шаг добавляет `int $actorUserId`. `GameCharacterHttp::returnToOwner` берёт его из `requireActor` и передаёт вниз. Текст — уже обрезанная причина, вложения пустые. Это не `sendSystemMessage`. Id сообщения пишется в `return_message_id` той же успешной попытки, что статус `returned` и причина. Если `discussion_chat_id` строки пуст, `send` не вызывается и `return_message_id` остаётся пустым; причина и статус пишутся как сейчас. Если чат есть, а актора в нём нет, перед `send` он сажается через `addSeat`. Так проходит носитель `game.edit_all`: `canModerate` пускает его в return, а в члены чата строки его не сажают ни submit, ни роль `gm`. Повторная посадка уже сидящего по-прежнему не ошибка.

## Сверка шага 7 и G1–G3

- `IChats::addTyped` вставляет `type` и `name` без `pair_key`. Тип должен быть уже в реестре: `ChatTypeRegistry::isRegistered` уже есть, новый метод реестра не заводится. Создатель всегда член.
- `Chats::send` тип не проверяет. Автор обязан быть членом. `IChats::send` возвращает id сообщения.
- `Chats::assertGroupChat` пускает только `type === 'group'`. `addMember` и `removeMember` донорский чат сейчас отвергают. Без точки ниже Game не меняет состав после `addTyped`. HTTP этих двух методов не вызывает.
- Inbox и `ChatSseService` фильтр `ChatHostTypes` не расширяют. `getChatIdsOfUser` не сужается.
- `GameViewAssembler` уже кладёт `gameChatId` и `discussionChatId` как `null`. Этот шаг подставляет колонки. Новых полей карточки нет.
- `returnToOwner` уже пишет причину и поднимает `membershipRevision`. Сообщение и `returnMessageId` добавляются сюда. Reject по-прежнему удаляет только `submitted`. Leave по-прежнему ставит `left`. Оба сообщение в чат стола не пишут.
- Character модуль Game не импортирует. Чат персонажа создаёт Game, не Character.

## Зафиксировано (модель и права)

**Чаты игры.** `Games::add` после вставки строки создаёт два чата. Стол — тип `game`, имя равно имени игры. Обсуждение — тип `game_discussion`, имя то же. Создатель и единственный начальный член — владелец игры. Id пишутся в строку игры. Падение `addTyped` откатывает вставку игры. `Games` шлюз сейчас не держит: транзакция — `ISmartTableGateway::transaction` на том же экземпляре, который `GamePortFactory::create` уже отдаёт в `GameRepository`. Чат пишет через свой репозиторий на этом же шлюзе, поэтому begin соединения накрывает обе вставки. `update` чаты заново не создаёт и id не затирает.

**Чат строки.** `submit` создаёт чат типа `character_discussion`. Имя — id персонажа строкой, без чтения листа в имя. Создатель — владелец персонажа. Члены на создании: владелец персонажа, владелец игры и учётки с ролью `gm`. Роли участника — `gm` и `player`, других `GameInputNormalizer` не принимает. Id пишется в `game_character.discussion_chat_id` в той же транзакции шлюза, что строка заявки. `GameCharacterMemberships` шлюз получает тем же способом, что `Games`. Approve, bonus и видимость секций чат не создают.

**Состав.** Его ведёт Game, не HTTP.

| Переход Game | Чаты |
|---|---|
| `addMember` | учётка добавляется в стол и в обсуждение игры |
| `addMember` с ролью `gm` | та же учётка добавляется в уже лежащие чаты строк этой игры |
| `updateMember` на `gm` | учётка добавляется в чаты строк |
| `updateMember` с `gm` на другую роль | учётка снимается с чатов строк, если это не владелец игры и не владелец этой строки |
| `deleteMember` | учётка снимается со стола и с обсуждения игры; с чатов строк — по тому же правилу, что смена роли с `gm` |
| `submit` | члены чата строки задаются списком `addTyped`, повторным `addMember` их не дублировать |
| `reject` | строка заявки удаляется; сообщение в стол не пишется; чат строки не переносится на стол |
| `leave` | статус `left`; сообщение в стол не пишется; `return_message_id` не заполняется этим переходом |
| `returnToOwner` | одно сообщение в чат этой строки, если id чата есть |

Владелец игры из стола и обсуждения не снимается: он не строка `game_member`. Последнего члена `removeMember` хоста снять нельзя; владельца поэтому не снимают.

Пустой id чата переход члена пропускает: и стол, и обсуждение игры, и чат строки. Такие пустые id остаются у строк, созданных до шага. `addSeat` и `removeSeat` на них не вызываются, и `game.addMember` на старой игре не падает.

**Return.** Успех с непустым `discussion_chat_id` сохраняет `return_message_id` возвращённого `send`. Автор — актор. Если его ещё нет среди членов, `addSeat` выполняется до `send` и в той же транзакции. Повторный return, который G3 уже допускает новым `membershipRevision`, пишет новое сообщение и заменяет id на него. Пустой чат строки: причина есть, `returnMessageId` в ответе `null`, вызова `send` нет.

**Чтение.** `game.get` и элемент `game.getList` отдают сохранённые id. Скрытая карточка по-прежнему `GAME_NOT_FOUND` и id не раскрывает. `game.getCharacter` и список строк персонажа отдают `returnMessageId` из колонки (или `null`). Отдельного action «прочитать чат» нет: страница сообщений остаётся `chat.findMessagePage` у члена. Этот шаг HTTP чата не расширяет.

**Inbox.** `chat.getChats` и кадр `/api/chat/sync` не содержат id стола, обсуждения и чата строки и не содержат сообщение return. Фильтр «нет game_id» не вводится.

**Права.** Нового кода нет. Создаёт игру тот, кто уже может `game.create`. Return пишет тот, кто уже может `game.returnCharacter`. Член чата — следствие перехода, не отдельное право.

**Чего шаг не делает.** Не отменяет process. Не открывает вкладку Discussion и не меняет Vue. Не вводит `sendSystemMessage`, thread, Files, macros и SSE battleground. Не кладёт типы в каталог Chat. Не сужает `getChatIdsOfUser`. Не закрывает `chat.sendMessage` по type. Не пишет сообщение reject или leave в чат стола.

## Что даёт этот заход

Сборщик `GameDonorChats`: регистрация трёх строк, создание трёх видов чатов, добавление и снятие члена. Колонки id на `game` и `game_character`. Ответы с уже обещанными `gameChatId`, `discussionChatId` и новым чтением `returnMessageId`.

## Что не закрыто

- Отмена process при return.
- Вкладка Discussion и прочий Vue.
- `sendSystemMessage`, thread, Files, macros.
- SSE battleground и хаб Kernel.
- Удаление осиротевшей строки чата при reject.
- `personalNotes`.

## Точки кода G1–G15 и Chat 1–7

Старые планы не переписываются. Остальной код этих шагов не меняется. Ниже только места, без которых Game не создаёт чат, не ведёт состав или не пишет `returnMessageId`.

- `Games::add`. После вставки строки — два `addTyped` и запись id. `update` не трогать. `IChats` в конструктор не кладётся: его держит `GameDonorChats`. Туда же — `ISmartTableGateway`, чтобы открыть `transaction`. Сейчас аргументов шесть. Шлюз и сборщик делают восемь. Порог стандартов — больше шести. `GameRecord` такое превышение уже глушит точечным `phpcs:disable` с комментарием, что это геттеры, не порты. На `Games` тот же приём: комментарий, что шлюз нужен для одной транзакции со вставкой чата, и класс ради порога не дробится.
- `Games::addMember`, `Games::updateMember`, `Games::deleteMember`. После успешной записи участника — правка членов стола, обсуждения и, для `gm`, чатов строк. Роль и существование учётки по-прежнему проверяет Game. Ветку HTTP `chat.addGroup` не вызывать.
- `GameCharacterMemberships::submit`. `addTyped` типа `character_discussion` и колонка `discussion_chat_id` в той же транзакции, что строка. Сборщик — пятый аргумент, шлюз — шестой. Порог «больше шести» не перейден.
- `GameCharacterMemberships::returnToOwner`. Новый аргумент `int $actorUserId`. После принятых проверок G3: если `discussion_chat_id` есть, `addSeat` актора, если его ещё нет, затем `send` и `return_message_id`; если нет — причина без сообщения. `reject` и `leave` этот вызов не получают. Вызовы в `GameCharacterMysqlTest` и `GameCharacterHttpMysqlTest` передают актора.
- `GameRepository`. Отдельная запись двух chat id после `add`. Чтение — через `GameRecord::fromNormalized`: пустое поле даёт `null`, чтобы старые фикстуры `GameCharacterDiffTest` не перечисляли новые ключи. `GameCharacterRepository::saveReturned` принимает необязательный id сообщения и пишет его вместе с причиной. `add` строки персонажа chat id не принимает: его пишет следующий update в той же транзакции. Сигнатуры approve, reject и leave не получают сообщение.
- `GameRecord` и `GameCharacterRecord`. Два id на игре, `discussionChatId` и `returnMessageId` на строке. `withReviewState` и `withAdmission` копируют новые поля: сейчас они собирают `new self` явным списком.
- `GameViewAssembler`. `gameChatId` и `discussionChatId` из записи. Пустая колонка остаётся `null`. `GameCharacterViewAssembler::detail` добавляет `returnMessageId`. Этот метод кормит и `game.getCharacter`, и список. `GameHttpMysqlTest` сейчас ждёт `null` на create; ожидание становится числом id.
- `GameTable`, `GameCharacterTable`, `GameModuleSetup`, `GameMysqlFixture`. Четыре целых поля. Карты Chat не меняются.
- `GamePortFactory::create` и `GamePortFactory::createMemberships`. Собирают один и тот же вид `GameDonorChats` из `IChatContainer`: реестр и `IChats`. Второй реестр не конструировать. Шлюз — тот же `smartTableGateway`, что уже у репозиториев этих фабрик.
- `Chats::assertGroupChat`. Пускает `group` и строку, для которой уже существующий `IChatTypeRegistry::isRegistered` истинен. `private` и незарегистрированная строка по-прежнему `ChatInvalidException`. Иначе `addMember` / `removeMember` не меняют состав донорского чата. HTTP create этих методов не вызывает. `addTyped`, `send`, `ChatHostTypes`, inbox и SSE не меняются. Сам реестр не меняется.

`GameCharacterHttp::returnToOwner` передаёт id актора в `returnToOwner` и отдаёт тело сборщика. Отдельное поле ответа сверх `returnMessageId` не появляется.

Список чатов строк для посадки `gm` читает `GameCharacterRepository::getListByGame`. Этот репозиторий есть у membership, у `Games` его нет: его получает `GameDonorChats`.

## Модуль

Логика в `Roleplay/Game`, класс `GameDonorChats` в `Service/`. Отдельного порта `IGameChats` нет: снаружи по-прежнему `IGames` и `IGameMemberships`. Публичных `create*` у `GamePortFactory` не прибавляется.

**DAG:** Game → `IChatTypeRegistry`, `IChats`. Character Game не импортирует. Chat Roleplay не импортирует. Новый порт Character не появляется.

Ошибки Chat на пути создания и return переводятся в ошибки Game до ответа HTTP. Новый код не заводится.

| Источник | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта, нет строки | `GAME_NOT_FOUND` |
| пустая причина return | `GAME_INVALID` |
| `addTyped` / `send` / член: нет учётки или чата | `GAME_NOT_FOUND` |
| тип не зарегистрирован к моменту `addTyped` | `GAME_INVALID` |
| конфликт revision return | `GAME_CONFLICT` |

`CHAT_*` клиенту Game не отдаётся.

## Фасад

Нового фасада нет.

`GameDonorChats` снаружи модульных HTTP-действий не торчит.

- `registerTypes(): void` — три `register`.
- `openGameChats(int $ownerUserId, string $name): array` — два id, `gameChatId` и `discussionChatId`.
- `openCharacterChat(int $ownerUserId, array $memberIds): int`.
- `addSeat(int $chatId, int $userId): void` / `removeSeat(int $chatId, int $userId): void` — `IChats::addMember` / `removeMember`. Повторное добавление уже сидящего — не ошибка перехода Game: `ChatDuplicateException` глотается здесь.
- `postReturn(int $chatId, int $authorUserId, string $reason): int` — `send`.

`IGames` методы не прибавляет. `IGameMemberships::returnToOwner` получает аргумент `actorUserId` и нового метода не заводит.

## HTTP

Нового action нет. `chat.addPrivate` и `chat.addGroup` поле type не получают.

Меняется тело уже существующих ответов:

- `game.create`, `game.get`, элемент `game.getList` — `gameChatId` и `discussionChatId` числа, не `null`, когда строка создана этим шагом;
- `game.returnCharacter` и чтение строки персонажа — `returnMessageId` число или `null`.

`game.rejectCharacter` и `game.leaveCharacter` по-прежнему без сообщения стола. Вход с лишним `returnMessageId` на submit остаётся `INVALID_PARAMS`, как в G3.

## Тесты

Suite `game`. Suite `chat` каталогом `game` / `game_discussion` / `character_discussion` не расширяется. Suite `character` не расширяется.

Mysql создания. `game.create` пишет два разных id. Строка `chat` имеет эти type и не имеет `game_id`. Владелец — член обоих. `game.get` и список отдают те же id.

Mysql строки. `submit` пишет `discussion_chat_id`. В чате сидят владелец персонажа, владелец игры и `gm`. `game.addMember` с `gm` добавляет учётку в этот чат. Снятие `gm` снимает её и не снимает владельца строки. Уход участника снимает его со стола и с обсуждения игры.

Mysql return. Успешный return пишет сообщение в чат строки и `returnMessageId` этого сообщения. Носитель `game.edit_all`, которого в чате строки не было, после return оказывается членом, и сообщение записано от его id. Повторный return с новой revision заменяет id и не стирает первое сообщение. Строка с пустым `discussion_chat_id` сохраняет причину, оставляет `returnMessageId` пустым и строку `chat_message` не добавляет. `reject` и `leave` число сообщений стола не меняют.

Mysql inbox. Актор — член этих чатов. `chat.getChats` их id не содержит. Кадр `/api/chat/sync` не кладёт их в `chats` / `newChats` и не кладёт сообщение return в `messages`.

## Todo

- [x] **columns** — четыре целых на `game` и `game_character`. Без `game_id` в Chat.
- [x] **register** — три строки донора через `IChatTypeRegistry` из кода Game.
- [x] **open** — стол и обсуждение в `Games::add`; чат строки в `submit`.
- [x] **seats** — состав через `addMember` / `removeMember` на переходах участника и `gm`. Предикат `assertGroupChat` пускает зарегистрированный тип.
- [x] **return** — `send` и `returnMessageId`, если чат строки есть. Reject и leave молчат.
- [x] **gates** — phpunit `game`. Inbox и `/api/chat/sync` эти id не показывают. Character не импортирует Game. Chat не импортирует Roleplay.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| колонки | Game `Table/` | id чатов и `return_message_id` |
| сборщик | Game `Service/GameDonorChats` | register, addTyped, члены, send |
| точки Game | `Games`, `GameCharacterMemberships` | создать и вести состав; return пишет id |
| чтение | `GameViewAssembler`, `GameCharacterViewAssembler` | отдать уже обещанные id |
| точка Chat | `Chats::assertGroupChat` | член донорского типа через уже существующий `isRegistered` |
| установка | `GameModuleSetup`, `GameMysqlFixture` | новые колонки Game |

## Acceptance G16

- Тип регистрирует Game. В Chat нет каталога `game` / `game_discussion` / `character_discussion`.
- Chat Roleplay не импортирует. Колонки `game_id` в таблицах чата нет.
- Ответы с `gameChatId` и `discussionChatId` отдают чаты, созданные через `IChats`.
- Return с чатом строки пишет сообщение и `returnMessageId`. Return без чата строки `returnMessageId` не пишет.
- Reject и leave чат стола этим сообщением не подменяют.
- Состав меняют переходы Game, не HTTP `chat.addGroup` и не `chat.addPrivate`.
- Inbox и `/api/chat/sync` эти чаты не показывают.
- Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G16; [`chat-roadmap.md`](chat-roadmap.md); [`chat-plan-07.md`](chat-plan-07.md); [`game-system.md`](game-system.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
