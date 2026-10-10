# Read-only AP storage contract

## A. Найденное существующее storage evidence

1. **Character actual**
   - Физическое authoritative-хранилище: `character`.
   - JSON-поля: `choices`, `sheet`.
   - CAS: `actual_version`.
   - `CharacterRecord` типизирует `choices` и `sheet` только как массивы, отдельного resource DTO нет.
   - `CharacterSheetDocument` сейчас строит `sheet` с ключами:
     `abilityLevels`, `racialAbilityCodes`, `osSurchargeTotal`, `equippedModifiers`,
     `coveredPaths`, `characteristicPurchases`, `characteristicPurchaseOs`, `active`, `money`.
   - AP в текущем `sheet` отсутствует.
   - `choices` содержит build-вход: inventory, abilities, characteristics, limits и money.
     Runtime AP туда не подходит.

2. **NPC**
   - Authoritative-хранилище: `game_npc.version`.
   - Внутри `version` находится `choices` и `sheet`.
   - CAS: `npc.actual_version`.
   - Отдельного typed resource-поля нет.
   - Game overlay не является допустимым источником листа.

3. **Секции и projection**
   - Секция `resources` уже существует.
   - Сейчас она проецирует только `money`:
     - Character: `choices.money`, `sheet.money`;
     - NPC: `sheet.money`.
   - `sheet.resources` кодом не подтверждён.

4. **Mutation**
   - Character: `CharacterActualMutations::apply()` → `CharacterRepository::replacePayload()`.
   - NPC: `CharacterActualMutations::applyToDocument()` → `GameNpcRepository::replaceVersion()`.
   - Оба пути уже используют optimistic CAS.
   - AP-specific spend port отсутствует.

5. **Migration**
   - Character migration пересобирает `sheet` на target revision.
   - Из старого `sheet` явно сохраняются только деньги.
   - AP migration policy отсутствует.
   - Migration active-session participant запрещена.

## B. Рекомендованный authoritative AP representation

Рекомендую использовать существующий authoritative `sheet`, добавив в него типизированный подраздел `resources`.

Для Character:

```json
{
  "resources": {
    "<live-resource-code>": {
      "current": 3
    }
  }
}
```

Для NPC:

```json
{
  "spaceId": 12,
  "rulesRevision": 7,
  "choices": {},
  "sheet": {
    "resources": {
      "<live-resource-code>": {
        "current": 3
      }
    }
  }
}
```

Это не должно быть добавлено молча: `sheet.resources` сейчас не существует как подтверждённый backend contract.

Свойства representation:

- `resourceCode` — ключ live `resource` Rule, не hardcoded `ap`;
- `current` — persisted authoritative integer;
- `limit`, `base`, `bonuses` — derived из текущей Rule revision, grants и modifiers;
- dimensional AP не допускается;
- `current >= 0`;
- spend ниже нуля запрещён;
- client не передаёт authoritative `current`.

`choices` для AP использовать не следует: это build/declaration document, а не runtime state.

## C. Storage, migration и backfill contract

### Storage

- Character: `character.sheet.resources`.
- NPC: `game_npc.version.sheet.resources`.
- Один общий JSON shape для Character и NPC.
- Внутри `resources` допускаются только server-resolved resource codes.
- Для AP mutation разрешается изменять только `current`.
- `limit/base/bonuses` не сохраняются как authority.
- Unknown resource rows не должны silently использоваться как AP.

### Initialization

При создании Character/NPC сервер должен:

1. Получить live Rule revision.
2. Найти AP resource через action Rule и `ResourceComponent`.
3. Проверить resource Rule:
   - `type = resource`;
   - `auto_add = true`;
   - `is_dimensional = false`;
   - effective limit — integer.
4. Инициализировать:

```text
current = effective initial limit
```

Но правило определения «effective initial limit» требует отдельного принятия: только `limit.base` или `base + applicable adjustments/grants`.

### Existing documents

Старые документы без AP должны считаться legacy-invalid для боевого действия:

- read возможен;
- AP-dependent action должен отказать `GAME_INVALID` либо вернуть migration-required status;
- молчаливое значение `0` не рекомендуется: оно маскирует отсутствие initialization;
- автоматический backfill должен быть отдельной идемпотентной migration/backfill operation с CAS.

Backfill обязан:

- не менять `choices`;
- не менять `gameOverlay`;
- использовать live revision;
- записывать AP в тот же authoritative document;
- увеличивать соответствующий version ровно один раз;
- быть безопасным при повторном запуске.

### Rule revision migration

Нужно принять одну из политик:

1. сохранить `current` по resource code и ограничить новым limit;
2. пересоздать `current` из нового initial limit;
3. сохранить только при совместимом resource Rule, иначе выдать migration conflict.

Текущий код не выбирает ни одну из них.

Migration должна быть атомарной: AP не может исчезнуть из-за пересборки `sheet`.

### Reset / refresh

В текущем каноне нет подтверждённого authoritative поведения reset/refresh AP:

- между ходами;
- между боями;
- при `endBattle`;
- при `stopSession`;
- при начале нового battle.

Поэтому автоматический reset добавлять нельзя.

## D. Rule-side contract

Подтверждено:

- `RootSpecs::resource()` читает:
  - `is_dimensional`;
  - `auto_add`;
  - `check_token`;
  - `limit`.
- `ResourceLimit` содержит `base` и `adjustments`.
- `AbilitySpecs` поддерживает `type = action`.
- `ActionComponents` использует канонический backend key `components`.
- `ResourceComponent` содержит:
  - `resourceCode`;
  - `amount` типа `int | DimensionalNumber | ChosenAmount`.

Не подтверждено и требует resolver-а:

- ровно один AP resource component;
- соответствие `resourceCode` live resource Rule;
- `auto_add = true`;
- integer amount;
- отсутствие `DimensionalNumber` и `ChosenAmount`;
- отсутствие hardcoded AP code и hardcoded cost;
- action cost берётся из live Rule revision;
- разные revisions могут давать разные costs.

`auto_add` само по себе не является уникальным AP marker. Если в revision несколько подходящих resources, action должен быть invalid, а не выбирать первый.

## E. Owners и зависимости

- **Character** — typed resource document, initialization, validation, mutation port.
- **Game** — orchestration action/effect, но не storage.
- **Game NPC repository** — CAS replacement `version`.
- **Rule** — live resource/action resolution.
- **Projection/visibility** — добавление `sheet.resources` в resources section.
- **Migration owner** — policy при смене Rule revision.
- **Replay/idempotency owner** — Game command layer.

### CAS и replay

Для успешного spend:

- Character использует `actual_version`;
- NPC использует `actual_version`;
- stale version возвращает `GAME_CONFLICT`;
- current version/sheet возвращаются в conflict envelope;
- stale не превращается в auto-ignore;
- успешный replay не списывает AP повторно;
- replay с другим command body возвращает conflict;
- spend и authoritative effect выполняются в одной transaction;
- rollback отменяет и spend, и effect;
- entity version увеличивается один раз на успешную entity mutation.

## F. Acceptance criteria

Контракт можно считать принятым, когда подтверждено:

- AP хранится только в authoritative Character/NPC sheet;
- Character и NPC используют одинаковый JSON shape;
- `choices` и Game overlay не содержат AP authority;
- AP resource code разрешается из live Rule;
- нет hardcoded resource code или value;
- dimensional и chosen amounts отклоняются;
- `current` — persisted non-negative integer;
- limit/base/bonuses — derived;
- отсутствующий AP в legacy document не трактуется как ноль;
- initialization и backfill идемпотентны;
- migration policy явно определена;
- reset/refresh policy явно определена;
- resources projection включает AP по visibility rules;
- spend использует существующие CAS paths;
- stale, replay и rollback semantics определены;
- spend + effect атомарны.

## G. Решения, которые должен принять Андрей

1. Утвердить или отклонить `sheet.resources` как canonical storage.
2. Утвердить точный shape: map по `resourceCode` или список resource rows.
3. Определить AP resource identity при нескольких `auto_add` resources.
4. Утвердить integer-only storage.
5. Определить initial value: effective limit или отдельное initial rule значение.
6. Выбрать migration policy для текущего AP.
7. Определить reset/refresh lifecycle.
8. Определить, виден ли `current` всем пользователям с resources visibility или только GM/участнику.

## Verdict

**BLOCKED**

Причина: подходящее authoritative поле-контейнер найдено (`sheet`), но `sheet.resources`, точный JSON shape, initialization/backfill, migration и reset policy ещё не являются принятыми контрактами. До этих решений typed AP storage и atomic spend implementation утверждать нельзя.
