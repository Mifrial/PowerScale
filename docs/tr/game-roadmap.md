# Нарезка Roleplay/Game

**Статус:** план, 2026-10-03. Канон — [`game-system.md`](game-system.md). Границы — [`architecture.md`](architecture.md), [`cross-domain.md`](cross-domain.md). Стык листа — [`character-system.md`](character-system.md), [`character-roadmap.md`](character-roadmap.md) (C8, C9). Порядок стыка, не замена канона Game — [`../specs/character-actual-session-source-roadmap.md`](../specs/character-actual-session-source-roadmap.md). Сцена — [`battleground-system.md`](battleground-system.md), в эту линию не входит. PHP — [`php-coding-standards.md`](php-coding-standards.md).

Цель линии: серверный модуль **`Roleplay/Game`**. Каркас — игра, люди, membership персонажа и предикаты модерации. Бой, сцена, каст и runtime листа вне create этим каркасом не закрываются.

Фактура на 2026-10-03: каталога `www/mifrial/modules/Roleplay/Game` нет. Character C0–C7 сделаны; Character не импортирует Game. `character.migrate` не применяет потолки игры и бонусы GM. Vue `draft-front_1.2ds/.../Roleplay/Game` — эскиз транспорта и моков, не схема хранения.

## Кто что хранит

```text
RuleSpace: (spaceId, revision) игры
Character: actualCharacter, character.migrate
Game: игра и её стартовые потолки ОС/ОЛ/ОР, moneyLimit,
      участник-пользователь (их у роли несколько персонажей),
      строка персонажа в игре: approvedCharacterVersion и бонус этого персонажа,
      session/battle state (бой — не этот каркас)
```

Game читает строку actual через порт Character (`ICharacters::get`), не через HTTP `character.get` и не через `CharacterSheetAccess`. Публичная видимость листа GM не заменяет: модератор видит лист membership, который ему можно модерировать, даже если лист не публичный. Запись в `character` этим портом Game не делает. Character не начинает знать об игре.

Две разные строки. Участник — `(gameId, userId)`: роль и права человека. Строка персонажа — `(gameId, characterId)`: заявка, snapshot, бонус этого персонажа. Один участник может иметь в игре несколько персонажей. Уникальность «не больше одной игры» — у `characterId`, не у `userId`.

Стартовый потолок ОС/ОЛ/ОР лежит на игре. Бонус ОС/ОЛ/ОР лежит на строке персонажа в этой игре, не на участнике и не на строке игры. Лимит персонажа в игре считает Game: стартовый потолок игры плюс бонус этого персонажа. `moneyLimit` — потолок игры без бонуса персонажа в текущем эскизе; отдельный денежный бонус не выдумывать. `null` у потолка игры значит, что потолка нет: бонус персонажа сам по себе потолок не создаёт. В `character.migrate` ни потолок, ни бонус не входят. Смена `rulesRevision` игры лист не мигрирует: владелец вызывает `character.migrate`; после успеха строка персонажа снова требует модерации.

`approvedCharacterVersion` — неизменяемая копия листа на момент approve. Это не история `CharacterVersion`. `actualCharacter` — единственный сохранённый лист. Overlay не хранит второй полный лист.

Один персонаж не более чем в одной игре, пока статус не `left`. Инвариант есть в [`character-system.md`](character-system.md) и в комментарии `GameCharacterMembership`. Исключение `left` — часть того же инварианта, не вторая модель.

## Главный критерий каркаса

Каркас `IMPLEMENTED` только если backend:

1. создаёт игру в выбранных `(spaceId, rulesRevision)` и не принимает derived листа с клиента;
2. держит статусы `draft → recruiting → in_process → paused → completed`; `visibility` и `join_policy` независимы от статуса;
3. пока запущена текущая сессия, не меняет `spaceId`, `spaceCode`, `rulesRevision`; `completed` read-only;
4. `game.update` сессию не запускает и не останавливает;
5. хранит строку персонажа в игре со статусом `submitted | active | left`, immutable `approvedCharacterVersion` и `membershipRevision`;
6. запрещает второе не-`left` участие того же `characterId`; одного `userId` это не ограничивает;
7. модерирует по `approved == null` или semantic diff к actual; `returned` хранится, `clean` и `changes_pending` вычисляются из diff; reject — только `submitted`;
8. после смены ревизии игры сам лист не переписывает и блокирует допуск, пока владелец не пройдёт `character.migrate` и модерацию заново;
9. не пишет полный лист в overlay и не зовёт Character так, чтобы Character импортировал Game.

До этого любая строка в MySQL — `BACKEND_OPEN`.

## Todo

План-файл пишем, когда шаг начинается.

### G0. Граница каркаса — этот документ

Отдельный `game-plan-00` не нужен: границы ниже. Физические колонки и точные тела HTTP — в плане выбранного шага, не в этом файле.

### G1. Каркас модуля и строка игры — `TODO`

Lazy-модуль `Roleplay/Game`. Строка игры: имя, описания, статус, visibility, join policy, владелец, `spaceId`, `spaceCode`, `rulesRevision`, потолки `osPointsLimit`, `olPointsLimit`, `orPointsLimit`, `moneyLimit` (`null` — потолок не задан). Бонусов GM на этой строке нет. Ревизия — через RuleSpace, не «latest» и не Versioning.

HTTP: `game.create`, `game.get`, `game.getList`, `game.update`. `game.update` сессию не запускает и не останавливает и не принимает признак сессии. Признак «текущая сессия запущена» в ответе до G5 всегда ложен: отдельной булевой колонки на строке игры нет, признак истинен ровно когда есть текущая сессия. Имя признака — `sessionRunning`, не значение статуса и не `in_process`.

`gameChatId` / `discussionChatId` в ответе списка — представление. Создание чатов типов `game` и `game_discussion` этим шагом не делается: в PHP Chat тип `game` встречается в тестах и не является host-типом. Чаты — `OPEN`, пока нет шага Chat.

### G2. Участники-люди и права — `TODO`

Зависимость: G1.

Строки участника `(gameId, userId)` и роль `owner | gm | player`. Это человек, не персонаж: бонусов ОС/ОЛ/ОР здесь нет. Роль `owner` даёт `game.edit`, `game.moderate`, `game.manage`; `gm` даёт `game.edit`, `game.moderate`; `player` из роли ничего не добавляет. Запись в `game_member_permissions` для этих трёх ключей не дублирует роль ([`auth-system.md`](auth-system.md)). Один пользователь может потом подать несколько своих персонажей (G3).

HTTP: добавить / изменить / снять участника. Глобальные `game.create`, `game.view_all`, `game.edit_all` — проверка уже описанных ключей, не новый каталог прав.

Приглашения и заявки на вступление — `OPEN` (эскиз `game.getInvitations` и соседние методы). Каркас живёт без них: владельца создаёт G1.

### G3. Membership персонажа — `TODO`

Зависимость: G1, G2. Это серверная сторона гейта «C8 без Game».

Таблица строки персонажа вне Character и вне таблицы участника. Поля каркаса: `gameId`, `characterId`, статус `submitted | active | left`, `approvedCharacterVersion` (копия или `null`), `membershipRevision`, `reviewState` (`clean | changes_pending | returned`), `returnedAt`, `returnReason`, бонус этого персонажа (`osBonus`, `orBonus`, `olBonus`), видимость секций в игре. `returned` пишется return-ом. `clean` и `changes_pending` — вывод diff, не второй источник. Уникальность: один `characterId` среди статусов не `left`. Несколько строк с одним `userId` владельца в одной игре допустимы. Лимит листа в этой игре — стартовый потолок игры плюс бонус строки; в таблицу `character` эта сумма не пишется.

Чтение actual для модерации — `ICharacters::get` после проверки права игры и строки membership. `CharacterSheetAccess` для этого обхода не используется и не ослабляется. Game не пишет таблицу `character`.

HTTP: подать персонажа (`submitted`), approve, return, reject, выйти (`left`), записать бонусы GM. Approve пишет immutable snapshot actual и не меняет actual. CAS: ожидаемые `actual_version` и `membershipRevision`; конфликт не меняет ни snapshot, ни actual. Reject удаляет только `submitted`. Return оставляет membership и пишет причину. Сообщение в обсуждение персонажа и `returnMessageId` — не этот шаг: чата `character_discussion` в PHP нет. Отмена process при return — когда появятся process, не в G3.

Создание персонажа «из игры» — обычный `character.create` плюс membership `submitted`. Лимиты игры в тело `character.create` не кладутся.

### G4. Смена ревизии и допуск — `TODO`

Зависимость: G3. Character C7 уже есть.

Смена `rulesRevision` разрешена, пока текущая сессия не запущена. Game не вызывает миграцию. Несовместимый персонаж не блокирует игру целиком: `canStartSession` для него ложен, пока actual не на ревизии игры и membership снова не approved.

После успешного `character.migrate` Game видит новый actual через порт и снова требует модерацию (snapshot больше не совпадает). Повторный approve — новый immutable snapshot.

Предикаты, без боевого состояния:

- `needsModeration` — нет snapshot или semantic diff;
- `canStartSession` — active membership, нет diff, ревизия игры совпадает с ревизией actual, нет `returned`, лист проходит validation, нет блокирующего repair;
- `isActiveSessionParticipant` — уже в текущей сессии; `changes_pending` его не выкидывает и не пускает в **следующую** сессию.

Repair в PHP Character сейчас не статус строки: `needs_fix` в каноне — результат проверки, не колонка. Пока отдельного repair-флага нет, блокер — непройденная validation и явный `returned`. Новый статус персонажа под repair не заводить.

Единый comparator — diff snapshot ↔ actual. Второй алгоритм сравнения не заводить. `isCharacterChanged` — только `hasChanges`.

Пока сессии нет, `isActiveSessionParticipant` всегда ложен. Предикат всё равно фиксируется здесь, чтобы G5 его не переопределял.

### G5. Сессия без боя — `TODO`

Зависимость: G4.

Одна текущая сессия игры: старт и `game.stopSession`. Старт не требует допуска всех `active`: персонаж с ложным `canStartSession` в сессию не входит, остальные могут. Старт и stop статус не меняют. Stop не принимает `targetStatus`, не ставит `in_process` или `completed` и не коммитит лист. `completed` — отдельный терминальный переход кампании, строка после него только для чтения. `in_process` — фаза кампании без живого стола. `paused` — заморозка кампании, не пауза сессии и не способ погасить стол.

Migrate активного участника этой сессии запрещён. Запрет не переносится внутрь правил листа. `character.migrate` остаётся действием Character. Game даёт порт «участник в сессии»; Character вызывает порт и не импортирует Game. Без порта текущий migrate о сессии не знает — это дыра до G5, не поведение G4.

Пока запущена текущая сессия, контекст правил неизменяем. Отдельного `gameRevision` нет.

Таблица сессии — идентификатор, статус, техническая версия. Battle, process, offer, initiative, effects — не эта таблица.

## Порядок

```text
G1 строка игры
  → G2 люди и роли
    → G3 membership персонажа
      → G4 ревизия и предикаты
        → G5 сессия без боя
```

Нельзя закрыть:

- G3 без порта Character и без запрета второго не-`left` membership;
- G4 без G3 и без уже существующего `character.migrate`;
- G5 без предикатов G4;
- C8 Character без G3–G4;
- C9 (decay, DOT, каст) как замену G5 или как обход C5.

## Гейты

**Строка игры (G1):** create/update не меняют чужой `spaceId`; `update` сессию не запускает и не останавливает; `completed` отвергает запись.

**Membership (G3):** второй не-`left` вход того же персонажа — отказ; approve не пишет `character`; reject `active` — отказ; snapshot не становится историей версий Character.

**Ревизия (G4):** смена ревизии не вызывает migrate и не портит actual; допуск ложен до migrate владельца и нового approve; бонусы GM и потолки игры не уходят в `character.migrate`.

**Сессия (G5):** stop не меняет статус и не принимает `targetStatus`; несовместимый персонаж не блокирует старт остальных; migrate активного участника отклоняется портом; лист после stop тот же.

## HTTP и таблицы

На каркасе появляются: строка игры, участники-люди, membership персонажа, строка сессии без боя. Actions: `game.create`, `game.get`, `game.getList`, `game.update`, участники, подача и модерация персонажа, `game.startSession`, `game.stopSession`.

`OPEN`, не выводить из моков:

- приглашения и join requests;
- чаты игры и сообщение return в обсуждение персонажа;
- личные заметки игры (`personalNotes`);
- NPC и `npc.version`;
- летопись и `GameTime`;
- loot, магазин, `EconomyOperation`;
- battle, process, offer, initiative, check, movement;
- read projections roster/batch;
- SSE, `game.sync`, outbox, EventManager after-commit;
- runtime mutation actual вне create/update/migrate Character (C9).

Legacy SQL в [`data-model.md`](data-model.md) (`games`, `game_members`, `game_rule_sets`, старый `game_characters`) — не физическая схема этого каркаса. Целевое membership там же помечено `implementation OPEN`. Колонки выбираются в плане шага.

## Дефекты эскиза

Не копировать в фасад:

- `game.stopSession` принимает `targetStatus`. Канон: stop статус не меняет и `targetStatus` не принимает; `completed` — отдельный терминальный переход.
- Рядом есть `game.stopStateSession`. На каркасе один stop — `game.stopSession`.
- `GameCharacterMembership.overlay: GameCombatOverlay` в эскизе исторически тащил лист. В PHP overlay каркаса нет: полный лист туда не пишется.
- Лимиты ОС/ОЛ/ОР и `moneyLimit` живут на игре (`GameDetail`), не во входе `character.create`. `osBonus` / `orBonus` / `olBonus` в эскизе лежат на `GameCharacterMembership` (строка персонажа), не на `GameMember` (участник-пользователь). На участника и на строку игры их не переносить.

## Не входит

Бой и `game.submitCombatCommand`. `game.startBattle` / `endBattle`. Battleground и `ISpatialResolver`. Каст, DOT, decay, инвентарь и экономика сессии. SSE, outbox, EventManager. История `CharacterVersion`. Импорт Game из Character.

Перед любым шагом боя спросить развилку, которую каркас не закрывает: R3-FE фиксирует не больше одного active battle в сессии, [`game-system.md`](game-system.md) и R7 — несколько независимых battles в одной текущей сессии. На G1–G5 выбора нет, таблицы боя нет.

NPC — отдельный шаг после каркаса, не G1. У NPC нет `approvedCharacterVersion`; лист — `npc.version`. Пока шага нет, сессия G5 знает только персонажей игроков.
