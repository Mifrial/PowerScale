# План Game 7 — секции видимости строки персонажа

**Статус:** запись секций в PHP сделана, 2026-10-04. `BACKEND_OPEN`. Suite `game` зелёный. Нарезка — [`game-roadmap.md`](game-roadmap.md) G7. Строка персонажа — [`game-plan-03.md`](game-plan-03.md). NPC — [`game-plan-06.md`](game-plan-06.md). Границы — [`game-system.md`](game-system.md), [`architecture.md`](architecture.md), индекс [`TR.md`](TR.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md).

Цель: отдельное действие пишет список кодов секций в уже существующую колонку `game_character.section_visibility`. Подача по-прежнему пишет `[]`. Запись не меняет лист персонажа, snapshot и публичную видимость Character. Это не `character.visibility_fields`, не `CharacterSheetAccess` и не объект видимости NPC из G6.

## Сверка G1–G6

Код строки персонажа уже хранит колонку. Отдельной записи нет.

- `game_character.section_visibility` — JSON, required, default `[]`. `GameCharacterRepository::insertSubmitted` пишет `[]`. `GameCharacterRecord` и `GameCharacterViewAssembler` уже отдают `sectionVisibility`.
- `saveApproved`, `saveReturned`, `saveLeft` и `saveBonus` эту колонку в `update` не передают. Значит approve, return, leave и бонус уже её не затирают. Эти методы не меняются.
- `GameCharacterMemberships` на потолке снифа: конструктор и девять методов. Одиннадцатый публичный метод туда не ставится. Зависимостей конструктора четыре.
- `GameCharacterHttp` — конструктор и восемь action. Девятый action по числу методов влез бы (потолок десять). Не влезает зависимость: в конструкторе уже шесть, это потолок. `assertModerator` остаётся приватным этого класса.
- `GameCharacterRepository` на потолке: конструктор и девять методов (`add`, `getByPair`, `getListByGame`, `findLiveId`, `saveApproved`, `saveReturned`, `deleteById`, `saveLeft`, `saveBonus`). Приватный `update` пишет произвольные колонки. Запись секций — одиннадцатый публичный метод этого класса и `phpcs:ignore` на `MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods` в строке `final class`. Отдельного репозитория нет.
- Публичная видимость Character — `visibility_fields`, `is_public`, `character_viewer`. Её пишет Character, не Game. `CharacterSheetAccess` G3 не вызывает. G7 тоже не вызывает.
- `game_npc.visibility` — объект `scope` (`all` / `gm` / `users`), `userIds` и четыре кода секций. Чтение NPC вырезает ключи до ответа. G7 эту таблицу, `GameNpcVisibility` и action NPC не читает и не пишет.

## Зафиксировано (модель)

На строке персонажа видимость в игре — JSON-список кодов `CharacterSheetSection`, как зафиксировал G3. Восемь кодов: `shortDescription`, `fullDescription`, `race`, `states`, `characteristics`, `resources`, `abilities`, `inventory`. Имя секции не является. Пустой список допустим: это то, что пишет подача.

Чужой код, не list, не строка и повтор кода — `INVALID_PARAMS`. Перед записью список уникален и отсортирован по строке, как `CharacterSheetSection::codes()`. Аудитории нет: ни `all`, ни `gm`, ни user id.

Это не фильтр ответа. `game.getCharacter` и список по-прежнему отдают хранимый список и snapshot как есть. Вырезание секций из листа до ответа — G14. Объект NPC (`scope`, `userIds`, четыре кода) на эту колонку не переносится. `shortDescription`, `fullDescription`, `race` и `states` для строки персонажа принимаются; у NPC G6 их по-прежнему нет.

`membershipRevision` это действие не увеличивает. Approve, return, leave и бонус остаются прежними CAS. Колонки `character` (`visibility_fields`, `is_public`, `choices`, `sheet`, `actual_version`) не пишутся. `approved_character_version` не пишется.

Кто пишет: актор с `game.moderate` этой игры или с `game.edit_all`. Тот же допуск, что `game.setCharacterBonus`. Владелец персонажа без этого права строку не открывает: `GAME_NOT_FOUND`. Статус `submitted` или `active`. `left` и игра `completed` — `GAME_INVALID`, колонка прежняя.

## Что даёт этот заход

`game.setCharacterSectionVisibility`. Одна запись колонки `section_visibility` на живой строке.

## Что не закрыто

- Применение списка к чтению листа и проекции (G14).
- Видимость NPC: scope и вырезание секций G6. Этот заход её не двигает.
- Публичная видимость Character и `CharacterSheetAccess`.
- Вступление (G8), летопись (G9), порт мутации actual (G10), экономика, бой, доставка.
- Vue поверх этого PHP.

## Точки кода G1–G6

Старые `game-plan-01.md`–`game-plan-06.md` не переписываются. Поведение подачи, модерации, бонуса, сессии и NPC не меняется.

- `GameCharacterRepository`. Новый публичный метод пишет только `section_visibility` через уже существующий приватный `update`. `insertSubmitted` по-прежнему кладёт `[]`. `saveApproved`, `saveReturned`, `saveLeft` и `saveBonus` не меняются: они колонку и так не передают. Sniff `MifrialCodingStandard.Metrics.ClassQuality.TooManyPublicMethods` вешается на `T_CLASS`. На строке `final class` — `phpcs:ignore` этого кода. Комментарий называет одиннадцатый метод: запись одной колонки той же строки. Отдельный репозиторий ту же таблицу не открывает. Глобального отключения нет.

`GameSchema`, `GameCharacterTable`, `GameCharacterMemberships`, `GameCharacterHttp`, `Games`, `GameSessions`, `CharacterMigration` и классы NPC не меняются. `GamePortFactory` уже собирает HTTP строки через `createCharacterHttp`; новый HTTP-класс получает свой `create`, как `createNpcHttp`. У фабрики семь публичных методов, конструктора нет.

## Модуль

Тот же `Roleplay/Game`. Новый сценарий в `Service/` Game, не метод `IGameMemberships` и не метод `IGames`. Ответ собирает `GameCharacterViewAssembler::detail` после `IGameMemberships::get`. У `detail` обязательны `reviewState`, `needsModeration`, `canStartSession` и `isActiveSessionParticipant`; `get` их уже ставит через `withReview`. Ответ совпадает с `game.getCharacter`, включая эти четыре поля. `events` остаётся `[]`. Suite `game`.

**DAG:** Game → SmartTable + User + RuleSpace + Character. Нового ребра нет. Character не импортирует Game. `Service/` Game `Service/` Character не импортирует. `CharacterSheetSection` — enum Character, уже доступный модулю Game. `CharacterSheetAccess`, `CharacterVisibilityRepository` и `ICharacters` на запись не вызываются. `get` membership по-прежнему читает actual только чтобы посчитать `reviewState`; в `character` он не пишет.

Ошибки прежние: `GAME_INVALID`, `GAME_NOT_FOUND`, `AUTH_REQUIRED`, `AUTH_DENIED`, `INVALID_PARAMS`. `CHARACTER_*` и `GAME_CONFLICT` этому действию не принадлежат: версии листа и `membershipRevision` оно не сверяет.

| Источник | Лист |
|---|---|
| нет игры; нет строки; нет `game.moderate` этой игры и нет `game.edit_all` | `GAME_NOT_FOUND` |
| игра `completed`; статус `left` | `GAME_INVALID` |
| тело не list; код вне восьми `CharacterSheetSection`; повтор кода; объект `{ audience, sections }` | `INVALID_PARAMS` |
| нет актора | `AUTH_REQUIRED` |

## Таблицы

Новой карты нет. `GameSchema` не получает седьмую зависимость.

Пишется одно поле уже существующей `game_character.section_visibility`. Snapshot, бонусы, статус, return и `membership_revision` в этом `update` отсутствуют.

## Фасад

Класс в `Service/` Game. Один публичный сценарий рядом с конструктором. Порт `IGameMemberships` не расширяется. В `GameCharacterHttp` метод не добавляется: седьмая зависимость конструктора не проходит порог.

Запись. Порядок проверок как у `setBonus`. Сначала актор: `requireActor`, `game.edit_all` либо `GamePermissionKeys::keysFor` с `game.moderate`; нет права — `GAME_NOT_FOUND`. Затем `IGames::get`: нет игры — `GAME_NOT_FOUND`, `completed` — `GAME_INVALID`, строка не читается. Затем `GameCharacterRepository::getByPair`: нет строки — `GAME_NOT_FOUND`; среди строк пары метод уже отдаёт живую, а если живой нет — последний `left`. Статус `left` — `GAME_INVALID`. `submitted` и `active` пишутся. Список нормализуется и пишется новым методом `GameCharacterRepository`. Успех — `IGameMemberships::get` и `GameCharacterViewAssembler::detail`. `membershipRevision`, snapshot, бонусы и return те же, что были до вызова. `ICharacters` на запись не вызывается. Чтение actual делает только уже существующий `get`, чтобы собрать `reviewState`. `game_npc` не читается.

`GameModuleSetup` уже отдаёт `GameCharacterTable`. Вторая регистрация карты не нужна.

## HTTP

Один action, свой DTO, `ports` и `routes` в `Game/module.config.php`, `csrf` true. Отдельный HTTP-класс, потому что в конструктор `GameCharacterHttp` седьмая зависимость не входит. Action вызывает этот класс, как `SetGameCharacterBonusAction` вызывает `GameCharacterHttp`.

`game.setCharacterSectionVisibility`.

Тело: `gameId`, `characterId`, `sectionVisibility` — список строк. Успех — плоский объект `game.getCharacter`. `approvedCharacterVersion` тот же объект или тот же `null`. `membershipRevision` то же число.

Дефекты эскиза `draft-front_1.2ds`, в фасад не копировать:

- `game.updateMembershipVisibility` с `SheetVisibility`: массив `{ audience, sections }`. Контракт — список из восьми кодов, без аудитории.
- Мок `updateMembershipVisibility` пишет `character.visibility`, то есть публичную видимость листа. Колонка игры при этом не меняется. Гейт шага — обратный: пишется только `section_visibility`.
- Диалог открывается владельцу персонажа и при `canManage`, и только для `active`. Сервер пускает `game.moderate` или `game.edit_all` и на `submitted`, и на `active`.
- `CharacterVisibilitySection` зовёт `character.updateVisibility`. Это публичная видимость Character, не строка игры.
- Четыре кода и `scope` NPC. На строку персонажа они не копируются.

## Тесты

Mysql записи. После submit список `[]`. Вызов с двумя кодами пишет нормализованный список. Повтор с `[]` возвращает пустой список. `membership_revision`, `approved_character_version`, бонусы и return те же. Строка `character`: `choices`, `sheet`, `actual_version`, `visibility_fields`, `is_public` те же. Затем approve и `setBonus`: список секций на месте, snapshot и бонус пишутся своими действиями.

Mysql отказа. `left` и `completed` — `GAME_INVALID`, список прежний. `player` и владелец персонажа без `game.moderate` — `GAME_NOT_FOUND`. `gm` пишет в чужой для владельца игре. `edit_all` пишет без роли. Чужой код, повтор, объект с `audience` — `INVALID_PARAMS`, колонка прежняя.

Mysql границ. `game_npc` не появляется и не меняется. `game.getNpc` не вызывается этим действием. Вызова `CharacterSheetAccess` нет.

Suite `game`. Suite `character` не расширяется: контракт видимости листа не меняется.

## Todo

- [x] **write** — публичный метод `GameCharacterRepository` пишет только `section_visibility` через `update`; `phpcs:ignore` на строке класса.
- [x] **action** — `game.setCharacterSectionVisibility` для `submitted` и `active`.
- [x] **gates** — phpunit `GameCharacterSectionMysqlTest`. Лист, snapshot и `visibility_fields` на месте. NPC не задет.

## Слои

| Тип | Папка | Задача |
|---|---|---|
| запись колонки | Game `Repository/` | только `section_visibility`, ignore на строке класса |
| сценарий | Game `Service/` | допуск модератора, нормализация восьми кодов |
| HTTP | Game `Service/` `Action/` | отдельный класс: у `GameCharacterHttp` шесть зависимостей |
| строка G3 | Game `Service/` | подача, approve, бонус без изменений |
| NPC G6 | Game `Service/` | без изменений |
| Character | — | лист и публичная видимость не пишутся |

## Acceptance G7

- Suite `game` зелёный.
- Колонка принимает пустой список и коды `CharacterSheetSection`. Чужой код не пишется.
- `membershipRevision`, snapshot и бонусы этим действием не меняются.
- `choices`, `sheet`, `actual_version`, `visibility_fields` и `is_public` строки `character` те же.
- `CharacterSheetAccess` не вызывается. Character не импортирует Game.
- Строка `game_npc` и её `visibility` не меняются.
- G8, G9, G10, G14 и G15 этим заходом не спроектированы.

## Документы захода

этот файл; [`game-roadmap.md`](game-roadmap.md) G7; [`game-plan-03.md`](game-plan-03.md); [`game-plan-06.md`](game-plan-06.md); [`game-system.md`](game-system.md); [`architecture.md`](architecture.md); [`TR.md`](TR.md); [`php-coding-standards.md`](php-coding-standards.md).
