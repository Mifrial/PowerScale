# План M4 — насыщение и остаток РУ

**Статус:** ~ПЛАН~, 2026-11-10. План заведён; реализация не начата.

**Реализация:** ~PHP~, ~VUE~.

**Опираться на:** ~PHP~ (`Rule/Dto/Spec/Ability/SpellSaturation`,
`Rule/Dto/Spec/Ability/NextCastDifficulty`,
`Rule/Spec/AbilityCombat::saturation`/`nextDifficulty`,
`Rule/Dto/Spec/Ability/AbilityBase::getSpellSaturation`/`getNextCastDifficulty`,
`Game/Service/GameSpellCast`, `GameSpellCastFacts`, `GameSpellCastCheck`,
`GameSpellCastDamage`, `GameSpellCastChat`, `GameSpellCastView`,
`GameSpellTouchFacts`, `GameSpellTouchFinish`, `GamePendingActionCosts`,
`GameStrikeSingleWrite`, `GameBattleRepository`, `GameActionRepository`,
`GameViewAssembler`), ~VUE~ (`SpellCastDialog`, `HitLaunchDialog`,
`GameChatTab`, `GameApi`, `GamePhpBattleContext`, `GamePhpSpellCastResult`,
`GameBattleState`, `SpellCastExecutionService` только как предпросмотр),
документы [`spell-real-roadmap.md`](spell-real-roadmap.md) (M4),
[`spell-real-plan-02-spell-upgrades.md`](spell-real-plan-02-spell-upgrades.md),
[`spell-real-plan-03-check-efficiency.md`](spell-real-plan-03-check-efficiency.md),
[`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md),
[`magic-content-design-02-arcanist-advanced.md`](magic-content-design-02-arcanist-advanced.md),
[`magic-content-design-03-arcanist-mastery.md`](magic-content-design-03-arcanist-mastery.md).

**Не основа:** `SpellCastExecutionService`, `mockSpellImport`, `MockGameApi`,
локальный бросок и локальный слайдер насыщения — ~НЕ ОСНОВА~. В real-режиме
бросок, шаги и остаток считает сервер; мок остаётся только предпросмотром.

**Родитель:** [`spell-real-roadmap.md`](spell-real-roadmap.md), шаг M4.
**Связанные:** [`spell-real-plan-02-spell-upgrades.md`](spell-real-plan-02-spell-upgrades.md)
(M2), [`spell-real-plan-03-check-efficiency.md`](spell-real-plan-03-check-efficiency.md)
(M3), [`game-plan-46-spell-cast.md`](game-plan-46-spell-cast.md).

M3 подключил эффективность проверки. M4 подключает два уже разобранных, но не
используемых поля Rule:

- `spell_saturation` — пост-проверочная трата РУ на Мощь;
- `next_cast_difficulty` — снижение Сложности следующего сотворения от остатка
  РУ.

Шаг затрагивает не только расчёт: пост-проверочный выбор требует паузы между
броском и применением эффекта, поэтому M4 вводит двухфазный серверный каст и
отложенное состояние боя. Касание получает такую же паузу после закрытия удара.

## 1. Границы

Входят:

- чтение `spell_saturation` и `next_cast_difficulty` у **изученных**
  способностей кастера по эффективным уровням листа;
- двухфазный обычный каст: `check` → выбор шагов → `resolve`;
- пауза после закрытия удара касания: проверка сотворения есть, эффект
  заклинания ждёт выбора;
- хранение отложенного каста в состоянии боя и открытое действие на время
  выбора;
- снятие и постановка `next_spell_cast_difficulty` в
  `game_battle.pending_action_effects` в форме, которую понимает фронт;
- серверные ответы `check`/`resolve`/`abort`, строка чата о насыщении и о
  снижении Сложности;
- фронт: слайдер шагов после броска, общая панель для обычного каста и
  касания, баннер незавершённого сотворения, обновление версии боя;
- тесты Rule/Game/Character-фикстур и фронта, документация и карты.

Не входят:

- магическое отклонение (M5), активные заклинания (M6), поддержание (M7),
  заряды и Генератор (M8), ядро и заёмная мощь (M9–M10);
- цепная молния и многоцелевые (M13);
- изменение правил `check_efficiency` (M3) и `spell_upgrade` (M2);
- вторая модель броска или клиентский расчёт как авторитет;
- новые таблицы: отложенный каст живёт в JSON-поле боя;
- автоматическое разрешение насыщения по политике «всегда максимум».

## 2. Что уже есть

| Место | Что делает | Чего не делает |
| --- | --- | --- |
| `Rule/Dto/Spec/Ability/SpellSaturation.php` | хранит `min_rating`, `rating_per_step`, `power_per_step` | не решает доступность и не считает шаги |
| `Rule/Dto/Spec/Ability/NextCastDifficulty.php` | хранит `min_remaining_rating`, `delta`, `source_code` | не хранит и не снимает хвост |
| `Rule/Spec/AbilityCombat::saturation` / `nextDifficulty` | разбирают поля в `AbilityBase` | — |
| Контент ревизии 47 | «Динамическое энергонасыщение»: `min_rating: 2`, `rating_per_step: 2`, `power_per_step: 1`; «Межструктурные энергопереходы»: `min_remaining_rating: 2`, `delta: -1`, `source_code: training` | Game их не читает |
| `GameSpellCastFacts::prepare` | доступность, параметры, мощь, контроль, траты, руки | насыщение не читает |
| `GameSpellCastCheck` | Сложность (дефицит, сопротивление, эффективность), бросок | не умеет принять сохранённый бросок и дельту тренировки |
| `GameSpellCastDamage` | урон от `spellPower`, опыт, falloff, сопротивление | не получает добавку Мощи от насыщения |
| `GameSpellTouchFinish` | проверка и эффект заклинания касания после удара | не умеет отложить эффект |
| `GameBattleRecord`/`GameBattleRepository` | `pending_action_effects`, CAS `state_version` | отдельного отложенного каста нет |
| `GamePendingActionCosts` | снимает/ставит хвосты обычных действий и ударов | `next_spell_cast_difficulty` не знает |
| `SpellCastDialog` (фронт) | в моке показывает слайдер после локального броска | в real-режиме броска и слайдера нет |
| `HitLaunchDialog` (фронт) | закрывает касание через `game.runAction` | паузы после закрытия нет |
| `GameApi.castSpell` | отправляет `game.runAction` kind spell | не обновляет `battleVersion` после каста |

Вывод: Rule и контент готовы, но Game не читает оба поля. Пост-проверочный
выбор нельзя сделать одной командой: клиент не знает бросок до ответа сервера.
Поэтому M4 — это не только расчёт, но и явная фаза между броском и эффектом.

## 3. Правила

### 3.1. Насыщение

Источник — «Динамическое энергонасыщение»:

- доступно после **успешной** проверки сотворения, до разрешения эффекта
  заклинания;
- каждые `rating_per_step` РУ дают `power_per_step` Мощи;
- нельзя потратить больше РУ, чем получено за проверку;
- `min_rating` — минимальный результат, с которого выбор вообще доступен;
- после выбора применяются способности, зависящие от результата проверки.

Серверная модель:

- `rating` — результат проверки (`cast.roll.rating`);
- `maxSteps = castOk && needsCheck && rating >= min_rating
  ? floor(rating / rating_per_step) : 0`;
- `steps` — выбранное целое `0..maxSteps`;
- `ratingSpent = steps × rating_per_step`;
- `powerDelta = steps × power_per_step`;
- `remainingRating = rating − ratingSpent`;
- `powerDelta` добавляется к Мощи заклинания для урона; Требуемая мощь и
  Сложность уже посчитаны и не меняются.

### 3.2. Следующая Сложность

Источник — «Межструктурные энергопереходы»:

- если сотворение успешно и **остаток РУ** после насыщения не ниже
  `min_remaining_rating`, следующее сотворение получает `delta` к Сложности;
- это волшебство должно быть следующим действием кастера;
- итоговая стоимость следующего сотворения в ОД не должна превышать стоимость
  предыдущего;
- после применения бонус исчезает;
- если следующее сотворение снова даёт достаточный остаток РУ, бонус
  появляется снова.

Хвост хранится в `game_battle.pending_action_effects` под ключом кастера
(`character:<id>`/`npc:<id>`) в форме фронта:

```json
{
  "sourceRuleCode": "interstructure-energy-transfer",
  "effect": {
    "type": "next_spell_cast_difficulty",
    "delta": -1,
    "source_code": "training",
    "max_total_action_cost": 5
  }
}
```

`max_total_action_cost` — `prepared->spent` предыдущего сотворения (стоимость
компонентов заклинания с улучшениями). Для касания это стоимость самого
заклинания, а не удара касания.

## 4. Модель и API

### 4.1. Две фазы обычного каста

`game.runAction`, `kind: "spell"`:

1. `phase: "check"` — полный контекст каста, как у текущего `declare`.
   - проверить бой, права, руки, ОД, цель, версии;
   - прочитать хвост `next_spell_cast_difficulty`, посчитать дельту, **не
     снимая** его;
   - бросить проверку;
   - если `maxSteps === 0` — сразу завершить каст в этой же команде
     (однофазный ответ `phase: "resolve"`);
   - иначе открыть действие боя `kind: "spell"`, записать
     `pending_spell_cast`, вернуть `phase: "check"` с броском и `maxSteps`.
   ОД, листы, урон и чат на этом шаге не меняются.
2. `phase: "resolve"` — `{castId, saturationSteps}`.
   - взять сохранённый бросок и тело каста;
   - проверить, что версии листов и боя не изменились;
   - проверить `0 <= saturationSteps <= maxSteps`;
   - снять старый хвост `next_spell_cast_difficulty` (дельты записей, чей
     `max_total_action_cost` не меньше стоимости этого каста);
   - применить мощь, урон, траты, последствия;
   - поставить новый хвост, если проверка успешна и
     `remainingRating >= min_remaining_rating`;
   - записать листы, `pending_action_effects`, очистить
     `pending_spell_cast`, закрыть действие, написать чат.
3. `phase: "abort"` — `{castId}`.
   - только для устаревших версий листа/боя или для GM (`edit_all`);
   - очистить `pending_spell_cast`, закрыть действие, не тратить ОД и не
     писать чат.
4. `phase: "declare"` — совместимый однофазный путь: как `check`, но при
   наличии выбора завершает каст с `saturationSteps: 0`. Нужен для старых
   вызовов и `game.castSpell`; real-фронт использует `check`/`resolve`.

**Закрытие диалога при выборе = `resolve` с 0 шагами.** Каст после броска уже
состоялся; отменять его нельзя, иначе можно перебрасывать проверку. `abort` в
обычной игре игроку недоступен.

Команды и идемпотентность:

- `check` — ключ `idempotencyKey` (он же `castId`);
- `resolve` — производный ключ `<castId>:resolve`;
- `abort` — производный ключ `<castId>:abort`;
- replay каждой фазы отдельный; повтор `check` возвращает тот же бросок и не
  создаёт второе действие.

Ответ `check`:

```json
{
  "kind": "spell",
  "phase": "check",
  "battleId": 92,
  "version": 5,
  "castId": "0ece07d2-9140-48bf-8f85-ac573530055c",
  "caster": {"type": "npc", "id": 3, "sheetVersion": 58},
  "target": {"type": "npc", "id": 2, "sheetVersion": 289},
  "check": {
    "difficulty": {"base": 4, "size": 0},
    "needsCheck": true,
    "roll": {"success": true, "rating": 5},
    "castOk": true
  },
  "saturation": {
    "sourceCode": "dynamic-energy-saturation",
    "minRating": 2,
    "ratingPerStep": 2,
    "powerPerStep": 1,
    "rating": 5,
    "maxSteps": 2
  }
}
```

Ответ `resolve` — обычный ответ каста плюс:

```json
{
  "phase": "resolve",
  "version": 6,
  "castId": "0ece07d2-9140-48bf-8f85-ac573530055c",
  "saturation": {
    "steps": 1,
    "ratingSpent": 2,
    "powerDelta": 1,
    "remainingRating": 3
  },
  "nextCast": [
    {
      "sourceCode": "interstructure-energy-transfer",
      "delta": -1,
      "minRemainingRating": 2,
      "maxTotalActionCost": 5
    }
  ]
}
```

### 4.2. Хранение отложенного каста

Новое JSON-поле `game_battle.pending_spell_cast` (`null` или объект):

```json
{
  "castId": "0ece07d2-9140-48bf-8f85-ac573530055c",
  "actionId": 123,
  "actor": {"type": "npc", "id": 3},
  "userId": 2,
  "spellCode": "lightning-strike",
  "body": {"pathCode": "arcanist", "parameters": {}, "usedPower": {}, "target": {}, "upgrades": []},
  "check": {"difficulty": {}, "needsCheck": true, "roll": {}, "castOk": true},
  "trainingDelta": 0,
  "cost": 5,
  "casterVersion": 58,
  "target": {"type": "npc", "id": 2, "version": 289},
  "touch": false,
  "saturation": {
    "sourceCode": "dynamic-energy-saturation",
    "minRating": 2,
    "ratingPerStep": 2,
    "powerPerStep": 1,
    "maxSteps": 2
  },
  "createdAt": 1794315000
}
```

Для касания `touch: true`, `target.version` — версия цели после закрытия удара,
`body` — блок `spellCast` касания.

Схема:

- `Table/GameBattleTable` — `JsonField('pending_spell_cast')`;
- `GameBattleRecord` — геттер и разбор `fromNormalized`;
- `GameBattleRepository` — `beginSpellCast`, `finishSpellCast`,
  `clearSpellCast` (каждый одним CAS с `state_version`);
  `replacePending`/`replaceTracked` получают необязательный
  `pendingSpellCast`, чтобы касание писало его вместе с хвостами удара;
- `GameViewAssembler` — `pendingSpellCast` в представлении боя;
- dev-БД обновляется `php bin/setup.php`, тестовая — установщиком схемы.

Действие боя:

- на `check` создаётся строка `game_action` с `kind: "spell"`, `open: true`,
  `action_rule_code` = код заклинания, атакующий = кастер;
- это блокирует следующий шаг через существующие `findOpenAny` в
  `GameSpellCast`, `GameStrikeOpenFlow`, `GameActionBattle`, `GameWideOpening`,
  `GameTurnWait` и `GameTurnProcessAbort`;
- `resolve`/`abort` закрывают строку. Если клиент пропал, GM завершает каст
  без насыщения или прерывает.

### 4.3. Серверные сервисы

- `GameSpellCastSaturation`: поиск `spell_saturation` у изученных способностей
  кастера (эффективные уровни листа), проверка `saturationSteps`, расчёт
  `ratingSpent`/`powerDelta`/`remainingRating`. Несколько источников: берётся
  первый изученный; если контент когда-нибудь даст несколько, решение
  пересматривается отдельно.
- `GameSpellCastNextCast`: чтение хвоста без снятия (`peek`), снятие с суммой
  дельт (`consume`), постановка нового хвоста (`queue`) при успехе и остатке
  не ниже порога. Несколько источников `next_cast_difficulty` складываются.
- `GameSpellCastBody`/`GameRunActionSpellBody`: строгий разбор фаз
  `check`/`resolve`/`abort`/`declare`; `saturationSteps` — целое `>= 0`.
- `GameSpellCastCheck` получает режим «переиспользовать сохранённый бросок»;
  дельта тренировки применяется после сопротивления и до проверки минимальной
  Сложности, как в предпросмотре фронта.
- `GameSpellCastDamage` получает `powerDelta` и добавляет его к Мощи до
  falloff.
- `GameSpellCastChat` после строки броска пишет строку насыщения
  (`[[rule:dynamic-energy-saturation]]`, потрачено РУ, прирост Мощи) и, если
  хвост поставлен, строку о снижении Сложности следующего сотворения.
- `GameSpellCastView` добавляет `phase`, `castId`, `saturation`, `nextCast`.
- `GameRunActionSpellDeclare` (или его преемник) разводит фазы; `GameSpellCast`
  остаётся входом, но разрешение/применение выносится в отдельные сервисы,
  чтобы не раздувать один класс.

### 4.4. Касание

`GameSpellTouchFinish` после броска:

- если `maxSteps === 0` — как сейчас: урон заклинания, чат, хвост Сложности
  ставится сразу;
- если выбор есть:
  - эффект заклинания и чат **не применяются**;
  - в `pending_spell_cast` пишется отложенный каст с `touch: true`, проверкой,
    телом касания и версией цели после удара;
  - открывается `game_action` `kind: "spell"` (в той же транзакции закрытия
    удара);
  - старый хвост `next_spell_cast_difficulty` снимается при закрытии удара
    (действие уже состоялось), новый ставится на `resolve`;
  - результат закрытия удара получает `spellCastPhase: "check"` и
    `spellPendingId`; `spellSr`/`spellDamage`/`spellInjury` остаются `null` до
    `resolve`.
- `resolve` для касания использует сохранённый бросок и цель после удара;
  урон заклинания пишется CAS по сохранённой версии цели.
- Если цель изменилась между ударом и `resolve`, команда отклоняется по
  конфликту версии; клиент перезагружает лист и завершает/прерывает каст.

### 4.5. Фронт

- `SpellCastDialog` в real-режиме:
  - `check` отправляется как `game.runAction` `phase: "check"`;
  - если ответ уже `phase: "resolve"` — поведение как сейчас;
  - если `phase: "check"` — диалог остаётся открытым, показывает бросок,
    `maxSteps`, текущий остаток РУ и итоговую Мощь;
  - «Применить» → `phase: "resolve"` с выбранными шагами;
  - закрытие → `resolve` с 0; при ошибке — сообщение, диалог не теряет выбор.
- `SpellSaturationPanel.vue` — общая панель для обычного каста и касания:
  слайдер, остаток РУ, прирост Мощи, кнопки «Применить»/«Без насыщения».
- `GameChatTab`:
  - читает `pendingSpellCast` из состояния боя;
  - для владельца кастера открывает панель (после касания или восстановления);
  - показывает баннер «Незавершённое сотворение» с «Завершить без насыщения»;
  - GM видит «Прервать» (`abort`).
- `HitLaunchDialog` после закрытия касания показывает, что заклинание ждёт
  выбора насыщения, и не врёт пустым уроном заклинания.
- `GameApi`: `castSpell` (check), `resolveSpellCast`, `abortSpellCast`;
  `castSpell` обновляет `battleVersion` (сейчас не обновляет). `GamePhpSpellCastResult`
  становится объединением `check`/`resolve`; `GameBattleState` получает
  `pendingSpellCast`.
- Мок-режим остаётся на локальном броске и слайдере; real-путь его не
  использует.

## 5. Срезы

### 5.1. Примитивы Rule/Game

- DTO намерения и результата насыщения, поиск источников.
- `GameSpellCastNextCast` (`peek`/`consume`/`queue`).
- Парсер `saturationSteps` и фаз.
- Юнит-тесты без БД.

### 5.2. Двухфазный обычный каст

- `pending_spell_cast`, открытое действие, CAS-записи боя.
- `check`/`resolve`/`abort` в `game.runAction`; однофазный `declare` для
  совместимости.
- Дельта тренировки в Сложности, мощь насыщения в уроне, хвост Сложности,
  чат и ответ.
- MySQL-тесты обычного каста.

### 5.3. Касание

- Пауза после закрытия удара, отложенный каст, `resolve` по версии цели.
- Тесты закрытия удара и `resolve`.

### 5.4. Фронт обычного каста

- Фазы в `GameApi`, `SpellCastDialog`, слайдер, версия боя.
- Vitest на поток `check → slider → resolve`.

### 5.5. Фронт касания и восстановления

- `SpellSaturationPanel`, `GameChatTab`, баннер, GM-прерывание.
- Vitest на панель и запросы.

### 5.6. Документы и ручная проверка

- Роудмап, эта карта, `composer maps`, `docs-check`.
- Dev: `php bin/setup.php`, кастер с «Динамическим энергонасыщением» и
  «Межструктурными энергопереходами».

## 6. Тесты

Юнит:

- насыщение: `rating 5`, `rating_per_step 2`, `power_per_step 1` → 2 шага,
  `+2` Мощи, остаток 1; выбор 1 шага → `+1`, остаток 3; 0 шагов → без
  изменений; нельзя больше `maxSteps`;
- порог `min_rating`: ниже порога шагов нет, даже при успехе;
- провал проверки: шагов нет, Мощь не меняется;
- `next_cast_difficulty`: `peek` не снимает, `consume` снимает и суммирует,
  запись с `max_total_action_cost` меньше стоимости не даёт дельту;
- `queue` только при успехе и остатке не ниже порога; несколько источников
  складываются;
- парсер `saturationSteps`: отрицательное, дробное, больше `maxSteps` —
  ошибка.

MySQL, обычный каст:

- `check` с насыщением: ОД не списаны, листы не изменены, чата нет, открытое
  действие и `pending_spell_cast` есть, `version` вырос;
- `resolve` с шагами: ОД списаны, урон выше базового на `powerDelta`, строка
  насыщения в чате, `pending_spell_cast` очищен, действие закрыто;
- `resolve` с 0 шагов: каст завершён без прироста Мощи;
- `check` без способности или с провалом — сразу `resolve`, как раньше;
- шаги больше `maxSteps` и отрицательные отклоняются;
- повторный `check` при открытом действии блокируется;
- replay `check`/`resolve` не пишет второй раз;
- устаревшие версии листа/боя отклоняются;
- `abort` очищает состояние без трат и чата; игроку в обычной ситуации
  недоступен;
- хвост `next_spell_cast_difficulty`: предзаполненный хвост снижает
  Сложность, снимается после `resolve`, новый ставится при достаточном
  остатке; стоимость выше потолка не даёт дельту; другое действие снимает
  хвост.

MySQL, касание:

- закрытие удара с насыщением: урон удара записан, урон заклинания и чат
  заклинания ещё нет, `pending_spell_cast` и открытое действие есть;
- `resolve` пишет урон заклинания и чат, закрывает действие;
- `resolve` с 0 шагов;
- изменение листа цели между ударом и `resolve` — конфликт;
- replay закрытия и `resolve`;
- `abort` после закрытия удара.

Front (`vitest`):

- `GameApi.castSpell` обновляет `battleVersion` из ответа `check` и
  `resolve`;
- `SpellCastDialog` в real-режиме: ответ `check` открывает панель, закрытие
  отправляет `resolve` с 0, «Применить» — с выбранными шагами;
- `SpellSaturationPanel` считает остаток и прирост Мощи по ответу сервера,
  не по локальному броску;
- `GameChatTab` открывает панель по `pendingSpellCast` и не дублирует
  запросы;
- мок-поток не сломан.

Гейт шага:

- `composer stan`;
- `composer test:unit`;
- `composer test:module game`;
- `composer test:module character` — если затронут Character;
- `npx vitest run` по затронутым файлам;
- `composer docs-check`, `composer maps-check`;
- полный `bash tools/test-full.sh` — только по подтверждению пользователя.

## 7. Риски и открытые вопросы

1. **Новая колонка боя.** `pending_spell_cast` меняет схему; dev-БД и тестовая
   обновляются установщиком. Без `php bin/setup.php` каст на dev упадёт.
2. **Реролл проверки.** Пост-проверочный выбор без коммита позволял бы
   перебрасывать каст. Решение: после броска каст всегда завершается; закрытие
   диалога — `resolve` с 0; `abort` — только для устаревших версий/GM.
3. **Зависшее действие.** Если клиент пропал между `check` и `resolve`,
   открытое действие блокирует бой. Нужен баннер и GM-прерывание; без него
   возможен дедлок. Это обязательная часть M4, а не «потом».
4. **Версии и сохранённый бросок.** Между фазами лист кастера/цели или бой
   могут измениться. `resolve` сверяет сохранённые версии и отклоняет
   устаревший каст; клиент перезагружает состояние.
5. **Транзакция закрытия касания.** Отложенный каст и открытое действие
   должны писаться в одной транзакции с хвостами удара; иначе возможен удар
   без отложенного каста или наоборот.
6. **Стоимость для `max_total_action_cost`.** Берётся `prepared->spent`
   заклинания, а не полная стоимость удара касания. Это соответствует
   «стоимости сотворения»; при появлении спорного контента решение
   пересматривается.
7. **Несколько источников.** Насыщение — первый изученный источник; хвосты
   `next_cast_difficulty` складываются. В текущем контенте по одному
   источнику; правило для нескольких нужно подтвердить при появлении.
8. **`min_rating`.** Трактуется как порог доступности выбора и одновременно
   как минимальный результат для первого шага (в контенте оба значения
   совпадают: 2). Если появится `min_rating < rating_per_step`, доступность и
   количество шагов разойдутся — тест зафиксирует принятое поведение.
9. **Хвост и конец хода.** Хвост снимается следующим действием, а не концом
   хода. Если «следующее действие» через ход не должно сохраняться, это
   отдельное решение; сейчас поведение совпадает с моком.
10. **Версия боя в старых клиентах.** `check` поднимает `state_version`;
    клиент, который не обновил `battleVersion`, получит конфликт на следующем
    действии. `GameApi.castSpell` обязан обновлять версию, это отдельный
    багфикс в M4.
11. **Совместимость.** `phase: "declare"` и `game.castSpell` остаются
    однофазными (0 шагов), чтобы не ломать старые тесты и вызовы. Real-фронт
    использует `check`/`resolve`.
12. **Мок.** Локальный слайдер и локальный бросок остаются предпросмотром;
    расхождение с real-путём допустимо, но не должно попадать в контракт
    сервера.
13. **Чат.** Строка броска и насыщения пишется только на `resolve`, иначе при
    отложенном выборе чат показывал бы ещё не применённый эффект. Replay не
    должен дублировать сообщения.
14. **Открытое действие и другие клиенты.** `check` не пишет чат; другие
    клиенты узнают о паузе через состояние боя/баннер, а не через сообщение.
    Если понадобится мгновенное уведомление, это отдельный сигнал, не чат.

## 8. Журнал

| Дата | Изменение |
| --- | --- |
| 2026-11-10 | План заведён: двухфазный каст `check → resolve`, отложенный каст в `game_battle.pending_spell_cast`, пауза касания после закрытия удара, хвост `next_spell_cast_difficulty` в состоянии боя. |
