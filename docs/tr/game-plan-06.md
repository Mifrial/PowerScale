# План Game 6 — NPC без владельца и без второго листа

**Статус:** NPC в PHP сделан, 2026-10-04. `BACKEND_OPEN`. Suite `game` и `character` зелёные. Нарезка — [`game-roadmap.md`](game-roadmap.md) G6. Сессия — [`game-plan-05.md`](game-plan-05.md). Строка персонажа — [`game-plan-03.md`](game-plan-03.md). NPC и видимость — [`game-system.md`](game-system.md), [`character-system.md`](character-system.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: у игры появляется строка NPC. Лист — один, `npc.version`, в таблице Game. Технический `npc.actual_version` — CAS этой строки. Владельца-игрока, `approvedCharacterVersion`, draft, модерации игрока и второй копии листа нет. Обязательной расы и потолков игрока нет. Смена `rulesRevision` игры лист не переводит. Отдельное действие пишет `npc.version` тем же движком миграции по `code`, с `expectedNpcActualVersion`. Это не `character.migrate` и не approve. Создание NPC не кладёт его в сессию G5 и не открывает бой.

## Сверка G1–G5

Код `Roleplay/Game` и `character.migrate` для строки NPC не читается и не пишется: таблицы NPC нет.

- `game` хранит `rules_revision`. `Games::update` меняет номер, пока нет сессии и игра не `completed`. Migrate персонажей и NPC этот метод не вызывает. Так и остаётся: смена ревизии игры NPC не мигрирует.
- `game_character` — владелец, snapshot, `approvedCharacterVersion`, бонусы, модерация. NPC этой строкой не является.
- `game_session` и `game_session_character` — снимок `character_id` с истинным `canStartSession`. Колонки NPC нет. `GameSessions` её не получает.
- `CharacterMigration::migrate` грузит строку `character`, проверяет порт сессии и пишет actual через `replaceMigrated`. Входа `gameId` нет. Character модуль `Roleplay\Game` не импортирует. Этот метод NPC не обслуживает.
- Конструктор `GameSchema` принимает пять `IOpenedSchema`. Это единственное место каркаса, без которого новая карта не ставится.

## Зафиксировано (модель)

NPC — персонаж игры без владельца-игрока. Одна строка `game_npc` на одного NPC. Ссылки на `character` нет. Snapshot, draft и второй JSON листа нет.

`npc.version` — один JSON. Ключи те же, что отдаёт движок Character по отдельности: `choices`, `sheet`, `spaceId`, `spaceCode`, `rulesRevision`. Колонки `character` (`choices`, `sheet`, `rules_revision`, `actual_version`) сюда не копируются. `npc.actual_version` — целое на строке, со старта 1, в этот JSON не входит. Конфликт CAS не пишет строку. Имя колонки и `choices.name` — одно значение.

Видимость — один объект, не массив правил эскиза. `scope`: `all`, `gm` или `users`. `userIds` — список id; при `all` и `gm` он пустой, при `users` это выбранные игроки. `sections` — список кодов. Другой `scope` — `INVALID_PARAMS`. Коды секций только `characteristics`, `resources`, `abilities`, `inventory`. Имя видно, когда NPC этому актору доступен. Если кода секции нет в списке, из ответа вырезаются только эти ключи сборки. `characteristics`: `choices.characteristicPurchases`, `sheet.characteristicPurchases`, `sheet.characteristicPurchaseOs`. `abilities`: `choices.abilities`, `sheet.abilityLevels`, `sheet.racialAbilityCodes`, `sheet.coveredPaths`. `inventory`: `choices.inventory`, `sheet.equippedModifiers`. `resources`: `choices.money`, `sheet.money`. Других ключей под этими именами `CharacterSheetDocument` и документ `choices` не содержат. `shortDescription`, `fullDescription`, `race` и `states` в список секций не принимаются и при доступности NPC остаются. Чужой код секции — `INVALID_PARAMS`. Недоступный NPC — `GAME_NOT_FOUND`.

Обязательной расы нет. Потолки ОС/ОЛ/ОР игры и бонусы строки персонажа к NPC не применяются.

Перевод — отдельное действие. Оно берёт текущий `npc.version`, ремапит ссылки по `code` на `rulesRevision` этой игры и при пустых problems пишет новый `npc.version` и увеличивает `npc.actual_version`. Строку `character` не создаёт и не меняет. Approve не вызывает. `Games::update` это действие не вызывает.

В состав `game_session_character` NPC не входит. Участие в бою — G12.

## Что даёт этот заход

`game.createNpc`, `game.getNpc`, `game.updateNpc`, `game.translateNpc`. Таблица `game_npc`. Чтение режет секции до ответа. Перевод пишет лист тем же движком по `code`.

## Что не закрыто

- Состав сессии G5, бой, `battleId`, process, offer, initiative.
- Порт мутации actual игрока (G10). Экономика и выдача в NPC (G11). Боевой состав (G12). Проекции пачкой и краткий roster (G14). Доставка (G15).
- Видимость секций строки персонажа (G7). Приглашения, летопись, чаты.
- Список NPC пачкой, поиск по тегам, модерация предложения игрока, удаление NPC.
- Vue поверх этого PHP.

## Точки кода G1–G5

Старые `game-plan-01.md`–`game-plan-05.md` не переписываются. Ниже только место, без которого строка NPC не ставится. Поведение сессии, membership и `character.migrate` не меняется.

- `GameSchema`. Шестая зависимость конструктора — DDL `game_npc`. Это потолок зависимостей. `getTableClasses` и `install()` добавляют карту после `game_character` и до сессии: сессия на NPC не ссылается. Единственный `new GameSchema` — `GameMysqlFixture::installGameSchemas`. `dropGameTables` удаляет `game_npc` до `game`. `GameSessions`, `GameSessionCharacterTable`, `Games::update` и `CharacterMigration::migrate` не меняются.

Движок перевода достижим только из `CharacterMigration::migrate`: `CharacterRevisionRemap` и `CharacterSaveAssembly` лежат в `Service/` Character, а `remapped`, `build` и `conflicts` приватные. `migrate` грузит строку `character`. Для NPC этого мало, и это не правка G1–G5. Новый порт в `Character/Interface/Service/` ничего не пишет в `character`. Два входа. Сборка: `choices`, `spaceId`, ревизия. Ремап: те же `choices`, `spaceId`, исходная и целевая ревизия; сначала `CharacterRevisionRemap::remap`, затем та же сборка. Сборка — публичный `CharacterSaveAssembly::build`. Перед ним те же публичные проверки, что `CharacterMigration::build`: `CharacterSaveKeys::assertLimits` с `allowMoney` false, `assertLists`, `assertExpectedSheet`. Лишний ключ — `INVALID_PARAMS`, не conflicts. `assertLimits` при false не принимает `money` внутри `limits`: наличные остаются `choices.money`. В `build` последним аргументом уходит тот же документ `choices`, не новый. Иначе пропадут `shortDescription`, `fullDescription` и `ageYears`: `CharacterChoiceAssembler::assemble` их не читает, `buildDocument` кладёт их только в этот документ. Нет ключа `active` — true, как `buildDocument`. Потолки игры в `limits` не передаются. `CharacterInputChecks` на пустой `raceCode` всегда добавляет `CHARACTER_RACE`; ремап пустую расу уже сохраняет. Оба входа этот problem снимают и не считают его conflicts. Непустой код расы по-прежнему проходит живую проверку. Изменение NPC зовёт сборку на ревизии, уже лежащей в строке. Перевод зовёт ремап на ревизию игры. `character.migrate` контракт не меняет: пустая раса во вход игрока не добавляется, порт сессии и `replaceMigrated` остаются. Game вызывает новый порт и не вызывает `migrate`. `Service/` Game `Service/` Character не импортирует.

## Модуль

Тот же `Roleplay/Game`, без нового модуля. Порт листа NPC — `Character/Interface/Service/`, регистрация в `ports` `Character/module.config.php`. Game берёт его из локатора по интерфейсу, как `ICharacters` в `GamePortFactory`. Реализация порта — Character `Service/`. `events` остаётся `[]`. Suite `game`. Suite `character` остаётся зелёным: сигнатура `migrate` и отказ участника сессии прежние.

**DAG:** Game → SmartTable + User + RuleSpace + Character. Новое ребро — Game вызывает порт перевода. Character не импортирует Game. `Service/` Game не импортирует `Service/` Character.

Ошибки прежние: `GAME_INVALID`, `GAME_NOT_FOUND`, `GAME_CONFLICT`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. `CHARACTER_INVALID` этому заходу не принадлежит.

| Источник | Лист |
|---|---|
| нет игры; нет строки NPC; актор не видит NPC по scope; нет `game.edit` этой игры и нет `game.edit_all` на запись | `GAME_NOT_FOUND` |
| игра `completed`; перевод, когда лист уже на ревизии игры; в `npc.version` нет объекта `choices`; update или перевод присылает другой `spaceId`, `spaceCode` или `rulesRevision`, чем уже лежит в строке | `GAME_INVALID` |
| `expectedNpcActualVersion` не равен `npc.actual_version` | `GAME_CONFLICT` |
| `characterId`, `approvedCharacterVersion`, `status`, `proposedBy`, бонусы, потолки во входе | `INVALID_PARAMS` |
| нет актора | `AUTH_REQUIRED` |

`game.edit` не глобальный ключ каталога. Запись собирается как `GameSessionHttp::assertEditor`: владелец, роль `gm` через `GamePermissionKeys::keysFor`, либо `game.edit_all`. Нет вывода — `GAME_NOT_FOUND`. `player` строку не создаёт и не меняет.

Чтение — отдельный вход, не `GameHttp::assertVisible`. Тот метод пускает только владельца и `game.view_all`: ни `gm`, ни строка `game_member` карточку `game.get` не открывают. Владельца G1 в `game_member` не пишет. Для NPC читатель — владелец, строка `game_member` этого пользователя, либо `game.view_all`. Дальше scope. `game.view_all` scope не обходит.

## Таблицы

Карта в `GameSchema::getTableClasses` и в `install()`. DDL — `createTable` / `updateTable`.

**`game_npc`.** Один NPC игры. Unique по id. Несколько NPC одной игры разрешены.

| Поле | Смысл |
|---|---|
| `id` | IdField, суррогат. Это `npcId` |
| `game_id` | reference на `game`, `restrict` |
| `name` | имя. Видно при доступности NPC |
| `version` | JsonField, required. Это `npc.version`. Второй лист рядом не хранится |
| `actual_version` | IntField, минимум 1. Технический CAS, в лист не копируется |
| `visibility` | JsonField, required. Объект `scope` (`all`, `gm`, `users`), `userIds`, `sections` |

Колонок `character_id`, `owner_id`, `approved_character_version`, `status`, `proposed_by`, бонусов и draft нет. Колонки NPC в `game_session_character` нет.

Создание пишет `actual_version = 1` и `npc.version` на текущие `spaceId`, `spaceCode` и `rulesRevision` игры. Входной `choices` для порта сборки: `name`, `raceCode` пустая строка, пустые списки `abilities`, `inventory`, `characteristicPurchases`, `customRules`, `limits` пустой объект, `money` 0, `active` true. Пустой объект `limits` проходит `assertLimits` с false: ключей нет. `expectedSheet` — null, ремапа нет. В строку пишутся `choices` и `sheet` сборки. Пустой `sheet` не хранится: `CharacterSheetDocument` собирает объект с ключами `abilityLevels`, `money` и остальными, и такой объект не равен `{}`. Иначе первый `game.updateNpc` с листом из ответа создания всегда получит `CHARACTER_SHEET`. Нет `choices` после записи быть не может. Потолки игры в `limits` не копируются.

Изменение сверяет `expectedNpcActualVersion` до сборки. Успех пишет имя, видимость и `choices`/`sheet` сборки, затем увеличивает `actual_version` на 1. Успешный перевод пишет то же из ремапа. Оба пути — одна запись строки. Conflicts не меняют строку.

## Фасад

Новый сценарий в `Service/` Game, не методы `IGames`, `IGameMemberships` и не методы сессии. `GameHttp` и `GameCharacterHttp` новые action не получают: у обоих публичных методов уже девять с конструктором. Четыре action — отдельный HTTP-класс, порты и `routes` в `Game/module.config.php`, `csrf` true.

Создание. Актор с `game.edit` этой игры или с `game.edit_all`. Игра не `completed`. Строка `character` не создаётся. Сессия не читается и не пишется. Бой не стартует.

Чтение. Сначала вход из абзаца про чтение выше, потом scope. Имя всегда, если scope пустил. Четыре секции вне списка вырезаются до JSON по карте ключей выше. `CharacterSheetAccess` так не делает: он отдаёт коды и полную строку. Для NPC ключи снимаются в Game до ответа. Владелец и роль `gm` видят лист целиком, scope для них не фильтр секций. Scope `all` — строки `game_member`. Scope `users` — id из `userIds`; владелец и `gm` и здесь видят лист целиком. Остальные — `GAME_NOT_FOUND`. `GameModuleSetup` уже отдаёт `GameSchema::getTableClasses()`, вторая регистрация карты не нужна.

Изменение. Тот же актор, что создание. Игра не `completed`. CAS сверяется до сборки: чужой `expectedNpcActualVersion` — `GAME_CONFLICT`, порт не вызывается. Клиент присылает `choices` и `sheet`. `spaceId`, `spaceCode` и `rulesRevision` в записи не меняются: другие значения во входе — `GAME_INVALID`. Имя пишется в колонку и в `choices.name`. Порт сборки разбирает `choices` в аргументы `CharacterSaveAssembly::build` так же, как `CharacterMigration` перед своим `build`: имя, раса, списки, `limits`, `money`. `shopCreate` — false, как у update персонажа и у migrate. Присланный `sheet` идёт в `expectedSheet`. Расхождение даёт уже существующий `CHARACTER_SHEET` и не пишет строку. Problems — тот же `conflicts`, строка и `actual_version` прежние. Пустой список problems — в `npc.version` пишутся `choices` и `sheet` сборки, не сырое тело, `actual_version` увеличивается на 1. Обязательной расы нет. Потолки и бонусы игрока не читаются. `approvedCharacterVersion` не пишется. Ремап этот путь не вызывает.

Перевод. Тот же актор. `expectedNpcActualVersion` обязателен. Нет `choices` или ревизия листа уже равна ревизии игры — `GAME_INVALID`, строка прежняя. Иначе порт ремапит по `code` на ревизию игры и собирает лист с `expectedSheet` null и `shopCreate` false. Ответ conflicts повторяет ключи `CharacterMigration::conflicts`: `kind`, `problems`, `revision`, `choices`, `sheet`. Строка при этом прежняя. Problems пустые — запись нового `choices` и `sheet` в `npc.version`, ревизия листа становится ревизией игры, `actual_version + 1`. `character.migrate` не вызывается. Строки `character` нет. Approve нет. `Games::update` по-прежнему только меняет номер игры.

`game.startSession` состав не расширяет. Проверка гейта: после создания NPC старт пишет в `game_session_character` только прежних персонажей с истинным `canStartSession`.

## HTTP

`game.createNpc`, `game.getNpc`, `game.updateNpc`, `game.translateNpc`.

Создание: `gameId`, `name`, `visibility`. Успех — строка: `npcId`, `gameId`, `name`, `npc.version`, `npc.actualVersion`, `visibility`.

Чтение: `gameId`, `npcId`. Успех — та же форма, лист уже без скрытых секций.

Изменение: `gameId`, `npcId`, `name`, `visibility`, `choices`, `sheet`, `expectedNpcActualVersion`. Успех без problems — строка с `choices` и `sheet` сборки и новым `npc.actualVersion`. Problems — `conflicts`, счётчик прежний. Ревизию листа это действие не двигает.

Перевод: `gameId`, `npcId`, `expectedNpcActualVersion`. Успех без problems — строка NPC, `rulesRevision` внутри `npc.version` равен ревизии игры, счётчик увеличен. Ответ с problems — `kind: conflicts` и поля `CharacterMigration::conflicts`; в базе строка прежняя. Продолжение — повтор `game.updateNpc` с `choices` и `sheet` из этого ответа. Серверного черновика нет. `character.migrate` не вызывается.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `status` `proposed`, `game.proposeNpc`, `game.moderateNpc`, `proposedBy`. У NPC модерации игрока нет.
- `game.deleteNpc`. Этот шаг удаление не даёт.
- `game.getNpcSummaries` и пачка листов. Это G14.
- `game.updateNpc` только с `npcId`, без `gameId`.
- Перевод через клиентский `characterMigrationService` и запись результата в `game.updateNpc`. На сервере перевод — `game.translateNpc` и движок по `code`.
- `SheetVisibility` как список `{ audience, sections }`. Контракт шага — один scope и список из четырёх кодов.
- Отдельные `tags`, `shortDescription`, `fullDescription` рядом с `version`. Описания живут в `choices`/`sheet`. Имя — колонка и `choices.name`. Видимость режет только четыре секции выше.
- Участие `npc:` в бою, луте и чате из моков. Бой — G12, экономика — G11.

## Тесты

Mysql строки. Создание пишет одну `game_npc`, `actual_version` 1, ревизия листа равна ревизии игры, строки `character` не прибавляется, snapshot нет. Повторное создание — вторая строка, не вторая копия той же. `completed` — `GAME_INVALID`. `player` и посторонний — `GAME_NOT_FOUND`. `gm` создаёт в чужой для владельца игре. `edit_all` создаёт без роли.

Mysql CAS. Изменение с верным `expectedNpcActualVersion` и пустыми problems пишет лист сборки и увеличивает счётчик. Чужой счётчик — `GAME_CONFLICT`, порт сборки не вызывается, лист прежний. Смена ревизии через update — `GAME_INVALID`. Problems сборки — `conflicts`, счётчик прежний. Пустая раса в problems не входит.

Mysql видимости. Scope `gm`: участник-`player` на `game.getNpc` получает `GAME_NOT_FOUND`. Scope `all`: тот же участник видит имя и только открытые секции; характеристики, ресурсы, способности и inventory вне списка в JSON отсутствуют. Ведущий видит секции.

Mysql перевода. После `game.update` игры на новую ревизию (сессии нет) лист NPC остаётся на старой. `game.translateNpc` с верным счётчиком пишет новую ревизию в `npc.version` и увеличивает счётчик. Повтор — `GAME_INVALID`. Нет `choices` — `GAME_INVALID`. Минимальный лист создания, с пустой расой, переводится и не возвращает `CHARACTER_RACE`. Непустой код неживой расы остаётся в `problems`, строка не пишется. Conflicts не пишет строку. Вызова `character.migrate` нет, строка `character` не появляется. Участник сессии по-прежнему получает `CHARACTER_INVALID` на свой migrate.

Mysql гейта сессии. NPC создан, затем `game.startSession`: в `game_session_character` нет ссылки на этого NPC. Stop сессии лист NPC не меняет.

Suite `game`. Suite `character`: порт перевода в тестах migrate не обязателен для каждого кейса; один тест проверяет, что пустая раса не принимается входом `character.migrate`.

## Todo

- [x] **table** — `game_npc`: один лист, `npc.actual_version`, видимость, без snapshot.
- [x] **crud** — создать, прочитать с вырезанием секций, изменить по CAS.
- [x] **translate** — порт движка по `code`, `game.translateNpc`, не `character.migrate`.
- [x] **session** — старт G5 не берёт NPC. `GameSessions` не меняется.
- [x] **gates** — phpunit `game` и `character`; cs новых файлов. Нет второго листа.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| карта NPC | `Table/` `Schema/` | `game_npc`, шестая зависимость `GameSchema` |
| сценарий NPC | Game `Service/` | создание, чтение, изменение, перевод |
| HTTP NPC | Game `Service/` `Action/` | отдельный класс |
| видимость | Game `Service/` | scope и вырезание секций до ответа |
| порт листа NPC | Character `Interface/Service/` | сборка и ремап по `code`, без записи |
| реализация порта | Character `Service/` | тот же remap и та же сборка, что migrate |
| `CharacterMigration` | Character `Service/` | не вызывается для NPC и не меняет контракт игрока |
| сессия | Game `Service/` | без изменений |

## Acceptance G6

- Suite `game` и `character` зелёные; cs/quality затронутых модулей.
- Нет `approvedCharacterVersion`, draft и второго листа. Нет строки `character` у NPC.
- Обязательной расы и потолков игрока нет.
- Смена `rulesRevision` игры лист NPC не переводит. Перевод — отдельное действие, `expectedNpcActualVersion`, движок по `code`.
- Конфликт CAS не пишет строку. Conflicts перевода не пишет строку.
- Скрытые секции не попадают в ответ. Недоступный NPC — `GAME_NOT_FOUND`.
- Создание NPC не добавляет его в `game_session_character` и не стартует бой.
- Character не импортирует Game. `character.migrate` остаётся действием владельца строки `character`.
- G10, G11, G12, G14 и G15 этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G6; [`game-plan-05.md`](game-plan-05.md); [`game-plan-03.md`](game-plan-03.md); [`game-system.md`](game-system.md); [`character-system.md`](character-system.md); [`architecture.md`](architecture.md); [`php-coding-standards.md`](php-coding-standards.md).
