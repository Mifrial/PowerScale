# План Game 14 — проекции чтения

**Статус:** проекции чтения в PHP сделаны, 2026-10-04. `BACKEND_OPEN`. Suite `game` по `GameProjectionMysqlTest` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G14. Канон — [`game-system.md`](game-system.md): roster — краткие записи; полный лист читается отдельной границей по ключам, не пачкой; видимость применяется до ответа; `GameStateSnapshot` не содержит полный roster и листы. NPC — [`game-plan-06.md`](game-plan-06.md): `game.getNpc` уже режет четыре секции; список NPC пачкой тот шаг не закрывал. Строка персонажа — [`game-plan-07.md`](game-plan-07.md): `section_visibility` только хранится; `game.getCharacter` лист не режет. Бой — [`game-plan-12.md`](game-plan-12.md): `battleId` и состав уже есть; идентичность боя не переписывается. Сессия — [`game-plan-05.md`](game-plan-05.md): снимок старта — строки «сессия + персонаж», не полный лист. Индекс — [`TR.md`](TR.md). Границы — [`architecture.md`](architecture.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: один заход. Появляется чтение roster краткими записями и чтение полного листа только по названным ключам. Чужая секция снимается до JSON. Для NPC маска та же, что у `game.getNpc`. Для строки персонажа список `section_visibility` впервые режет лист, и только в этом новом ответе. Боевой subset можно отдать по уже существующему составу `battleId`. Список NPC от боя не зависит и сессии не ждёт. Снимок сессии и `game.get` полный roster и листы не получают.

Зависимость шага — G6. Боевой subset опирается на G12 и без боя не обязателен: roster и лист по ключам живут и когда сессии нет. G13, `1 → N`, `N → 1`, DOT, каст, движение и battleground не входят. Доставка (G15) не проектируется: нет SSE, outbox, listener EventManager и физической схемы доставки.

## Сверка G5, G6, G7, G12

- `game.getNpc` собирает ответ в `GameNpcView::detail`. `GameNpcVisibility::mask` снимает ключи четырёх секций, если кода нет в `visibility.sections`. Владелец игры и `gm` получают лист целиком. Scope `gm` для `player` — `GAME_NOT_FOUND`. Scope `users` пускает id из `userIds`; остальные — `GAME_NOT_FOUND`. Имя остаётся, если scope пустил. Списка NPC в фасаде нет: `GameNpcRepository` отдаёт строку по id.
- `game.getCharacter` и `game.getCharacterList` отдают `GameCharacterViewAssembler::detail`: статус, бонусы, `sectionVisibility` и `approvedCharacterVersion` как лежат. Actual в этот JSON не кладётся. Список для актора без модерации фильтрует владельца. Вырезания секций нет.
- Восемь кодов строки персонажа: `shortDescription`, `fullDescription`, `race`, `states`, `characteristics`, `resources`, `abilities`, `inventory`. Пустой список пишет подача. Объект NPC (`scope`, `userIds`, четыре кода) на колонку не переносится.
- Четыре секции NPC режут пути `GameNpcVisibility`: `characteristics` — `choices.characteristicPurchases`, `sheet.characteristicPurchases`, `sheet.characteristicPurchaseOs`; `abilities` — `choices.abilities`, `sheet.abilityLevels`, `sheet.racialAbilityCodes`, `sheet.coveredPaths`; `inventory` — `choices.inventory`, `sheet.equippedModifiers`; `resources` — `choices.money`, `sheet.money`. `sheet.osSurchargeTotal` в этом списке нет, хотя `CharacterSheetDocument` его пишет. `racialAbilityCodes` лежит на `abilities`, потому что секции `race` у NPC нет.
- Снимок сессии — `game_session` и `game_session_character`. `game.get` знает `sessionRunning`. Полного roster и листов в этом ответе нет. `GameSessionRepository` наружу отдаёт id сессии и `hasParticipant`, не список листов.
- `GameBattleRepository::findParticipants` уже публичен и отдаёт `kind` и `subject_id`. `find` и `findIdsBySession` бой этой сессии отличают. Версия боя, `advanceVersion` и запись состава этим шагом не нужны.
- Character модуль Game не импортирует. Actual персонажа читается через `ICharacters::get`.

## Зафиксировано (модель и права)

**Две границы чтения.** Roster и полный лист — разные ответы. Roster не содержит `choices`, `sheet`, `version` NPC и `approvedCharacterVersion`. Полный лист приходит только если ключ назван. Действия, которое одним успехом отдаёт лист каждого NPC игры, нет.

**Ключ.** `{ type, id }`. `type` — `character` или `npc`. Пустой список ключей — `INVALID_PARAMS`. Повтор того же ключа в одном теле — `GAME_INVALID`. Чужой тип — `INVALID_PARAMS`. Верхняя граница списка — 32 ключа; длиннее — `INVALID_PARAMS`. Это не страница «все NPC».

**Roster.** Карточка проверяется `GameCardAccess::isVisible`: владелец, `game.view_all` или видимость игры. Кто прошёл, видит краткие записи персонажей. Персонаж: `characterId`, `name`, `status`, `actualVersion`. Строка `left` в roster не входит. NPC: `npcId`, `name`, `actualVersion`, и только если scope того же правила, что `GameNpcHttp::scopeAllows`. Scope `all` пускает только участника с ролью. `game.view_all` без роли карточку видит и краткие персонажи получает, но NPC со scope `all` ему не отдаётся: `getNpc` такому актору даёт `GAME_NOT_FOUND`. Имени чужого NPC вне scope в roster нет. Состав сессии и состав боя в этот ответ не вкладываются.

Имя персонажа и `actualVersion` берутся из `ICharacters::get`. В JSON roster нет `getChoices()` и `getSheet()`. Снимок `approvedCharacterVersion` не читается в roster.

**Полный лист по ключам.** Отдельный ответ, не поле roster. Персонаж: `choices` и `sheet` actual. NPC: уже лежащий `version`, той же маской, что `game.getNpc`. Нет строки, строка `left`, NPC вне scope или персонаж не этой игры — ключ в `missing`, листа этого ключа в ответе нет. Одного `GAME_NOT_FOUND` на всю пачку нет: иначе соседний открытый ключ не читается, а отказ на первом ключе выдаёт существование скрытого.

**Видимость до ответа.** NPC — `GameNpcVisibility::mask` после добавления `sheet.osSurchargeTotal` в `characteristics`. `racialAbilityCodes` остаётся на `abilities`: секции `race` у NPC нет, и перенос ключа сделал бы его нескрываемым. Владелец игры и `gm` видят документ целиком. Остальные — без секций, которых нет в `sections`. Тот же mask у `game.getNpc` и у листа по ключу, иначе два чтения одного NPC разойдутся.

Строка персонажа. Владелец строки, владелец игры и `gm` видят actual целиком: для них `section_visibility` не фильтр. Полный лист NPC у `GameNpcHttp::allows` видят только владелец игры и `gm`, не `game.edit_all` и не `game.view_all`. Остальной участник с видимой карточкой видит лист с вырезанными секциями. Кода нет в списке — этих ключей нет в JSON. Карта — чёрный список по ключам `CharacterSectionMask`, не вызов этого класса. `characteristics` — пути NPC плюс `sheet.osSurchargeTotal`. `abilities` — пути NPC без `sheet.racialAbilityCodes`. `race` — `choices.raceCode` и `sheet.racialAbilityCodes`. `shortDescription` — `choices.shortDescription`. `fullDescription` — `choices.fullDescription`. `inventory` и `resources` — пути NPC. Код `states` ключа в сборке не имеет и ничего не снимает. Класс `CharacterSectionMask` не вызывается: он белый список публичного листа и снимает `name`, `limits`, `ageYears`, `customRules`. Game его `Service/` не импортирует.

Каркас внутри `choices` и `sheet` остаётся при любой маске секций: `name`, `ageYears`, `limits`, `customRules`, `active`. Этих кодов среди восьми нет. `customRules` при этом текст и уходит тому, кто запросил лист. Мир и ревизия в этих двух объектах не лежат. У NPC они на корне `version`: `spaceId`, `spaceCode`, `rulesRevision`. Маска их не трогает, и элемент листа их копирует. У персонажа `CharacterRecord` даёт `spaceId` и `rulesRevision`; `spaceCode` на строке нет, и ответ его не выдумывает из игры. `owner_notes` и `visibility_fields` в ответ не входят. Строки персонажа читаются `IGameMemberships::getListByGame($gameId, null)`, не `get()`: `get()` для `left` возвращает строку и читает actual. `game.getCharacter` и список этот шаг не учат резать: snapshot и `sectionVisibility` там остаются как хранятся.

`ICharacters::get` при отсутствии строки бросает `CharacterNotFoundException`. Фасад ловит его и отдаёт `GAME_NOT_FOUND` на весь вызов, как `GameCharacterHttp::withReview`. Отдельный ключ при этом в `missing` не кладётся: строки персонажа уже нет.

**Боевой subset.** Отдельное чтение одного `battleId` текущей сессии. Состав — `findParticipants`. Ответ — те же краткие поля и полный лист только участников этого боя, с той же маской. NPC вне состава в этот ответ не попадают. Список NPC игры этот вызов не заменяет и при отсутствии боя не требуется. Нет сессии или бой не этой сессии — `GAME_NOT_FOUND`. Идентичность боя, версия и запись состава не меняются.

**Снимок сессии.** `game.startSession`, `game.stopSession` и `game.get` тело не расширяют. В них нет roster, `choices`, `sheet` и `approvedCharacterVersion`. Stop по-прежнему не принимает `targetStatus`.

**Чего шаг не делает.** Не пишет лист, snapshot, `section_visibility`, бой и сессию. Не вызывает порт G10. Не шлёт событие. Не вводит cursor, SSE, outbox и listener. Не считает удар, урон, DOT, каст и движение. Не открывает `1 → N` и сцену.

**Права.** Карточка скрыта — `GAME_NOT_FOUND` до чтения roster и листов. Карточка видна — roster и лист по ключам доступны участнику; чужая секция и NPC вне scope в тело не попадают. `game.edit` для чтения не требуется. Новый код прав не заводится.

## Что даёт этот заход

Фасад `IGameProjections` и действия `game.getRoster`, `game.getSheets`, `game.getBattleSheets`. Roster — краткие записи персонажей и доступных NPC. Лист — по названным ключам, уже без чужих секций. Боевой subset — участники одного `battleId`. Снимок сессии остаётся без полного roster.

## Что не закрыто

- Доставка (G15): SSE, outbox, listener EventManager, cursor и физическая схема. Этот план их не выбирает.
- Вырезание секций в уже существующем `game.getCharacter` и в `approvedCharacterVersion`.
- `1 → N`, `N → 1`, DOT, каст, движение, battleground.
- Запись листа, боя и сессии.
- Vue поверх этого PHP.

## Точки кода G1–G13

Старые планы не переписываются. Character не импортирует Game. `GameNpcView`, `GameCharacterViewAssembler`, `GameBattles`, `GameSessions` и `game.get` не меняются: снимок сессии не должен начать возить листы, идентичность боя не переписывается.

- `GameNpcVisibility`. В `characteristics` добавляется путь `sheet.osSurchargeTotal`. Без этого скрытые характеристики оставляют доплату ОС и в `game.getNpc`, и в листе по ключу. `racialAbilityCodes` остаётся на `abilities`. Остальные пути и scope не меняются.

- `GameNpcRepository`. Публичное чтение строк одной игры: id, имя, `actualVersion`, объект `visibility`, документ `version`. Без списка фасад не соберёт краткий roster NPC и не отсечёт scope до ответа. `getById`, `add`, `save` и `replaceVersion` не меняются.
- `ICharacters::get` вызывается как есть. Отдельного метода «только имя» в Character нет, и этот шаг его не заводит. Roster из записи берёт имя и `actualVersion` и в JSON документ не кладёт.

Остальной код G1–G13 не меняется.

## Модуль

Чтение в `Roleplay/Game`, `Interface/Service/IGameProjections`. Сборщик — `GameProjectionPortFactory` с `create` и `createHttp`. Публичных `create*` у `GamePortFactory` не прибавляется. `events` остаётся `[]`. `module.config.php` регистрирует порт и три action. `GameContainer` список портов не хранит.

**DAG:** Game → Character через уже существующий `ICharacters`. Character Game не импортирует. Новый порт Character не появляется: маска — код Game, поверх уже прочитанного документа.

Маска строки персонажа — класс в `Service/` Game. Общие секции он не сводит к одному вызову `GameNpcVisibility::mask`: у персонажа `racialAbilityCodes` снимается с `race`, а не с `abilities`. Пути inventory, resources и общий список characteristics берутся те же, плюс `sheet.osSurchargeTotal`. Фасад в локатор не ходит. Конструктор фасада, не больше шести, как у `GameStrikes`: шлюз, `IGames`, `GameCardAccess`, `ICharacters`, `IGameMemberships`, эта маска. `GameNpcRepository`, `GameBattleRepository` и `GameSessionRepository` фасад создаёт сам из шлюза. `GameNpcs` в конструктор не входит: списка строк у него нет. Записи нет, транзакция не открывается.

Ошибки: `GAME_NOT_FOUND`, `GAME_INVALID`, `AUTH_REQUIRED`, `INVALID_PARAMS`. `AUTH_DENIED` этому чтению не принадлежит: скрытая карточка — `GAME_NOT_FOUND`, как у `game.getNpc`. `GAME_CONFLICT` нет: версии не сверяются и не пишутся.

| Источник | Код |
|---|---|
| нет актора | `AUTH_REQUIRED` |
| карточка скрыта; бой не этой сессии; сессии нет на боевом чтении; `ICharacters::get` не нашёл строку | `GAME_NOT_FOUND` |
| повтор ключа в одном теле | `GAME_INVALID` |
| пустые ключи; больше 32 ключей; чужой `type`; лишний ключ тела; `battleId` вместе со списком ключей | `INVALID_PARAMS` |

Ключ без строки, NPC вне scope и персонаж `left` в `missing`. Это не ошибка пачки.

## Фасад

`IGameProjections`. Не метод `IGameNpcs`, не метод `IGameMemberships` и не метод `IGameBattles`.

- `roster(int $gameId, int $actorUserId, bool $viewAll): array`
- `sheets(int $gameId, int $actorUserId, bool $viewAll, array $keys): array`
- `battleSheets(int $gameId, int $actorUserId, bool $viewAll, int $battleId): array`

`keys` — список `{ type, id }`.

Возврат roster — `{ characters, npcs }`. Элемент персонажа — `{ type: character, id, name, status, actualVersion }`. Элемент NPC — `{ type: npc, id, name, actualVersion }`.

Возврат листов — `{ sheets, missing }`. Элемент `sheets` — `{ type, id, name, actualVersion, spaceId, rulesRevision, choices, sheet }` после маски. У NPC ещё `spaceCode` с корня `version`. У персонажа `spaceCode` нет. `choices` и `sheet` NPC — поля уже лежащего `version`, не второй документ. `missing` — список тех же `{ type, id }`, листа которых нет.

Возврат боевого subset — `{ battleId, characters, npcs, sheets, missing }`. `characters` и `npcs` — краткие записи только состава. `sheets` — их листы после маски. Участник, которого маска scope скрыла целиком, попадает в `missing`, не в `sheets`.

## HTTP

Свои DTO, `csrf` true. В `handle` только вызов сценария. Тело не принимает `choices`, `sheet`, урон и `projectionLevel`.

| Action | Кто | Тело | Успех |
|---|---|---|---|
| `game.getRoster` | карточка видна | `{ gameId }` | `{ characters, npcs }` без листов |
| `game.getSheets` | карточка видна | `{ gameId, keys }` | `{ sheets, missing }` |
| `game.getBattleSheets` | карточка видна | `{ gameId, battleId }` | `{ battleId, characters, npcs, sheets, missing }` |

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `fetchNpcs(gameId)` без списка id возвращает полный `GameNpc` на каждого NPC игры одним вызовом. В PHP такого ответа нет.
- `GameChatTab` на `sync` ставит `projectionLevel: 'full'` на все ключи персонажей и NPC, когда сессия не запущена. Полный лист в PHP только у названных ключей или у состава одного боя.
- `visibility` NPC в моке — массив `{ audience, sections }`, включая `shortDescription` и `fullDescription`. В PHP по-прежнему один объект `scope` / `userIds` / четыре кода. Описания NPC этот шаг не начинает резать.
- `GameStateSnapshot` мока и SSE-sync. В ответы сессии и в эти три action снимок потока не кладётся.

## Тесты

Suite `game`. Suite `character` не расширяется: `ICharacters` не меняется.

Mysql roster. Два персонажа и два NPC. Ответ содержит имена и версии и не содержит `choices`, `sheet`, `version`, `approvedCharacterVersion`. NPC со scope `gm` отсутствует у `player` и есть у ведущего. Персонаж `left` отсутствует. Карточка скрыта — `GAME_NOT_FOUND`.

Mysql листа. Два ключа возвращают два документа. Третий NPC игры в этом JSON отсутствует. Секция вне списка отсутствует у чужого участника и присутствует у владельца строки и у `gm`. Скрытые характеристики персонажа не содержат `sheet.osSurchargeTotal`. Скрытая раса не содержит `sheet.racialAbilityCodes`, открытые способности его не возвращают. У NPC тот же ключ снимается вместе со способностями, не с расой. `game.getNpc` при скрытых характеристиках тоже не содержит `osSurchargeTotal`. Каркас `name`, `ageYears`, `limits`, `customRules` у чужого участника остаётся. У персонажа в элементе есть `spaceId` и `rulesRevision` и нет `spaceCode`. У NPC есть и `spaceCode`. `owner_notes` нет. Ключ вне игры — в `missing`, соседний лист на месте. Пустые `keys` и 33 ключа — `INVALID_PARAMS`. Повтор ключа — `GAME_INVALID`.

Mysql боя. Участники одного `battleId` приходят краткими записями и листами. NPC этой игры вне состава в ответе нет. Второй бой той же сессии не подмешивается. Бой без сессии — `GAME_NOT_FOUND`. `game.get` после этого чтения не содержит roster и листов. `targetStatus` на stop — `INVALID_PARAMS`.

Mysql сессии без стола. `game.getRoster` и `game.getSheets` при отсутствии сессии работают. `game.getBattleSheets` без сессии — `GAME_NOT_FOUND`.

## Todo

- [x] **npc-list** — `GameNpcRepository` читает строки одной игры. Запись NPC не меняется.
- [x] **mask** — `sheet.osSurchargeTotal` в `GameNpcVisibility`. Маска строки персонажа: карта секций Character чёрным списком, `racialAbilityCodes` на `race`. Код `states` путь не добавляет. `game.getCharacter` не режется.
- [x] **facade** — roster без листа, лист по ключам, боевой subset по `findParticipants`.
- [x] **http** — три action. Нет ответа «все листы NPC» и нет `projectionLevel`.
- [x] **gates** — phpunit. Roster без полного листа. Чужая секция не в ответе. `game.get` без полного roster.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| чтение | Game `Interface/Service/` `Service/` | roster, ключи, боевой subset |
| маска персонажа | Game `Service/` | `section_visibility` до JSON |
| список NPC | Game `Repository/GameNpcRepository` | строки одной игры |
| сборка | Game `Service/GameProjectionPortFactory` | `create` и `createHttp` |
| HTTP | Game `Action/` | три действия |

## Acceptance G14

- Roster отдаёт краткие записи и не содержит полный лист.
- Полный лист возвращается по названным ключам. Пачки листов всех NPC одним ответом нет.
- Видимость применена до ответа. NPC режется тем же правилом, что `game.getNpc`. Чужая секция строки персонажа в новый ответ не входит.
- Боевой subset читает состав уже существующего `battleId` и идентичность боя не меняет. Список NPC от боя не зависит.
- Снимок сессии и `game.get` остаются без полного roster и листов.
- Доставка не спроектирована. Character не импортирует Game.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G14; [`game-system.md`](game-system.md); [`game-plan-06.md`](game-plan-06.md); [`game-plan-07.md`](game-plan-07.md); [`game-plan-12.md`](game-plan-12.md); [`game-plan-05.md`](game-plan-05.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
