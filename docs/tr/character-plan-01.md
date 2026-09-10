# План Character 1 — серверный контракт

**Статус:** план, 2026-09-09. Нарезка — [`character-roadmap.md`](character-roadmap.md). Канон — [`character-system.md`](character-system.md). DAG — [`architecture.md`](architecture.md). Binding — [`mechanic-plan-03.md`](mechanic-plan-03.md). PHP Engine — OPEN в [`rule-roadmap.md`](rule-roadmap.md).

Цель: согласовать контракт до модуля на диске. CRUD и DDL нет.

## 1. Pipeline save

```text
выборы (build)
  → IRuleSpaces.getRevision(spaceId, revision)
  → Character считает лист + Mechanic runEvent(Binding)
  → один валидатор (тот же на create, update и validate-only)
  → optional сверка expectedSheet
  → если запись: guard expectedVersion в той же TX, что write
  → actualCharacter
```

Обхода нет. Editor draft только браузер. Create/update без прохождения валидатора не пишут. Validate-only — тот же контур, без TX и без записи.

`gameId` на Character API нет: Character ↛ Game. Join / лимиты игры — операции Game (C8): Game читает actual, сам сравнивает spent с потолками игры и грантами мастера.

## 2. Вход — выборы, не посчитанный лист

Текущий Vue `CreateCharacterData.version` на бэке не канон. `ownerId` — актор. Селектор правил — `spaceId` + `revision` через RuleSpace (не `IRules` в обход мира, не `spaceCode` как ключ). Если клиент пришлёт `spaceCode`, сверить с миром или `CHARACTER_INVALID`.

### 2.1 Тело create/update

```text
spaceId, revision
name, shortDescription, fullDescription, ageYears
limits: { os, or, money? }    // money — только create (стартовый бюджет)
raceCode
characteristicPurchases: [{ characteristicCode, cost }]
abilities: [{ ruleCode, level, zone?, parameters?, domain?, domainCode?, grantedBy?, studyPairId?, studyPairRole? }]
inventory: [{ ruleCode | custom, quantity, equipped, modifiers?, durabilityLeft?, note? }]
customRules: [{ id?, kind, name, description, status, replacedWithRuleCode? }]
money?                        // наличные: не на create
active?                       // create и update; default true
expectedVersion               // только update: optimistic lock actual_version, НЕ rulesRevision
expectedSheet?                // опциональная сверка FE/BE
```

`expectedVersion` — целое `actual_version` строки персонажа (после create = 1, каждый успешный update +1). Это не номер ревизии правил. **Create:** ключа быть не должно (`INVALID_PARAMS`). **Update:** ключ обязателен; нет или не int — `CHARACTER_INVALID`; не совпал с БД — `CHARACTER_CONFLICT` + `currentVersion`. Validate-only: не обязателен.

Не во входе: итоги характеристик, `osSpent`/`olSpent`/`orSpent`, `olTotal`, resource bonuses/max, лист `status` (`draft`/`ready`), `gameId`, notes, visibility, булев `gifted`, на create — поле наличных `money`.

`current` ресурсов — не trusted как лимит; на create сервер выставляет из правил (C4).

Инвентарь: **`equipped` сохраняется**. Слоты / руки / конфликт экипировки — только validation. Кастом (`ruleCode` null + имя/описание) на листе владельца **допустим**. Клиентский `InventoryItem.id` черновика не PK.

`customRules` — тот же список на create (обычно `[]`) и на update. Новые без серверного `id`; существующие на update — с `id` из actual. Чужой id — `CHARACTER_INVALID`. Не правила ревизии; `replacedWithRuleCode` если задан — `code` в текущем срезе.

### 2.2 Purchases ≠ abilities ≠ race ≠ inventory

| Поле | Выбор игрока |
|---|---|
| `raceCode` | раса: fixed-базы, стоимость расы в ОС, врождённое |
| `characteristicPurchases` | ступень характеристики за ОС у расы `mode: purchased` (лестница cost → value). Не способность |
| `abilities` | навык/черта/заклинание: уровень, зона ОС/ОР, параметры покупки, domain, грант-источник |
| `inventory` | экземпляры предметов, не список id |

Без purchases сервер не отличит «Сила как у расы» от «купили вторую ступень». Итоговое значение характеристики — derived.

Список голых `abilityIds` недостаточен: уровень, несколько экземпляров `multiple`, `zone`, параметры, какой экземпляр покрыт грантом.

### 2.3 Лимиты

Три слоя: **потолок**, **потрачено** (считает сервер), **ОЛ** (из возраста + grants; игровой `olPointsLimit` — Game).

**Свои потолки** (`limits`): ОС/ОР — на create и можно менять на update. **`limits.money` — только create** (стартовый бюджет закупки). На update ключ `limits.money` — `INVALID_PARAMS` (бюджет заморожен). `null` = потолка нет. Отрицательные — `CHARACTER_INVALID`. Клиент не заявляет «игра дала N».

На **create** поле наличных `money` не принимается (`INVALID_PARAMS`). Сервер считает остаток: стартовый бюджет + grant `money` − Σ(`cost_gm` **купленного** инвентаря). Innate / grant items в сумму шопа **не** входят. Кастом без `cost_gm` в шоп как 0. Проверка: сумма шопа ≤ бюджета (если бюджет не null). Остаток пишется в actual (`money ≥ 0`; перерасход шопа — `problems`, записи нет).

На **update** `money` — наличные листа (выбор, ≥ 0). Стартовый шоп заново не пересчитывается; лут/выдача — Game.

Validate-only: без `characterId` — правила create (нет поля `money`, есть `limits.money`); с id — правила update (`money` можно, `limits.money` нельзя).

**Потолки игры** (`osPointsLimit` / `orPointsLimit` / `olPointsLimit` / `moneyLimit` + выдача мастера): не поле Character save. Game при подаче/approve читает spent/наличные с листа и свои числа. Character не знает об игре.

Свой потолок может быть жёстче игры; слабее игры — отсекает Game, лист при этом валиден как свободный персонаж.

### 2.4 Грант на экземпляре (`grantedBy`)

Булев `gifted` на входе не нужен.

Указатель нужен, когда слотов покрытия меньше кандидатов: грант `ability` (конкретный код) *или* `magic_study` (`scope`, `max_cost`, `max_instances`, `paid_cost`). Пример Арканиста: «заклинания со стоимостью ≤ 2», `max_instances` / бесплатный слот — какое из двух заклинаний покрыто.

```text
grantedBy: {
  ruleCode,       // донор на этом же листе (черта, «Становление Арканиста», …)
  instanceKey?    // ключ экземпляра донора, если донор multiple
}
```

Ключ экземпляра **всегда** с доменом/путём (одно заклинание на Арканисте и на Псионике — две записи):

```text
ruleCode + ':' + (domainCode ?? '') + ':' + (domain ?? '')
```

Не «только если spec.multiple». Пустой домен у обычного навыка → ключ `code::`. В одном save ключи уникальны. Дубль того же `ruleCode` **на том же пути/домене** — `CHARACTER_INVALID`. Тот же `ruleCode`, другой `domainCode` (путь) — ок.

Сервер: донор есть; у него grant, который покрывает этот `ruleCode` / уровень / базовую стоимость / scope **и путь** (`magic_study.path_code`; грант без пути не покрывает все дарованные пути); число покрытий ≤ `max_instances` (если задан); оплата = каталожная (или `paid_cost` гранта) минус покрытое. `magic_study` не путать с даром конкретного `ability_code`.

Покрытие `MagicPathSpec.includes_path_codes`: экземпляр на включённом пути (псионик под шаманом) удовлетворяет `has_ability` в контексте владельца и не требует второй покупки того же `ruleCode`. Повтор той же способности на том же пути — `CHARACTER_INVALID`. C4 должен повторить клиентскую семантику, не «есть любой `magic_study`».

Однозначный грант `ability` → конкретный код: `grantedBy` можно не слать, сервер материализует. Неоднозначный слот без указателя — problem на ability.

Два донора на одну запись — запрет, пока нет правила сложения.

#### Пара «два за 1 ОР»

Порядок массива и `localeCompare` **не** контракт. Пара — явный id:

```text
studyPairId: string   // две записи с одним id
```

Максимум две способности с одним id; обе с путём, у которого `pair_base_cost`, и с подходящей базовой ценой. Кто платит — **явно**, не порядок массива:

```text
studyPairRole: 'charged' | 'free'   // обязателен, если studyPairId задан
```

В группе из двух: ровно один `charged` (скидка пути) и один `free` (0). Один в группе — только `charged` (скидка, без пары). Два `charged`, два `free`, `free` без пары, три+ id, разные пути, не тот base — validation. Участник скидки пары без `studyPairId` / без роли — не угадывать. Id непрозрачный клиентский, не PK персонажа.

`grantedBy` и пара независимы: сначала покрытие грантом, затем оплата остатка по паре. Нельзя «обнулить дважды» сверх стоимости (spent ≥ 0).

### 2.5 Optional `expectedSheet`

Посчитанный лист только для diff. Канон сравнения: сортировка abilities/inventory по instance key; dimensional `{base, size}`; автоспособности и расовый innate gear как после серверного apply; без клиентских id инвентаря. Расхождение — fail, без записи. Не источник истины.

### 2.6 Инварианты входа (чтобы не откладывать в C4 молча)

- **Update не меняет** `spaceId` / `revision`. Смена ревизии — C7 `migrate`. Иначе `CHARACTER_INVALID`.
- **`raceCode` обязателен** для save; тип в срезе — `race`, не `species`. Null — не valid.
- В `abilities` только уровень ≥ 1. Автоматические (`zones.kind = automatic`) и чисто derived **не** присылать как покупку; сервер добавит. Прислали как купленные — validation.
- Расовые `grant type item` (innate gear) сервер сам кладёт в inventory; игрок их не «покупает». Прочий инвентарь — выбор (qty, equipped, modifiers, custom, durabilityLeft, note).
- `characteristicPurchases`: один ряд на `characteristicCode`; `cost` есть на лестнице расы; характеристика в спеке расы `purchased`. Лишняя/повтор — invalid.
- `zone` обязателен, если у способности больше одной покупаемой (не automatic) зоны.
- `parameters` обязательны для всех purchase-параметров спеки; значения в диапазоне; dimensional не строкой.
- `domain` / `domainCode`: при `multiple` / `domain_ref` — как в спеке; **у заклинания/изучения по пути** `domainCode` = код пути (иначе два пути схлопнутся). `domainCode` если задан — правило нужного типа в срезе.
- Имя: trim, непустое. Тексты — строки, не HTML-контракт.
- `ageYears`: если в срезе есть правило типа `age` — **обязателен** и попадает в ступень расы; нет `age` в срезе — можно null.
- `limits.os` / `limits.or`: целые ≥ 0 или null; на update можно менять.
- `limits.money` и наличные `money` — §2.3 (бюджет только create, наличные считает сервер / на update — поле листа).
- Выключенный **мир** — нельзя create/update/validate по этому space.
- Tombstone в составе — не цель race/ability/item.
- Владельца не меняем.
- **`active` персонажа** — поле полного create/update (default true); такой update **всё ещё** гоняет валидатор листа. Отдельный `deactivate` / `activate`: права + `expectedVersion`, **без** пересчёта build; при успехе `actual_version` всё равно +1. Не другой агрегат.
- Лишние ключи JSON — `INVALID_PARAMS`.
- `gameId` в теле — `INVALID_PARAMS`.

### 2.7 Что сервер достраивает сам

Автоспособности расы/вида; innate items; grants characteristic/resource/keyword/resistance/sense (derived листа); ОЛ из возраста; spent всех зон; surcharge механик; clamp resource current. В actual для edit хранятся **выборы §2.1**, не этот хвост как вход.

## 3. Граница модулей

| Модуль | На save | Не |
|---|---|---|
| RuleSpace | срез мира `(spaceId, revision)` | Character JSON, Engine |
| Rule | тело правила в срезе | persist персонажа |
| Character | оркестр, build, Binding, validation, persist | Versioning `open`, Game, SQL `rule*` |
| Mechanic | `runEvent`; нет хендлера — ошибка наверх | импорт Character/Rule; весь лист |
| Game | позже: join, свои лимиты, overlay | Character HTTP |

### Спеки правил не готовы

Текущие Vue `*Spec` **нельзя** считать каноном ни на PHP, ни для Character C4. Поля будут расширяться до релиза. **Нигде не объявлять spec готовым / `IMPLEMENTED`, пока не будет явной отмашки** (`decisions.md` или закрытие пункта Rule). Character читает spec только через узкий адаптер; не зашивать «как сейчас в TS» в фасад save. Хардкод **keyword codes** как механика персонажа — не канон (даже если фронт так сделал). Резолв codes вместо PHP-ids на снимке — долг C2, не разрешение зашивать коды признаков.

Появившийся hydrator не означает «спека закрыта». C4 без отмашки не закрывает «правила персонажа навсегда».

`contentStatus` на save не влияет. Tombstone `active=false` в составе — не живое правило.

`(code, handler_version) → handler` — код Mechanic. PHP из БД не исполнять.

## 4. Ошибки

Мутирующий save **не** отвечает 200 с `valid: false`. Либо ok (лист + `actualVersion` + `validation: { valid: true, problems: [] }`), либо `ActionException`.

| Класс | Код / форма | HTTP |
|---|---|---|
| нет актора | `AUTH_REQUIRED` | как Auth |
| нет права / чужой объект | как NotFound или запрет без утечки id | 400 |
| битый JSON, лишний ключ, `gameId`, `money`/`limits.money` не на том шаге, `expectedVersion` на create | `INVALID_PARAMS` | 400 |
| trim-имя, space≠мир, смена revision на update, update без `expectedVersion` | `CHARACTER_INVALID` | 400 |
| нет мира/ревизии | `CHARACTER_NOT_FOUND` или map с RuleSpace | 400 |
| stale `expectedVersion` (не тот `actual_version`) | `CHARACTER_CONFLICT` + `currentVersion` | 400 |
| домен build (ссылка, budget, grantedBy, sheet mismatch, нет handler) | `CHARACTER_INVALID`, `problems[]` | 400 |
| хендлер бросил неизвестное | `INTERNAL` + лог | 500 |

`problems`: `{ code, message, path?, stage }`. Stages: `input`, `reference`, `mechanic`, `derived`, `requirement`, `budget`, `inventory`, `state`.

**Один валидатор:** вход §2.1 + срез → лист + `problems`. Его зовут create, update и validate-only.

- create/update при `problems` → нет записи, `CHARACTER_INVALID` + `problems`.
- validate-only → **200** `{ valid, problems, sheet? }`, без записи. Без `characterId` — право `character.create` (черновик ещё не в БД). С id — как чтение своего листа (владелец). `expectedVersion` не обязателен. Расхождение `expectedSheet` — элементы `problems`, всё ещё 200. Позже тем же методом может пользоваться Game.

## 5. Revision и lock

Одна операция — один срез RuleSpace. Не `findLatestRevision` как селектор save. Migration задаёт source и target. Тот же срез на расчёт и validation.

`expectedVersion` = optimistic lock колонки `actual_version`, не `rules_revision`. Расчёт можно вне TX. Перед write в TX: прочитать текущий `actual_version`, сравнить. Create: ключа нет; после insert `actual_version = 1`. Update: ключ обязателен. Идемпотентности create нет.

## 6. Права

`character.create`, `character.view`. Свой лист — владелец. Чужой — view **и** visibility, иначе NotFound. `character.edit` не вводить. Запись чужого листа нет. Super-admin/bypass — как User, не «все с view пишут».

Notes / visibility — отдельные операции, не moderation diff.

## 7. Storage — plan 02 (зафиксировано)

`space_id` FK `rule_space` + `rules_revision` (номер). `choices` / `sheet`. `actual_version`. notes. Видимость: `visibility_fields` JSON + `is_public`; `character_viewer.fields` JSON. Существование чужому = ≥1 поле. Не `revision_id`. Игроки игры — не Character. Подробности — [`character-plan-02.md`](character-plan-02.md).

Чтение для редактора — build; list — проекция. Game хранит свою копию approved; не FK на прошлый Character.

SmartTable only.

## 8. Не в C1

HTTP create/update, Vue-смена API, Game, migration, Engine, persist без validation. C1: модуль, карты, Repository. Discussion chat — после типа Chat; C5 не блокировать.

Ответ save (C5): лист с сервера + `actualVersion`, не эхо клиента. HTTP `character.validate` — тот же валидатор.

## Acceptance

Закрыто, если согласованы §1–6 (включая `studyPairId`, customRules, `active`, общий валидатор). C5 не `IMPLEMENTED` без C3+C4 и этого валидатора. Спеки без отмашки не готовы.

## Не входит / сознательно позже

- PHP, DDL, HTTP C1, план Engine Mechanic, EventManager.
- Заморозка `limits.os` / `limits.or` после первого ready (сейчас их можно менять на update). `limits.money` уже заморожен после create.
- Смена владельца, merge персонажей.
- Алгоритм слотов/рук — C4; поле `equipped` уже во входе.
