# План Chat 1 — таблицы и фасад

**Статус:** план, сверено с Kernel/ST/User 2026-09-04. Нарезка — [`chat-roadmap.md`](chat-roadmap.md). Канон UI — [`chat-system.md`](chat-system.md) (имена `IChatApi` / `sync` **не** копировать в фасад). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Setup — [`kernel-plan-01-setup.md`](kernel-plan-01-setup.md). User — [`user-plan-01-account.md`](user-plan-01-account.md). Unique членства — [`user-plan-07-member-unique.md`](user-plan-07-member-unique.md). Составной unique — [`smarttable-plan-16-composite-unique.md`](smarttable-plan-16-composite-unique.md).

Цель: модуль **`Messages/Chat`** (ленивый), три карты, публичный фасад `IChats`. **Нет** HTTP, SSE, Vue, visibility, Files, Roleplay.

Порт не называть `IChat` (имя модуля). Сосед не `open`, не Repository. Листья ошибок — `ChatException`, не голый `RuntimeException`.

## Решения

**Группа диска `Messages`, не Core.** Сейчас `config/modules.php` → `'lazy' => []`. `ApplicationFactory::boot()` зовёт `loadCore()` (`scandir` только `modules/Core/*`) и вешает слоты `registerLazyFromCatalog`, затем `freeze`. `Messages/Chat` в HTTP-процессе **нет**, пока никто не `get(IChatContainer::class)` (фабрика слота делает `requireModule`). `bootSetup()` зовёт `loadAllFromDisk()` — все `modules/{Group}/{Name}/module.config.php`, **без** lazy catalog; CLI `bin/setup.php` ставит схему Chat вместе с User, как только папка модуля есть. Запись только в `lazy` без каталога на диске в setup не попадает.

Ключ `'setup' => ChatModuleSetup::class` (class-string, no-arg, как User; не closure как Mail). `IModuleSetup` не порт контейнера. `getTableClasses()` — те же три class-string, что `ChatSchema`. Data-шаги `[]`. `ModuleSetupCollectorTest` (сейчас только Core) после захода знает `Messages/Chat`.

**Lazy vs HTTP.** Слот в `modules.php` нужен контейнеру и mysql-тестам (`boot()` + первый `get(IChatContainer)`). `getRoutes()` читает **уже загруженные** модули: без `requireModule` маршрутов Chat нет. Это **не** чинить в плане 1 (`routes` пустые). Шаг 2: `requireModule` lazy на boot **только ради маршрутов**, контейнер по-прежнему ленивый ([`chat-plan-02.md`](chat-plan-02.md)). Не переносить модуль в `Core/`.

**Нет `game_id`.** `type` только `private` | `group` (нормализатор, не enum-поле ST). Имя: group — непустая строка после trim; private — `''`.

**Пара private уникальна.** Колонка `pair_key` string unique nullable: `{minUserId}:{maxUserId}` (меньший id первым). Пишет репозиторий чата, не DTO. Group — `null` (несколько NULL при UNIQUE — поведение MySQL, план 6). Повтор `addPrivate` тех же двоих → **тот же id**, не `CHAT_DUPLICATE` (порядок аргументов не важен). Гонка: insert → `UniqueConstraintException` по `pair_key` → прочитать строку. Повтор, если члены не дописались, **дописывает** недостающие членства. `addPrivate($id, $id)` → `CHAT_INVALID`. Планы 16/7 `pair_key` **не** заменяют: пара людей — не две колонки стола `chat`.

**Членство — таблица**, как User. Составной unique ST 16: `defineUniqueKeys [['chat_id', 'user_id']]`. Имя DDL: `chat_member_chat_id_user_id_unq`. Суррогат `member_key` **не** копировать. `user_id` — `ReferenceField::forTable('user_id', …, 'user', 'restrict')` (**не** `UserTable::class`: Chat не импортирует `User/Table`). `chat_id` → `ChatTable::class` restrict (свой модуль). Фасад перед add проверяет учётку через **`IUserAccounts::getById`**.

**Сообщение:** PK `IdField::big()`. `attachments` json required, `default` `[]` (как meta ST). Не `dice_result`, не `thread`/`kind`/`speaker`/`visibility`. `created_at` и `updated_at` — datetime required, `default` `DateTimeNow`. На send репозиторий пишет **оба** одним `DateTime::now()` (иначе два sentinel разойдутся на тик). Visibility потом двигает только `updated_at`. `send` обновляет `chat.updated_at` отдельным `update` (без `transaction()` в фасаде, как LAST_BYPASS).

**Индексы ленты.** Выборка шага 1: `filter chat_id` + `sort created_at DESC, id DESC` (два ключа sort ST умеет, порядок PHP-массива). Путь — индекс InnoDB на FK `chat_id` (хвост PK ≈ `id`). `indexed` на `chat_id` не ставить: FK и так индекс; Mail ставит `indexed` на reference ради имени `_idx` — здесь не нужно. Одноколоночный `indexed` на `created_at`/`updated_at` (план 6) ленту не кормит; для живости шага 1 не обязателен. Оставляем как дешёвый задел под sync (шаг 3), не как замену составному. Составной INDEX `(chat_id, created_at)` ST не умеет; в этот заход не входит. Когда появится в ST — кортежи на карте + `updateTable`, запросы не переписывать.

**`last_read_message_id`** на членстве: `new IntField(…, FieldSettings::fromOptions([]), null, null, true)` — bigint nullable, **без** FK (`ReferenceField` это INT; `IdField::big()` на членстве не нужен). `IntField::big()` в ST нет. `markRead(chatId, userId)` ставит max `id` сообщений чата; пустой чат — `null`.

**Членство на чтении — не размазывать «не член» на add.** Как группа User, плюс явный viewer только там, где он в сигнатуре:

- `getById(chatId)` — есть строка / `CHAT_NOT_FOUND`, **без** проверки членства (id уже известен).
- `getMemberIds(chatId)` / `addMember` / `removeMember` — как User: есть чат или `CHAT_NOT_FOUND`. `addMember` **не** требует, чтобы `userId` уже был членом (иначе add невозможен). Дубль пары → `CHAT_DUPLICATE` (1062). `removeMember` без строки членства → `CHAT_NOT_FOUND`. Актор HTTP — шаг 2.
- `send` / `markRead` / `findMessagePage` — `userId` в сигнатуре; нет чата **или не член** → `CHAT_NOT_FOUND`.
- Private: `addMember` / `removeMember` → `CHAT_INVALID`. Нельзя выйти из private.
- Group: создатель в членах; снять последнего → `CHAT_INVALID` (≥1 член).

**Пустое send:** trim `content`; пустой content **и** пустые attachments → `CHAT_INVALID`. Вложение: `{type: string, payload: json-совместимо}`; нет/пустой type → `CHAT_INVALID`; лишние ключи вложения отбросить, не INVALID. Не Files. `''` в required string/text для ST — не null, ок (имя private, пустой content при вложениях).

**Страница сообщений:** `findMessagePage(chatId, userId, limit, offset)` → `{ items, total }` (как User members). Sort `created_at` DESC, `id` DESC; limit 1…500, offset ≥ 0, иначе `CHAT_INVALID` (`assertPageBounds` до `ListQuery`, как User; `MapInvalidException` с ST тоже → `CHAT_INVALID`). `userId` нужен, иначе «не член» не проверить; HTTP шаг 2 подставит актора. Не два метода getMessages+count: иначе `IChats` > 10 public (`ClassQuality` считает и интерфейс). Фасад не `ListQuery`.

**Списки id** — как User, до 500 (`getChatIdsOfUser`, `getMemberIds`). Страница чатов — шаг 2.

**`IUserAccounts`** в фабрике Chat: `$serviceLocator->get(IUserContainer::class)->get(IUserAccounts::class)`. Chat не импортирует `User/Service`, `User/Table`. `USER_NOT_FOUND` **не** протекает с `IChats`: поймать `UserNotFoundException` → `CHAT_NOT_FOUND`. User Chat не импортирует. Репозитории Chat ловят листья ST (`UniqueConstraintException`, `RowNotFoundException`, `FieldRequiredException` / `FieldInvalidException`, `MapInvalidException`) → `ChatException`, не наружу.

**Имена таблиц:** `chat`, `chat_member`, `chat_message` (не `chats` plural).

**Force leftover — нет.** Новый модуль, живой `member_key` на chat нет. `ChatSchema::install` / Kernel setup — `createTable` / `updateTable`, **не** `forceUpdateTable`. Фикстуру legacy как User 7 не заводить. Kernel / `ChatModuleSetup` force не расширять.

**Граф DDL.** У `chat` нет FK на `user` (`pair_key` — строка). Рёбра: `chat_member`/`chat_message` → `chat` и → `user`. CLI topo сам: `user` и `chat` независимы, дети после обоих. `ChatSchema::install()` для тестов: `chat` → member → message. Тесты **сначала** `UserSchema::install()` (как Auth), иначе FK на `user` падает.

## Контракт `IChats` (ровно 10 public)

Без `install`, без unread, без `sync`. Без `phpcs:disable` на лимит методов (ни интерфейс, ни `Chats`).

| Метод | Сигнатура | Смысл |
|---|---|---|
| `addPrivate` | `(int $firstUserId, int $secondUserId): int` | Идемпотентная пара; тот же id |
| `addGroup` | `(NewGroupChat $newGroupChat): int` | Новая строка; имя не unique |
| `getById` | `(int $chatId): ChatRecord` | Без членства |
| `getChatIdsOfUser` | `(int $userId): array` | id чатов, до 500; нет user → NOT_FOUND |
| `getMemberIds` | `(int $chatId): array` | user id, до 500 |
| `addMember` | `(int $chatId, int $userId): void` | Только group |
| `removeMember` | `(int $chatId, int $userId): void` | Только group; не последнего |
| `send` | `(int $chatId, int $userId, string $content, array $attachments): int` | id сообщения |
| `findMessagePage` | `(int $chatId, int $userId, int $limit, int $offset): MessagePage` | Член обязателен |
| `markRead` | `(int $chatId, int $userId): void` | max id или null |

`NewGroupChat`: `name` + `creatorId` + `memberIds`. Создатель всегда в членах, даже если его нет в `memberIds`. Дубли id в списке схлопнуть до insert (иначе unique → DUPLICATE на create). Нет учётки → `CHAT_NOT_FOUND`. Пустое имя group → `CHAT_INVALID`.

## Todo

- [ ] **module** — `modules/Messages/Chat/`: `module.config.php` (`container`/`locator`/`ports` только `IChats`, `setup` class-string, `routes`/`events` `[]`), `IChatContainer` + `ChatContainer` как User/Mail, `ChatPortFactory` (шлюз ST + `IUserContainer`). Autoload `Mifrial\Messages\Chat\` и tests, `exclude-from-classmap` tests. Suite `chat` в `phpunit.xml.dist`. `lazy`: `IChatContainer::class` → `{group: Messages, name: Chat}`. `ModuleSetupCollectorTest` — ключ `Messages/Chat`. Mysql-тест: `boot()` не грузит Chat, первый `get(IChatContainer)` грузит.
- [ ] **tables** — три карты (поля ниже). `ChatSchema::install()` / `getTableClasses()`. `ChatModuleSetup` делегирует class-string. Create-граф как выше.
- [ ] **facade** — `IChats` / `Chats` ровно 10 public. Нормализатор type/name/content/вложений — отдельный тип (как `UserInputNormalizer`), не колонки. `pair_key` только репозиторий. Ctor фасада: три репозитория + `IUserAccounts` (≤6). Репозиторий членства держать ≤10 public; `phpcs:disable` на лимит не ставить заранее.
- [ ] **tests-gates** — mysql как User (`MIFRIAL_CONFIG=test`, `powerscale_test`; phpunit часто `required_permissions: ["all"]`). Не DDL на живой `powerscale`. Сначала `UserSchema`. Drop: `chat_message` → `chat_member` → `chat`, затем `UserMysqlTables::drop` (не копировать Auth/User Table в прод-код Chat; тест может импортировать `UserSchema` / `UserMysqlTables` как Auth). Сценарии: двое → private, повтор тот же id (порядок аргументов любой), `pair_key` не с фасада; self-private INVALID; group имя; group `pair_key` NULL, два group ок; дубль членства → DUPLICATE; send+`findMessagePage` total; markRead; не член send/page → NOT_FOUND; `getById` чужого id **находит** строку; private addMember INVALID; пустой send INVALID; вложение без type INVALID; нет user → `CHAT_NOT_FOUND` (не `USER_NOT_FOUND`); снять последнего group → INVALID; limit 0 / offset −1 → INVALID. Если mysql-класс упрётся в 500 строк — второй класс, не leftover. cs/quality.

## Карты

**`chat`**

| Поле | Заметки |
|---|---|
| `id` | `IdField` (INT, не big) |
| `type` | string required |
| `name` | string required (private `''`) |
| `pair_key` | string unique nullable, не required |
| `created_at` | datetime required, `default` `DateTimeNow` |
| `updated_at` | datetime required, `default` `DateTimeNow`; send обновляет |

**`chat_member`**

| Поле | Заметки |
|---|---|
| `id` | `IdField` |
| `chat_id` | `ReferenceField` → `ChatTable` restrict required |
| `user_id` | `ReferenceField::forTable(..., 'user', 'restrict')` required |
| `last_read_message_id` | bigint nullable, не reference |
| `joined_at` | datetime required, `default` `DateTimeNow` |
| unique | `defineUniqueKeys`: `['chat_id', 'user_id']` |

**`chat_message`**

| Поле | Заметки |
|---|---|
| `id` | `IdField::big()` |
| `chat_id` | reference `ChatTable` restrict required |
| `user_id` | `forTable('user')` restrict required |
| `content` | text required (пусто ок, если есть attachments) |
| `attachments` | json required, default `[]` |
| `created_at` | datetime required, `default` `DateTimeNow`, `indexed` (задел sync, не путь ленты) |
| `updated_at` | datetime required, `default` `DateTimeNow`, `indexed` (задел sync, не путь ленты) |

## DTO

`ChatRecord`: `getId`, `getType`, `getName`. Нет `pair_key`, нет `username`.

`MessageRecord`: id, chatId, userId, content, attachments, даты — `Mifrial\Core\Kernel\Value\DateTime`, не ISO-строка Vue.

`MessagePage`: `items` + `total` (геттеры, как `MemberIdPage`).

`NewGroupChat`: name + creatorId + memberIds. Private: два int на фасаде, не DTO мешок.

Нормализатор: trim name/content/type вложения; type чата только два литерала.

## Ошибки

Листья `ChatException` (`ActionException`): `ChatNotFoundException` `CHAT_NOT_FOUND`, `ChatDuplicateException` `CHAT_DUPLICATE` (дубль членства / гонка addMember), `ChatInvalidException` `CHAT_INVALID`. Не `USER_*`, не ST, не голый `RuntimeException`.

## Не входит

HTTP/SSE/Vue. `requireModule` в HTTP-boot (только зафиксировать). Visibility, system/thread/roll. Files. Guest. Delete чата/сообщения. Unread в фасаде. `sync` / `chat.sync` action. Хаб сцены. `game_id`. Составной неуникальный INDEX. `member_key`. `forceUpdateTable` / leftover-фикстура. `phpcs:disable` на 10 public. Перенос в `Core/`.

## Слои

| Тип | Папка | Задача | Не делает |
|---|---|---|---|
| три `*Table` | `Table/` | карты | `UserTable` class |
| Record / New / Page | `Dto/` | геттеры / ключи | `pair_key`, JSON HTTP |
| `IChats` | `Interface/Service/` | сосед | `ListQuery`, `IChat` |
| `IChatContainer` | `Interface/Container/` | слот локатора | домен |
| `Chats` | `Service/` | now, pair, инварианты private/group | колонки, `open` |
| `ChatPortFactory` | `Service/` | `new` + `get` чужого контейнера | сценарий runtime |
| репозитории | `Repository/` | keys, records, map ST→Chat | порт соседа |
| `ChatSchema` | `Schema/` | DDL трёх карт | seed, User-таблицы |
| `ChatModuleSetup` | `Setup/` | class-string карт | force |
| листья | `Exception/` | `CHAT_*` | `RuntimeException` |

## Документы захода

этот файл; roadmap шаг 1 (индексы — одноколоночные); [`TR.md`](TR.md); architecture; `composer.json`; `phpunit.xml.dist`; `config/modules.php`; `ModuleSetupCollectorTest`.

## Следующий заход

HTTP commands — [`chat-plan-02.md`](chat-plan-02.md). Chat остаётся `lazy` (маршруты на boot, контейнер по запросу); `sync` по-прежнему не action.
