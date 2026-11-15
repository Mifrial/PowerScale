# План M6 — активные заклинания

**Статус:** ~СДЕЛАНО~, 2026-11-11. Срезы 6.1–6.5 реализованы:
`GameSpellDurationRules`/`GameSpellSource`/`GameSpellPowerCap`, таблица
`game_active_spell` и репозиторий, создание из каста, поддержание/обрыв,
промпт на входе хода, потеря источника, чтение `game.getActiveSpells`,
real-ветки фронта. `php bin/setup.php` в dev выполнен, ручная проверка
пройдена, полный `bash tools/test-full.sh` зелёный: static 0,
backend 1632 (4 skip), frontend 2455.

**Реализация:** ~PHP~, ~VUE~. Код мока (`Dto/Spell/ActiveSpell`,
`ActiveSpellService`, `Mock/mockGameActiveSpells`, sustain-ветка
`InitiativeTrack`, `SpellSustainDialog`, `persistGeneratorIfNeeded` в
`SpellCastDialog`) — ~НЕ ОСНОВА~ как контракт; берём только семантику
поведения.

**Опираться на:** ~PHP~ (`Rule/Dto/Spec/Ability/SpellDuration`,
`Rule/Spec/SpellSpecs`, `Rule/Dto/Spec/Ability/SpellSpec`,
`Game/Service/GameSpellCast`, `GameSpellCastFacts`, `GameSpellCastWrite`,
`GameSpellCastChat`, `GameSpellCastView`, `GameSpellTouchFinish`,
`GameSpellSourceKey`, `GameActionPerform`, `GameActionFloor`,
`GameTurnEnd`, `GameTurnScenario`, `GameTurnEnvelope`, `GameTurnAccess`,
`GameBattleRepository`, `GameBattleMutator`, `GameBattleCleanup`,
`GameStrikeSheetWrites`), ~VUE~ (`GameApi`, `IGameApi`,
`GamePhpBattleContext`, `SpellSustainDialog`, `InitiativeTrack`,
`Detail/CombatCardPanel`, `SpellCastDialog`), ~МОК~ (`ActiveSpellService` —
только семантика «источник занят / тик на входе хода / первая мощь =
мощь сотворения»), документы [`spell-real-roadmap.md`](spell-real-roadmap.md)
(M6), [`spell-plan-08-runtime-expansion.md`](spell-plan-08-runtime-expansion.md),
[`spell-plan-09-magic-runtime.md`](spell-plan-09-magic-runtime.md),
[`spell-real-plan-04-saturation.md`](spell-real-plan-04-saturation.md),
[`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md).

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M6.
**Связанные:** [`spell-real-plan-02-spell-upgrades.md`](spell-real-plan-02-spell-upgrades.md)
(M2, `appliedUpgradeCodes`), [`spell-real-plan-04-saturation.md`](spell-real-plan-04-saturation.md)
(M4, отложенный каст и `resolve`), [`spell-real-plan-05-magic-deviation.md`](spell-real-plan-05-magic-deviation.md)
(M5, штраф к потолку мощи источника).

M5 закрыл провал каста. Успешный `sustained`-каст на сервере по-прежнему
не оставляет следа: «Генератор молний» и «Восприятие магии» существуют
только в моке. M6 вводит единственную сущность рантайма — **активное
заклинание** — и полный цикл: создать из завершённого каста, поддержать
на своём ходу, сменить мощь, прекратить, потерять источник.

## 1. Границы

Входят:

- хранение активных заклинаний в отдельной таблице `game_active_spell`;
- создание записи из **завершённого** каста: заклинание, путь, источник,
  мощь, параметры, улучшения и версия листа берутся из серверной подготовки,
  а не из тела запроса;
- длительности читаются из формы `spell.duration` карточки: `instant` строку
  не создаёт, `sustained` создаёт; `lingering`/`refreshable` пока
  отвергаются как неподдержанные;
- запрет второго активного заклинания на паре `(кастер, source_key)`;
- команды `game.sustainSpell` и `game.dropSpell` с проверкой «сейчас твой
  ход», replay и версиями;
- поддержание: смена мощи, трата `duration.action_cost` (если карточка её
  объявила), потолок мощи по характеристике пути, штраф отклонения (M5);
- `game.endTurn` на входе хода нового участника: авто-drop недоступных
  источников, список заклинаний для вопроса «продолжить / оборвать»;
- чат: создание, продолжение, прекращение, потеря источника;
- чтение `game.getActiveSpells` для карточки боя и диалога каста;
- real-фронт: промпт на входе хода, блок «Поддержание» в карточке боя,
  занятость источника в диалоге каста;
- тесты PHP (unit + MySQL), vitest, документация и карты.

Не входят:

- стабильность, дестабилизация и drop по силе (M7);
- заряды и «Генератор» как источник зарядов (M8);
- «одно активное волшебство» Псионика (M11); здесь только «один источник —
  одно заклинание»;
- мана, кристалл, накопители (M10);
- `lingering`/`refreshable` и `duration.limit` как авто-истечение (M15);
- battleground, сетка, дистанция;
- новые типы правил и коды карточек в PHP;
- перенос расчёта на клиент: длительности, траты и потолки считает сервер.

## 2. Что уже есть

| Место | Что делает | Чего не делает |
| --- | --- | --- |
| `Rule/Dto/Spec/Ability/SpellDuration` | `type`, `limit/unit`, `actionCost`, `power` | потребителя в Game нет |
| `Rule/Spec/SpellSpecs` | разбирает `spell.duration`, нет блока — `instant` | — |
| `GameSpellCastFacts::prepare` | карточка, путь, параметры, мощь, траты, руки | не читает длительность и не проверяет занятость источника |
| `GameSpellCast`/`GameSpellTouchFinish` | завершённый каст (`finish`/`resolve`) | не создаёт рантайм-запись |
| `GameSpellCastView` | конверт ответа каста | нет блока активного заклинания |
| `GameTurnEnd` | конец хода, курсор, тики, чат | нет промпта поддержания |
| `GameStrikeSheetWrites`/`GameActionPerform` | CAS-запись листа и трат | — |
| `GameApi.getActiveSpells` | заглушка → `[]` | реального чтения нет |
| `ActiveSpell.ts`/`ActiveSpellService` | мок-модель и правила | не контракт сервера |

В dev-контенте только два `sustained`-заклинания: «Генератор молний»
(`duration.power` = parameter `x`, без `action_cost`) и «Восприятие магии»
(`duration.power` = `{4|-1}`). Карточек с `lingering`/`refreshable` нет.

## 3. Решения

### 3.1. Хранение: таблица, JSON боя или лист

Активное заклинание — **не тип правила** и **не состояние листа**. Это
последствие уже завершённого действия: кто, чем, от какого источника и с
какой мощью держит эффект. По роли это сосед `game_process` (сессия
процесса) и `game_action` (открытое действие), а не `CharacterStateValue`.

| Вариант | Плюсы | Минусы |
| --- | --- | --- |
| **A. Таблица `game_active_spell`** | Первоклассные строки: выборка по бою/кастеру/источнику, CAS на строку, FK на игру/сессию/бой, явное удаление, простые MySQL-тесты версий и replay. Совпадает с тем, как Game уже хранит рантайм (`game_process`, `game_action`, `game_battle`). | Новая таблица, репозиторий, схема, Setup-шаг; dev-БД нужен `php bin/setup.php`. |
| **B. JSON-колонка в `game_battle`** | Нет новой таблицы; как `pending_spell_cast`. | Список, а не один объект: нет версии строки, конкурирующие sustain/drop теряются; любое изменение требует CAS всей строки боя и поднимает `state_version` всем клиентам; выборки по кастеру/источнику — перебор JSON; форма не описана Table-определением. `pending_spell_cast` — транзитный одиночный объект, прецедент не масштабируется. |
| **C. Состояние на листе** | «Это побочное действие» — рядом с состояниями. | Лист пересчитывается из `choices` и версионируется; рантайм-запись либо затрётся, либо попадёт в `choices`; заклинание принадлежит бою, а не листу; владелец может править лист вне боя; мок прямо говорит «не state листа». Состояние Rule — контент с агрегацией и затуханием, а не рантайм-сущность. |

**Решение: A.** Это не «таблица под карточку»: строки не содержат правил,
только рантайм-ссылку на карточку (`spell_code`) и числа каста. Новый тип
правила не вводится; поведение по-прежнему выбирается по форме `spec`.

### 3.2. Какие версии зачем

Три версии — три разные причины:

- **`game_battle.state_version`** — версия состояния боя. Растёт на каждом
  изменении боя (ход, отложенный каст, состав). Клиент присылает
  `expectedBattleVersion`; несовпадение — кто-то уже изменил бой, конфликт.
  Нужна, чтобы поддержание не прошло «в чужой ход» и не поверх чужой
  перестановки.
- **Версия листа (`caster_sheet_version`)** — версия пересчитанного листа
  персонажа/NPC. Растёт при тратах ОД, состояниях, инвентаре. При поддержании
  с тратой ОД клиент присылает `expectedSheetVersion`; сервер пишет лист
  только при совпадении, иначе двойная трата со старого экрана. При
  создании записи версия берётся из **серверного снимка после записи каста**,
  не из тела запроса. Это снимок происхождения, а не вечный замок: позднее
  ранение или трата не отменяют заклинание.
- **`game_active_spell.state_version`** — версия самой строки эффекта. Растёт
  при смене мощи. Клиент присылает `expectedSpellVersion`; несовпадение —
  другое окно уже сменило мощь или оборвало эффект. Почему не версия боя:
  тик поддержания не меняет бой; привязка к бою превращала бы каждую смену
  мощи в боевое событие и конфликт всего боя.

### 3.3. Поддержание: начало хода, `endTurn`, отдельные команды

Решение пользователя: вопрос задаётся **в начале хода**, чат о продолжении
и обрыве полезен, спрашивать обязательно, через `endTurn` можно.

- `game.endTurn` после сдвига курсора:
  1. для активных заклинаний **нового текущего** проверяет источник;
     недоступен — удаляет строку и пишет чат «источник недоступен, эффект
     спал»;
  2. остальные отдаёт в ответе `sustain` — список для диалога;
  3. в ход создания эффекта вопрос не задаётся (`started_round` +
     `started_participant`);
  4. если у нового текущего несколько заклинаний — список, фронт спрашивает
     по очереди.
- Ответ на вопрос — **отдельные команды** `game.sustainSpell` и
  `game.dropSpell`, а не фаза `game.runAction`: поддержание не объявленное
  действие, броска нет, ОД на тик в контенте нет. Команды идут через
  `GameTurnEnvelope` + `GameTurnScenario`: replay, идемпотентность и
  проверка «сейчас твой ход» уже есть.
- **Серверного гейта на действия в M6 нет.** Диалог `persistent`, закрыть
  без ответа нельзя. Если клиент всё же обойдёт вопрос, заклинание остаётся
  с прежней мощью и вопрос повторится в следующем ходу. Жёсткое состояние
  `pending_sustain` — отдельное решение для M7 вместе со стабильностью;
  иначе получаем второй дедлок, как открытое действие в M4.
- Трата ОД: `duration.action_cost` из формы карточки. Нет поля или `null` —
  0 (у «Генератора» и «Восприятия магии» её нет). Значение размерное —
  приводится `toNumber()`. Не хватает ОД — отказ, игрок может оборвать.
- Мощь: первая = мощь сотворения; дальше игрок присылает новую, сервер
  проверяет потолок характеристики пути (со штрафом отклонения M5),
  `CharacteristicNumber` и CAS строки. Меньше — можно, выше потолка — отказ.

### 3.4. Один источник — одно заклинание

**Запрет в M6 на сервере.** Перед кастом (обычный и касание) и при создании:
если у пары `(battle, caster_kind, caster_id, source_key)` уже есть активная
строка — отказ до списания ОД и броска. Проверка в сервисе, не unique-индекс:
M11 может разрешить несколько заклинаний на источник у Псионика.
Ограничение «одно активное заклинание на кастера» — M11, здесь его нет.

### 3.5. Длительности

**Только `sustained`.** `instant` — строки нет. `lingering`/`refreshable`
сервер отвергает на `prepare` (`GameInvalidException`), чтобы карточка не
импортировалась молча и каст не проходил «как instant». `duration.limit`
читается и доступен в ответе, но авто-истечение — M15. Обновление
`refreshable` за `action_cost` — M15 вместе с карточками.

### 3.6. Где показывать

- `InitiativeTrack`: промпт на входе хода из `sustain` ответа `endTurn`;
  диалог `SpellSustainDialog` (persistent), продолжение/обрыв — серверные
  команды.
- `Detail/CombatCardPanel`: блок «Поддержание» — имя, мощь, источник, кнопка
  «Оборвать» → `dropSpell`; чат пишет сервер.
- `SpellCastDialog`: занятые `source_key` этого кастера недоступны (данные
  из `getActiveSpells`); в real-режиме фронт больше ничего не создаёт.
- Мок-путь остаётся как есть; real-ветки отдельные, мок-сервисы в тестах.

## 4. Модель и API

### 4.1. Таблица `game_active_spell`

| Колонка | Тип | Смысл |
| --- | --- | --- |
| `id` | IdField | Строка |
| `game_id` | Reference GameTable | Игра |
| `session_id` | Reference GameSessionTable | Сессия |
| `battle_id` | Reference GameBattleTable | Бой |
| `caster_kind` | string(16) | `character` или `npc` |
| `caster_id` | int | Id кастера |
| `spell_code` | string(255) | Код карточки заклинания |
| `path_code` | string(255) | Код пути |
| `source_key` | string(255) | `inventory:9`, `grant:x`, `characteristic:magic-power` |
| `duration_type` | string(32) | В M6 только `sustained` |
| `used_power` | json | `{base,size}` мощи сотворения |
| `sustain_power` | json | `{base,size}` текущей мощи поддержания |
| `parameter_values` | json | Активационные параметры каста |
| `applied_upgrade_codes` | json | Применённые улучшения |
| `started_round` | int | Раунд создания |
| `started_participant` | string(64) | `character:1` — пропуск вопроса в ход создания |
| `caster_sheet_version` | int | Версия листа после каста (снимок сервера) |
| `state_version` | int | CAS строки, старт 1 |

Индексы: `(battle_id, caster_kind, caster_id)`, `(battle_id, source_key)`.
Unique нет — правило «один источник» держит сервис, чтобы M11 мог его
расширить.

`GameActiveSpellRepository`: `add`, `findById`, `findForBattle`,
`findForSession`, `updateSustainPower` (CAS), `deleteWithVersion`,
`deleteForBattle`, `deleteForSession`, `deleteForCaster`. Ошибки строки
маппятся в `GameInvalidException` / `GameNotFoundException` /
`GameBattleConflictException` как в соседних репозиториях.

`GameActiveSpellSchema` + `GameModuleSetup` + `GameMysqlTables::drop`
(новая таблица удаляется до `GameBattleTable`).

Очистка: `GameBattleMutator::end`, `GameBattleCleanup::deleteWithSession`
(до удаления боя), смена состава — удалить строки выбывших участников.

### 4.2. DTO и чтение

- `GameActiveSpellRecord` — `fromNormalized`, геттеры; JSON-вид отдельно.
- `GameActiveSpellView` — JSON: `id` (строка, непрозрачный ключ),
  `gameId`, `casterKey`, `spellCode`, `pathCode`, `sourceKey`,
  `durationType`, `usedPower`, `sustainPower`, `parameterValues`,
  `appliedUpgradeCodes`, `startedRound`, `startedParticipant`,
  `casterSheetVersion`, `version`.
- `game.getActiveSpells` (GET, `csrf: false`) → `gameId`; видимость игры
  как у прочих чтений; строки текущей сессии.
- Попутно `ActiveSpell.ts`: `version: number`, `casterSheetVersion: number`,
  `stability` — опционально/`null` до M7. `id` остаётся строкой (контракт
  уже строковый, мок не переделываем).

### 4.3. Создание из завершённого каста

Точки: `GameSpellCast::finish` (обычный каст и `resolve` отложенного) и
`GameSpellTouchFinish::finish` (касание). Условия:

- `castOk === true`;
- для касания — не молоко (`damage.milk !== true`); иначе эффекта нет;
- `duration.type` — `sustained`; `instant` — выход;
- источник свободен (проверка уже была до каста, здесь — повторно в
  транзакции);
- параметры: `prepared->spellCode/pathCode/sourceKey`, `usedPower`,
  `parameters`, `upgrades->getApplied()` коды;
- `sustain_power = used_power` (первый ход — мощь сотворения);
- `started_round = battle->getRound()`, `started_participant = caster key`;
- `caster_sheet_version` — версия после записи каста (`versions['casterVersion']`
  / `casterVersion` касания), не из тела;
- `state_version = 1`.

Сервис `GameActiveSpellCreate` возвращает запись/null; вставка в той же
транзакции, что запись листа и бой. Чат «поддерживает» постится **после**
строки каста (в `respond` и в `GameSpellTouchFinish`), чтобы порядок в чате
был «сотворяет → поддерживает». В ответ каста добавляется
`activeSpell` (JSON или `null`), в результат касания — тоже.

### 4.4. Команда поддержания

`game.sustainSpell` (POST, `csrf: true`), тело:
`gameId`, `battleId`, `idempotencyKey`, `expectedBattleVersion`,
`expectedSheetVersion`, `spellId`, `sustainPower {base,size}`,
`expectedSpellVersion`.

Шаги:

1. `GameTurnEnvelope` (`kind: sustain_spell`) — видимость, сессия, replay,
   сигнал доставки.
2. `GameTurnScenario::prepare` — бой, порядок, текущий, право актора.
3. Разбор тела: положительные id/версии, размерная пара; иначе
   `INVALID_PARAMS` (HTTP) / `GameInvalidException` (домен).
4. Строка по `spellId` и бою; чужая/нет — `GameNotFoundException`; версия
   строки чужая — `GameBattleConflictException`; кастер не текущий (для
   игрока) — `GameInvalidException`; `GameTurnAccess::isWriter`
   (владелец/gm/edit_all) может оборвать чужое заклинание.
5. Живая карточка из среза: `duration_type`, `action_cost`, `duration.power`;
   неподдержанная длительность — отказ.
6. Источник недоступен — авто-drop (как 4.5), ответ `dropped`, ОД не тратятся.
7. `action_cost > 0`: `GameActionPerform::assertSpends` + `spend` с
   `expectedSheetVersion`; ресурс хода — `GameActionFloor::turnResourceCode`.
8. Потолок мощи: характеристика пути из листа, штраф отклонения M5; выше —
   `GameInvalidException`.
9. `updateSustainPower` с `expectedSpellVersion`; `state_version + 1`.
10. Чат: «продолжает поддерживать X» (+ `N ОД`, если тратил).
11. Ответ: `spell` (JSON), `battleId`, `version` (боя, не меняется),
    `sheetVersion` (если тратил).

Порядок «лист → строка» в одной транзакции: при конфликте строки лист
откатывается.

### 4.5. Прекращение

`game.dropSpell` (POST, `csrf: true`), тело: `gameId`, `battleId`,
`idempotencyKey`, `expectedBattleVersion`, `expectedSheetVersion`,
`spellId`, `expectedSpellVersion`.

Шаги 1–4 как выше; ОД нет; `deleteWithVersion`; чат «перестаёт поддерживать
X». Ответ `{dropped: true, battleId, version}`. Отдельный текст для
авто-drop: «источник недоступен, X спадает» (в `endTurn` и в `sustainSpell`).

### 4.6. Конец хода

`GameTurnEnd::end` после `persist` и до `announce->passed` зовёт
`GameActiveSpellTurnStart`:

- вход: игра, сохранённый бой, новый текущий, актор;
- для каждой строки нового текущего: источник доступен? нет — удалить +
  чат; да — в список;
- список: `id`, `casterKey`, `spellCode`, `sustainPower`,
  `defaultSustainPower` (разрешённый `duration.power` по сохранённым
  параметрам, если есть), `version`, `durationType`;
- в ход создания (`started_round === round` и
  `started_participant === current`) — не включать;
- ответ `endTurn` получает `sustain`.

### 4.7. Чат

Единый `GameActiveSpellChat` (не раздувать `GameSpellCastChat`): токены
`GameStrikeChat`, вложения — тред хода/касания, `[[rule:...]]`.

- создание: «X поддерживает [[rule:Y]]»;
- продолжение: «X продолжает поддерживать [[rule:Y]]» (+ ОД);
- обрыв: «X перестаёт поддерживать [[rule:Y]]»;
- потеря источника: «источник недоступен, [[rule:Y]] спадает»;
- replay не дублирует: пишет только первая запись команды.

### 4.8. Фронт

- `IGameApi`: `getActiveSpells` (real), `createActiveSpell` (мок-создание;
  real — `throwPhpUnsupported`), `sustainActiveSpell(gameId, id, power,
  expectedVersion)`, `dropActiveSpell(gameId, id, expectedVersion)`.
- `GameApi`: real-вызовы `game.getActiveSpells`, `game.sustainSpell`,
  `game.dropSpell`; обновление `gamePhpBattleContext.battleVersion` из
  ответа, `sheetVersionByKey` — из `sheetVersion`.
- `GamePhpTurnResult`: `sustain?: GamePhpSustainPrompt[]`.
- `InitiativeTrack` real-ветка: после `endTurn` — диалог по `sustain`
  по очереди; continue/drop → API; локальный чат и mock-заряды не
  вызываются; мок-ветка не тронута.
- `CombatCardPanel`: блок «Поддержание» и `abortSustainedSpell` → серверная
  команда; `dropBoundCharges` — только мок (M8).
- `SpellCastDialog`: `persistGeneratorIfNeeded` — только мок; real-каст
  создаёт запись на сервере; занятость источника — из `getActiveSpells`.
- `SpellSustainDialog`: пропс сузить до `SpellSustainPrompt` (id, spellCode,
  sustainPower, version); `ActiveSpell` его расширяет.

## 5. Срезы

### 6.1. Rule/Game-примитивы

- `GameSpellDurationRules` — `instant`/`sustained`/неподдержано,
  `action_cost` (int или `toNumber`), `limit`, `power`.
- `GameSpellSource` — ключ, принадлежность при касте, доступность при
  поддержании (item надет, grant-донор есть, характеристика есть);
  `GameSpellCastFacts` переводится на него.
- `GameSpellPowerCap` — потолок мощи по характеристике пути со штрафом
  отклонения; используют каст и поддержание.
- Unit-тесты форм и доступности источника.

### 6.2. Таблица и репозиторий

- `GameActiveSpellTable/Schema/Repository`, `GameActiveSpellRecord`,
  `GameActiveSpellView`.
- `GameModuleSetup`, `GameMysqlTables::drop`, очистка в
  `GameBattleMutator::end` / `GameBattleCleanup` / смене состава.
- MySQL-тест: add/find/update CAS/delete/очистка.

### 6.3. Создание из каста и запрет занятого источника

- `GameActiveSpellCreate`, вызовы из `finish`/`resolveWork`/касания.
- Проверка занятости в `GameSpellCast::work` и в
  `GameSpellTouchFacts::prepare` (передать бой в подготовку касания).
- `activeSpell` в ответах каста и касания.
- MySQL: создан/не создан (instant, провал, молоко), занятый источник,
  replay без дубля, чужая карточка.

### 6.4. Команды поддержания и конца хода

- `GameActiveSpellSustain`, `GameActiveSpellDrop`, парсеры тел, input DTO,
  actions и маршруты `game.sustainSpell`/`game.dropSpell`.
- `GameTurnAccess::isWriter` (публичный), `GameTurns` получает две команды
  через тот же конверт.
- `GameActiveSpellTurnStart` в `GameTurnEnd` + `sustain` в ответе.
- `GameActiveSpellChat`.
- MySQL: продолжение/смена мощи/обрыв/replay/конфликты версий/чужой
  кастер/не свой ход/авто-drop/ОД по `action_cost`.

### 6.5. Чтение и real-фронт

- `game.getActiveSpells` (action, HTTP, сервис).
- TS DTO/IGameApi/GameApi/Mock; `GamePhpTurnResult`.
- `InitiativeTrack`, `CombatCardPanel`, `SpellCastDialog`,
  `SpellSustainDialog` real-ветки.
- vitest на запросы, промпт, drop без локального чата, занятость источника.

### 6.6. Документы и ручная проверка

- Роудмап (M6, журнал), эта карта, `composer maps`, `docs-check`.
- Dev: `php bin/setup.php` (запускает пользователь).
- Ручная проверка: «Генератор молний» и «Восприятие магии»; занятый
  источник; смена мощи; обрыв; потеря источника; вопрос в начале хода.
- Полный `bash tools/test-full.sh` — только после подтверждения.

## 6. Тесты

Юнит:

- `duration.type`: `instant` — нет записи; `sustained` — есть; `lingering`/
  `refreshable` — отказ; отсутствие `duration` — `instant`.
- `action_cost`: `null`/отсутствует → 0; int → N; размерное → `toNumber`.
- источник: `inventory:<id>` есть/нет, надет/снят; `grant` есть/нет;
  `characteristic` совпадает с характеристикой пути.
- потолок мощи: равен потолку, ниже, выше; штраф отклонения уменьшает.
- `GameActiveSpellView`/`Record` round-trip; `id` — строка.

MySQL:

- создание: cast `sustained` → строка с серверными `spellCode`, `usedPower`,
  `sourceKey`, `caster_sheet_version`, `started_*`; `instant` — нет;
  провал check — нет; молоко касания — нет; replay — одна строка, один чат.
- занятый источник: второй каст с тем же `source_key` — отказ, ОД не
  списаны; другой источник — можно; чужой кастер — можно.
- поддержание: продолжение без смены мощи; смена мощи (версия +1, потолок);
  `action_cost` списывает ОД, не хватает — отказ; чужая версия строки/листа/
  боя — конфликт; replay — без второй траты и чата; не свой ход —
  `AUTH_DENIED`/`INVALID`; GM/владелец — можно.
- обрыв: строка удалена, чат один, replay; повторный обрыв — `NOT_FOUND`.
- конец хода: `sustain` содержит заклинания нового текущего; в ход создания
  — пусто; снятый предмет/грант — авто-drop + чат; несколько заклинаний —
  все в списке.
- конец боя/сессии и смена состава — строки удалены.

Front (`vitest`):

- `GameApi.getActiveSpells` мапит ответ; `sustainActiveSpell`/
  `dropActiveSpell` шлют версии и обновляют контекст боя/листа.
- `InitiativeTrack`: `endTurn` с `sustain` открывает диалог, продолжение и
  обрыв идут серверными вызовами, локального чата нет.
- `CombatCardPanel`: «Оборвать» → `dropSpell`, без локального чата и без
  mock-зарядов.
- `SpellCastDialog`: занятый источник disabled; real-каст не вызывает
  `createActiveSpell`.
- мок-поток не сломан.

Гейт шага:

- `composer stan`, `composer test:unit`;
- `composer test:module game`;
- `npx vitest run` по затронутым файлам;
- `composer docs-check`, `composer maps-check`;
- полный `bash tools/test-full.sh` — только по подтверждению.

## 7. Риски и открытые вопросы

1. **Новая таблица.** Dev-БД и тестовая обновляются установщиком; без
   `php bin/setup.php` чтение упадёт. В dev запускает пользователь.
2. **Нет серверного гейта.** Обход диалога оставляет эффект с прежней мощью.
   Если нужен жёсткий `pending_sustain`, это меняет конец хода и блокировку
   действий — кандидат в M7.
3. **Мощь по умолчанию.** Диалог предзаполняется текущей мощью; `duration.power`
   (`x` Генератора) отдаётся как `defaultSustainPower` и используется, если
   клиент не прислал значение. Буквальное «x задаёт мощь после первого хода»
   (spell-plan-08) — уточнить, если поведение окажется неудобным.
4. **`action_cost` у `sustained`.** Трактуем как цену каждого продолжения;
   в контенте поля нет, поэтому проверяется только синтетической карточкой.
   Для `refreshable` это же поле станет ценой обновления в M15.
5. **Смена состава и выход.** Удаление строк выбывших участников — решение
   M6; если заклинание должно переживать выход кастера, это отдельный
   сценарий.
6. **Источник-характеристика.** Все касты с `characteristic:magic-power`
   делят один `source_key`; это и есть канон «источник занят», но на
   практике ограничивает одним sustained-эффектом. Проверить на «Генераторе»
   и «Восприятии магии» в dev.
7. **Ревизия контента.** `spell_code` хранится без версии карточки; игра
   фиксирует ревизию, поэтому перечитывание карточки стабильно. Если
   появится смена ревизии в открытой игре — понадобится снимок формы.
8. **Совместимость ответа.** `activeSpell` и `sustain` — добавления, старые
   поля не меняются; мок-ветки не переписываются.
9. **Чат и несколько клиентов.** Продолжение/обрыв видны через сигнал
   доставки; отдельного события не вводим.
10. **`id` строкой.** Контракт фронта уже строковый; сервер парсит
    положительное целое. Если позже понадобится numeric id — отдельная
    миграция DTO.

## 8. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-11 | План заведён: таблица `game_active_spell`, создание из завершённого каста, запрет занятого источника, `game.sustainSpell`/`game.dropSpell`, промпт из `endTurn`, чат, real-фронт. Решения 1–5 согласованы: таблица, начало хода через `endTurn`, запрет одного источника, только `sustained`, трек + карточка + занятость источника. |
| 2026-11-11 | 6.1: `GameSpellDurationRules` (только `instant`/`sustained`, цена из `duration.action_cost`), `GameSpellSource` (принадлежность при касте, доступность при поддержании), `GameSpellPowerCap` (потолок характеристики со штрафом отклонения). `GameSpellCastFacts` переведён на общий источник; юнит-тесты формы и доступности. |
| 2026-11-11 | 6.2: `GameActiveSpellTable/Schema/Repository`, `GameActiveSpellRecord/New`, `GameActiveSpellView`; регистрация в `GameModuleSetup`, `GameMysqlTables`, `GameMysqlFixture`, `GameStrikeRepositories`; очистка при конце боя, сессии и смене состава. |
| 2026-11-11 | 6.3: `GameActiveSpellGuard/Create/Chat`; строка создаётся из обычного каста, `resolve` и касания; `activeSpell` в ответах; занятость источника проверяется в `GameSpellCast::work` и `GameSpellTouchFacts::prepare`. |
| 2026-11-11 | 6.4: `GameActiveSpellFacts/Sustain/Drop/TurnStart/Wiring`, `GameActiveSpellBody`, HTTP и actions, маршруты `game.sustainSpell`/`game.dropSpell`/`game.getActiveSpells`; `GameTurns::sustainSpell/dropSpell`, публичный `GameTurnAccess::isWriter`, `endTurn` возвращает `sustain`. Потеря источника снимает строку и пишет чат. |
| 2026-11-11 | 6.5: TS `ActiveSpell` (`version`, `casterSheetVersion`, опциональная `stability`), DTO запросов/ответов и промпта, `IGameApi`/`GameApi`/mock; real-ветки `InitiativeTrack` (промпт и продолжение), `CombatCardPanel` (обрыв), занятость источника в `SpellCastDialog`; vitest `gameApiActiveSpell`. `duration.power` отдаётся как `defaultSustainPower`; диалог предзаполняется текущей мощью. |
| 2026-11-11 | Самопроверка: `composer stan`, `composer test:unit` (1003), `composer test:module game` (631), 4 MySQL-теста M6 и точечный vitest — зелёные. Полный прогон не запускался: нужен `php bin/setup.php` в dev, ручная проверка и подтверждение пользователя. |
| 2026-11-11 | Багфикс ручной проверки: заклинание без активационных параметров писало `parameter_values = []`, а чтение строки отвергало пустой список как «не карту». Пустая карта теперь допустима; добавлены unit-тест строки и MySQL-тест каста без параметров. |
| 2026-11-11 | Багфикс по второй ручной проверке: сообщение о создании поддержания пишется в тред хода (а не в тред заклинания, из-за чего свёртка показывала только «Заклинание»); сводка свёртки заклинания учитывает «применяет»/«поддерживает» и иначе берёт первое сообщение; передача хода через «Ожидание» теперь тоже показывает вопросы поддержания. |
| 2026-11-11 | M6 закрыт: полный `bash tools/test-full.sh` зелёный (static 0, backend 1632 / 4 skip, frontend 2455). |
