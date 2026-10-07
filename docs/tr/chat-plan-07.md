# План Chat 7 — непрозрачный тип донора

**Статус:** сделано, 2026-10-04. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). Inbox/SSE host — [`chat-plan-06.md`](chat-plan-06.md). Хост плагинов — [`chat-system.md`](chat-system.md). Рёбра — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: хост принимает регистрацию непрозрачной строки типа и создание чата этого типа через `IChats`. Не через `chat.addPrivate` и `chat.addGroup`. Inbox `/messenger` и кадр `/api/chat/sync` по-прежнему только `private` и `group`.

Чат игры этим заходом не создаётся. `returnMessageId` не пишется. Вкладка Discussion не открывается. Это следующий шаг донора, когда хост уже принимает тип.

## Термины

| Термин | Смысл |
|---|---|
| Host-тип | Литералы `private` и `group`. Allowlist `ChatHostTypes`. Единственные строки inbox и SSE. |
| Донорский тип | Строка, которую зарегистрировал сосед. Хост не знает, чья она и какой таблице принадлежит. |
| Реестр | Память процесса: набор зарегистрированных строк. Не колонка, не enum SmartTable, не каталог имён в модуле Chat. |

Имена `game`, `game_discussion`, `character_discussion` в этом файле — примеры строк донора. В код Chat и в тесты хоста их не зашивать. Проба в тесте — своя фикстурная строка, не имя модуля Roleplay.

## Решения

### 1. Реестр на хосте

Отдельный порт модуля Chat, не метод `IChats` и не знание о Game. В PHP нет `boot()` модуля: `module.config.php` Chat и соседей держит `'events' => []`, контейнер поднимается лениво. Регистрация — вызов порта, не хук ядра.

- Порт в `ports` карты `Messages/Chat/module.config.php`. `ModuleContainer::get` запоминает один экземпляр на контейнер.
- `ChatPortFactory::create` отдаёт этот же экземпляр в `Chats`. Второй `new` реестра внутри фабрики фасада не делать: `register` и `addTyped` иначе смотрят в разные объекты.
- `register(string $type): void`. Строка после trim непуста. Повтор той же строки — идемпотентно. Пустая — `ChatInvalidException`.
- `private` и `group` через `register` не принимаются (`ChatInvalidException`): это host, не плагин.
- Таблицы реестра нет. Строка живёт до конца процесса. Этот шаг сам донора не вызывает: тест берёт порт из `IChatContainer` и регистрирует фикстуру перед `addTyped`. Позже донор делает тот же `get` из своего кода.
- Реестр не хранит icon, roles, renderer, command. Это контракты Vue и поздние шаги (`sendSystemMessage`, thread, Files, macros).

`ChatHostTypes::isHost` не заменяется реестром. Inbox не становится «все зарегистрированные».

### 2. Создание через фасад

`IChats` сейчас 10 public (`addPrivate` … `markRead`). Шаг 6 11-й метод не заводил. Здесь он нужен: сосед не должен звать HTTP create.

Один метод, имя `addTyped`. Вход — DTO по образцу `NewGroupChat` (имя, создатель, дополнительные члены), плюс строка типа. Нормализация имени и списка — рядом с `ChatInputNormalizer::newGroupChat`, не вторая развилка правил.

- тип не в реестре, пустое имя, нет учётки — те же смыслы, что group: `ChatInvalidException` / `ChatNotFoundException`;
- создатель всегда член; повторный id в списке схлопывается, как `NewGroupChat`;
- каждый вызов — новая строка. Идемпотентности private-пары нет: донор сам хранит id;
- вставка как `ChatRepository::addGroup`: поля `type` и `name`. `pair_key` не писать: колонка unique, групповые строки его уже не задают и их несколько;
- колонки `game_id` нет и не появляется.

Не generic `open`. `addPrivate` / `addGroup` не принимают чужой type.

`Chats` уже помечен `phpcs:disable … TooManyPublicMethods`. Комментарий — 11 методов интерфейса плюс конструктор. Тот же sniff режет и сам `IChats` (интерфейс для него тоже «класс»): на интерфейсе такое же точечное исключение, потому что метод обязан жить на фасаде. Класс ради порога не дробить. Конструктор `Chats` — шесть зависимостей; порог стандартов — больше шести.

### 3. Точки шагов 1–6, без которых донор не создаёт свой тип

Остальной код шагов 1–6 не трогать.

| Точка | Зачем |
|---|---|
| `ChatRepository::addGroup` пишет `'type' => 'group'` | Рядом вставка с переданной строкой `type` и `name`, без `pair_key` и без новой колонки. `ChatTable.type` уже `StringField` required, не enum. |
| `IChats`, `Chats`, `ChatPortFactory::create` | Новый `addTyped`. Фабрика передаёт в `Chats` реестр из порта контейнера. |
| `Chats::assertGroupChat` | Сейчас отсекает только `type === 'private'`, значит чужой тип проходит в `addMember` / `removeMember` как группа. Предикат — ровно `group`. Иначе созданный донорский чат меняет состав чужим API. |

`ChatHostTypes`, `assembleInbox`, набор id в `ChatSseService` не расширять. `getChatIdsOfUser` не сужать. `assembleChatsByIds` по-прежнему не фильтрует type.

### 4. HTTP

Нового action нет. `chat.addPrivate` / `chat.addGroup` не принимают поле type. Тело с лишним ключом — как сейчас, `INVALID_PARAMS`.

Донор ходит в процессный `IChats`, не в `action.php`.

### 5. Inbox и SSE

Чужой тип не всплывает. Проверка захода — mysql: зарегистрировать фикстурную строку, `addTyped`, актор — член.

- `chat.getChats` не содержит этот id;
- поток `/api/chat/sync` не кладёт его в `chats` / `newChats` и не кладёт его сообщения в `messages`.

Фильтр «нет game_id» не вводить.

### 6. Ошибки

| Случай | Исключение фасада |
|---|---|
| Пустой type, type = `private`/`group`, type не в реестре, пустое имя | `ChatInvalidException` |
| Нет создателя или id из `memberIds` | `ChatNotFoundException` |
| Повтор `register` той же строки | не ошибка |

Новых кодов HTTP нет: create донора не action.

### 7. Фронт — только сверка эскиза

Вкладки Discussion (`GameDetailPage`) и Game Chat (`GameChatTab`) монтируют `ChatThread` из `Messages/Chat/init`. Второй ленты мимо хоста нет; в фасад её копировать нечего. `useCombatChatThread` держит штампы свёртки раунда, не список сообщений. `registerChatType` во Vue уже есть у Game и Character — этот заход PHP-реестр с ним не связывает и Vue не меняет.

Мок `mockCreateGameDiscussion` не переносить на `addTyped`.

## Todo

- [x] **registry** — порт в `module.config.php`; один экземпляр в контейнере; `register`; отказ host-литералов и пустой строки.
- [x] **facade** — `IChats::addTyped`; вставка `type`+`name` без `pair_key`; `assertGroupChat` только `group`; комментарий существующего disable на `Chats`.
- [x] **gate** — mysql: фикстурный тип через `register` + `addTyped` не в `chat.getChats` и не в кадре sync (сообщение этого чата тоже); phpunit `chat`; cs/quality Chat. Модуль Chat не импортирует Roleplay.
- [x] **canon** — этот файл. `chat-plan-01` … `chat-plan-06` не переписывать.

## Не входит

Чат игры, `returnMessageId`, вкладка Discussion, `sendSystemMessage`, thread, Files, macros, хаб Kernel, SSE battleground. Каталог имён `game` / `game_discussion` / `character_discussion` внутри Chat. Колонка `game_id`. Импорт Game, Character, Rule. Новый HTTP create. Сужение `getChatIdsOfUser`. Закрытие send/page по type для донорского id с HTTP мессенджера.

## Слои

| Тип | Задача | Не делает |
|---|---|---|
| Реестр типов | Принять строку от донора | Список игр, чтение чужих таблиц |
| `IChats::addTyped` | Строка чата и члены | Inbox, SSE, HTTP |
| `ChatHostTypes` | Как в шаге 6 | Реестр плагинов |
| Донор (не этот шаг) | `register` + `addTyped`, свой id | `chat.addPrivate` |

## Документы захода

этот файл; [`chat-roadmap.md`](chat-roadmap.md); [`chat-plan-06.md`](chat-plan-06.md); [`chat-system.md`](chat-system.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
