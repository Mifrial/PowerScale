# План Mechanic 3 — Vue-модуль `Roleplay/Mechanic`

**Статус:** сделано, 2026-09-07. Нарезка Vue — [`vue-rule-split-plan-01.md`](vue-rule-split-plan-01.md) заход 2. HTTP уже в PHP Mechanic ([`mechanic-plan-02.md`](mechanic-plan-02.md)). Эталон переезда каталога — [`keyword-plan-03.md`](keyword-plan-03.md). Канон рёбер — [`architecture.md`](architecture.md). Фронт — `draft-front_1.2ds/frontend-rules.md`. `DEC-062`, `DEC-064`.

Цель: каталог механик, админка `/admin/mechanics` и Vue-Engine живут в **`src/modules/Roleplay/Mechanic/`**. Rule хранит `mechanicId` / `mechanicPayload` и валидирует ссылки, но не владеет HTTP, стором, страницами и шиной событий. **Mechanic ↛ Rule** (как PHP). Не PHP Engine. Не HTTP правил. Не смена URL. Не seed. Не сужение публички спек Rule.

После влива `CODE_GAP` папки Mechanic снят. PHP-хендлеры по-прежнему OPEN ([`rule-roadmap.md`](rule-roadmap.md) «Позже»).

Цикла Character ↔ Mechanic нет: Character **вызывает** Engine со снимком; Mechanic не импортирует Character. `resolveActive` принимает `MechanicBinding[]`, не `Rule[]`.

## Термины

| Термин | Смысл |
|---|---|
| Каталог Mechanic | Строка: `id`, `code`, `name`, `description`, `version`. |
| Ссылка на правиле | `Rule.mechanicId` + `Rule.mechanicPayload`. Хозяин — Rule. Тип payload — Mechanic. |
| Binding | Узкий срез для Engine: `ruleCode`, `mechanicId`, `mechanicPayload`. Не весь `Rule`. |
| Донор хендлера | Модуль, который **регистрирует** хендлер в реестр Mechanic; Engine доноров не импортирует. |
| `RevisionFileMechanic` | Вид файла ревизии в RuleSpace. Не Dto каталога. |

## Решения

### 1. Папка и публичка

Новый модуль:

```text
Roleplay/Mechanic/
  init.ts
  routes.ts
  Interface/IMechanicApi.ts
  Interface/MechanicHandler.ts
  Dto/Mechanic.ts
  Dto/CreateMechanicData.ts
  Dto/UpdateMechanicData.ts
  Dto/MechanicPayload.ts
  Dto/RollMechanicPayload.ts
  Dto/RollScoreAdjustPayload.ts
  Dto/MechanicBinding.ts
  Dto/ResolveActiveOptions.ts
  Dto/ResolvedMechanic.ts
  Dto/MechanicState.ts
  Dto/CharacterMechanicContext.ts
  Service/MechanicApi.ts
  Service/MechanicEngine.ts
  Service/MechanicHandlerRegistry.ts
  Service/Instance/mechanicHandlerRegistry.ts # один экспорт: реестр
  Service/Instance/mechanicEngine.ts          # регистрирует purchase, new MechanicEngine(registry)
  Service/Handler/PurchaseSurchargeHandler.ts
  Constant/PURCHASE_SURCHARGE_EVENT.ts        # сейчас второй экспорт файла хендлера — разрезать
  Store/mechanics.ts
  Composables/useMechanics.ts
  Composables/useMechanicList.ts
  Composables/useMechanicEdit.ts
  Page/MechanicsListPage.vue
  Page/MechanicEditPage.vue
  Constant/Grid/mechanics/columns.ts
  Constant/Grid/mechanics/filterFields.ts
  Constant/Permission/MECHANIC_PERMISSION_CATEGORY.ts
  Constant/Permission/MECHANICS_ADMIN_SECTION.ts
  Constant/ROLL_EVENTS.ts
  Mock/mockMechanics.ts
  Mock/mockMechanicApi.ts
  __tests__/Store/mechanics.test.ts
  __tests__/Service/mechanicEngine.test.ts
```

Имена классов, Pinia id `mechanics`, actions `mechanic.*` — без смены.

`init.ts` публикует только:

- `registerMechanicApi` / `getMechanicApi`
- `useMechanics` (узкий фасад; **не** `useMechanicStore`)
- `mechanicEngine`
- `MechanicEngine` / `MechanicHandlerRegistry` (классы: продукт модуля; `rollEngine.test` собирает изолированный реестр, чужой `Service/` ему нельзя)
- `registerMechanicHandler` (доноры)
- `registerMechanicModule`
- `ROLL_EVENTS`, `PURCHASE_SURCHARGE_EVENT`

Тип `Mechanic` / `MechanicPayload` / `MechanicBinding` соседи берут из `Dto/`, не баррель.

Locator: **`Roleplay.Mechanic.Service.MechanicApi`**. Старый ключ `Roleplay.Rule.Mechanic.Service.MechanicApi` не оставлять и не алиасить.

Vue Mechanic → Engine, UI, User (плагин админки). **Mechanic ↛ Rule / Keyword / RuleSpace / Character / Game.** PHP Mechanic ↛ User сверх уже сделанного HTTP не трогаем.

Свои тесты Engine импортируют классы прямым путём внутри Mechanic. Сосед (Game) — только `init`.

### 2. Binding, не `Rule[]`

`resolveActive(bindings: MechanicBinding[], mechanics: Mechanic[], options?: ResolveActiveOptions)`.

```text
MechanicBinding:
  ruleCode: string
  mechanicId: number | null
  mechanicPayload: MechanicPayload | null
```

`extraRuleCodes` остаётся кодами **правил** (пер-ролл), не тип `Rule`.

Кто держит `Rule[]` мапит сам. Вызовов `resolveActive` ровно три: `CharacterEditorService`, `RollEngine`, `ActionExecutionService`. Хелпера в Mechanic с типом `Rule` нет. Отдельный Rule-сервис «toBindings» не плодить.

`includeCodes` — коды **семейства механики** (`Mechanic.code`, напр. из `sub_mechanics` payload «Бросок»), не `ruleCode`. Binding кода механики не несёт: Engine резолвит `mechanicId` → строка каталога.

Тесты Engine собирают Binding-фикстуры, не `Rule`.

### 3. Хендлеры и доноры

Движок не знает семантику. Свои хендлеры Mechanic вешает Instance; доноры — `registerMechanicHandler`.

| Хендлер | Дом | Почему |
|---|---|---|
| `purchase_surcharge` | Mechanic | контекст `CharacterMechanicContext` / `MechanicState` — снимок для Engine, не Dto Character |
| `six_one_rule` / `critical_strike` + `rollScoreAdjust` | **Game** | `RollMechanicContext` тянет `AdvantageModifier` (Rule). Mechanic ↛ Rule; Rule ↛ Game |
| `advantage_disadvantage` | **Game** | то же: событие броска; сервисы агрегации/drop Game уже берёт с публички Rule |
| `movement_state` | **Game** | контекст действия игры |

Umbrella [`vue-rule-split-plan-01.md`](vue-rule-split-plan-01.md) §4 ставил advantage донором Rule — **ошибочно**: хендлер типизирован контекстом броска. Rule не может импортировать Game-контекст. Копию `AdvantageModifier` в Mechanic не делать.

Переезд в Game:

- `RollMechanicContext`
- `MovementStateMechanicContext`
- `RollAdvantageHandler`, `RollSixOneHandler`, `RollCriticalStrikeHandler`
- `RollScoreAdjustService` (не файл функций `rollScoreAdjust.ts`)
- `MovementStateMechanic`

`ROLL_EVENTS` — Constant Mechanic (шина). Game импортирует из Mechanic `init`.

В Game после переезда (пути):

```text
Game/Dto/RollMechanicContext.ts
Game/Dto/MovementStateMechanicContext.ts
Game/Service/RollScoreAdjustService.ts
Game/Service/Handler/RollAdvantageHandler.ts
Game/Service/Handler/RollSixOneHandler.ts
Game/Service/Handler/RollCriticalStrikeHandler.ts
Game/Service/Handler/MovementStateMechanic.ts
```

`AdvantageDropService` / `aggregateSourceDeltasService` остаются в Rule; Game зовёт через `Rule/init`, как сейчас RollEngine.

`STRIKE_PROCEDURE_*` / `HIT_PROCEDURE` / `INJURY_PROCEDURE` — коды **контента правил**, остаются в Rule. `strikeV1` уже в Game, в реестр Engine не входит.

Не все ветки `MechanicPayload` — хендлеры Engine. `injury_efficiency` / `exhaustion_wound` читает `DamageTypeHooksService` с правила, без `runEvent`. `type: 'roll'` — дефолты броска в `RollEngine.resolveDefaults`. Их не регистрировать в registry.

`registerGameModule` **может** вызвать `registerMechanicHandler` для тех же четырёх (идемпотентная перезапись ключа). Это не единственная точка: см. синглтон ниже. `registerRuleModule` хендлеры механик **не** регистрирует.

Регистрация на синглтоне:

- `purchase_surcharge` — при создании Mechanic Instance (свой модуль). Иначе Character-тесты, которые не зовут `registerMechanicModule`, потеряют доплату.
- игровые четыре — при **импорте** Game-кода, который дергает Engine, не в `main.ts`. `hitRollService` / `checkRollService` / `injuryRollService` и их тесты зовут синглтон `rollEngine` → `mechanicEngine`. `registerGameModule` в этих тестах нет (и `main.ts` к моменту импорта `Game/init` ещё не выполнял `register*Module`).
  - три хендлера броска — в `Game/Service/Roll/Instance/rollEngine.ts` (до `new RollEngine(...)`);
  - `movement_state` — в **классе** `ActionExecutionService.ts`, не в `Service/Instance/actionExecutionService.ts` (тот уже импортирует `getGameApi` из `Game/init` — не добавлять туда Mechanic и не замыкать `Game/init` ↔ Instance).
- Порядок `registerMechanicModule()` до `registerGameModule()` в `main.ts` — про секцию админки User, не про реестр хендлеров (он наполняется импортом Instance).
- Instance Mechanic **не** импортирует хендлеры Game. Сейчас `mechanicEngine.ts` экспортирует и registry, и engine — разрезать на два файла (один экспорт).

### 4. Регистрация каталога

`main.ts`:

- `registerMechanicApi` / `registerMechanicModule` из `Mechanic/init`;
- mock/real `MechanicApi` из папки Mechanic;
- `registerMechanicModule()` рядом с Keyword/Rule, не внутри Rule.

`registerMechanicModule`:

- `registerPermissionCategory(MECHANIC_PERMISSION_CATEGORY)`
- `registerAdminSection(MECHANICS_ADMIN_SECTION)`
- опционально повторный `registerMechanicHandler(purchaseSurchargeHandler)` (уже в Instance)

`registerRuleModule` больше не трогает категорию `mechanic` и секцию «Механики»; не экспортирует `getMechanicApi` / `mechanicEngine` / `MechanicEngine` / `MechanicHandlerRegistry` / хендлеры / `ROLL_EVENTS` / `PURCHASE_SURCHARGE_EVENT`.

Character берёт `PURCHASE_SURCHARGE_EVENT` из `Mechanic/init`.

### 5. URL и склейка admin

Пути **`/admin/mechanics`**, `new`, `:id/edit` и имена роутов `Mechanics` / `MechanicNew` / `MechanicEdit` без смены. `requiresAny` как сейчас.

`Mechanic/routes.ts` — `adminChildren` только mechanics.

`Roleplay/routes.ts`:

```text
roleplayAdminChildren = [
  ...keywordAdminChildren,
  ...mechanicAdminChildren,
]
```

`Rule/routes.ts` после выреза — только `ruleCtxChildren` (admin mechanics нет). Пустой `adminChildren` у Rule не оставлять.

Оболочка `/admin` User не переезжает (`DEC-062`).

Страницы: UI в `.vue`; загрузка/сохранение/грид/навигация — `useMechanicList` / `useMechanicEdit` (как Keyword). `canCreate` на списке остаётся на странице.

### 6. Поверхность: стор чужим нельзя

Чужой модуль — публичка `init` (§1), плюс `Dto/` / `Interface/` / `Mock`. Не `Store/` / `Service/` / `Page/`.

`useMechanics` — список каталога + `fetchMechanics` / `error` (`computed`). CRUD стора только свои страницы.

Сейчас `getMechanicApi` снаружи: RuleEdit/Detail, CharacterSheetEditor, useCharacterCardDraft, Game страницы/провайдеры чата, RuleSpace Instance catalog/sync. Тесты Game чата: `registerMechanicApi` с Rule init — перевести на Mechanic. После переезда Vue — `useMechanics` или `getMechanicApi`; конструкторы RuleSpace — `getMechanicApi` из Mechanic.

Реэкспорта из `Rule/init` **нет**.

Фикстуры каталога (`mockMechanicApi`, тесты файла ревизии, `injuryRollService.test`) — **`Mechanic/Mock/mockMechanics`**. `mockRules.ts` каталог механик **не** импортирует: только числовые `mechanicId` / payload на правилах.

### 7. DAG потребителей

Импорт `Dto/Mechanic`, `MechanicPayload`, `IMechanicApi`, `useMechanics`, `getMechanicApi`, `mechanicEngine`, mock — ребро на Mechanic.

| Модуль | Зачем | Канон |
|---|---|---|
| Rule | `MechanicPayload` на Rule DTO; `useMechanics` / `getMechanicApi` в редакторе/карточке | уже Rule → Mechanic |
| RuleSpace | `getMechanicApi` catalog/sync; `Dto/Mechanic` и **`MechanicPayload`** (`RevisionFileService`) | **добавить** Vue RuleSpace → Mechanic (публично). PHP RuleSpace ↛ Mechanic не меняем |
| Character | `getMechanicApi`, `mechanicEngine`, `Mechanic` / `MechanicState` / `CharacterMechanicContext` | **сейчас** Character → Mechanic (снять «Позже») |
| Game | каталог, Engine, Binding-маппинг, донор хендлеров, `ROLL_EVENTS` | **сейчас** Game → Mechanic (снять «Позже») |

Полный список путей — grep `Roleplay/Rule/Dto/Mechanic`, `getMechanicApi`, `mechanicEngine`, `mockMechanics`, `useMechanicStore`, `ROLL_EVENTS`, `RollMechanicContext`. Не копировать в план.

Keyword ↛ Mechanic. Mechanic ↛ Keyword.

### 8. Что не переезжает

В Rule: `Rule.mechanicId` / валидация ссылок; селекторы mechanic в редакторах правил; `HIT_PROCEDURE` / `STRIKE_PROCEDURE` / `INJURY_PROCEDURE` / `DAMAGE_TYPE_HOOKS`; `AdvantageModifier`; `AdvantageDropService`; спеки.

В RuleSpace: `Dto/RevisionFileMechanic.ts`, `RevisionFileMechanicRef.ts`.

В Game после переезда: контексты броска/движения и донорские хендлеры (§3).

### 9. Docs этого захода

- `architecture.md`: снять CODE_GAP Vue Mechanic; Character/Game → Mechanic; Vue Mechanic → User; Vue RuleSpace → Mechanic; строка «Позже — Mechanic» у Character/Game убрать.
- `TR.md`: ссылка на этот файл (план 2 — HTTP).
- `vue-rule-split-plan-01.md`: todo mechanic/rule-init/gates/docs; таблица хендлеров как §3 (advantage = Game).
- `rule-roadmap.md`: Vue Mechanic вынесен; пункт «Вынос Vue Mechanic» закрыть; PHP Engine остаётся «Позже».
- `mechanic-plan-02.md`: одна строка «Vue-папка — план 3», историю HTTP не переписывать.

### 10. Тесты и гейт

Перенести `Rule/__tests__/Store/mechanics.test.ts` и `mechanicEngine.test.ts`. `registerMechanicApi` с нового init. Engine-тесты — Binding. Игровые хендлеры — тесты в Game (перенести куски, которые бьют в six_one/advantage/movement).

Поправить импорты Character / Game / RuleSpace / Rule.

Eslint internals: Game/Character не импортируют `Mechanic/Store`; можно `Mechanic/init` и `Dto/Mechanic`. `Roleplay/routes.ts` — импорт `Mechanic/routes` допустим. Добавить кейс как у Keyword.

Не phpunit. Не mount Vuetify.

Гейт: в `draft-front_1.2ds` — `npm run format` → `npm run lint` → `npx vue-tsc --noEmit` → `npm run test`. Dev-сервер не запускать.

## Порядок работ (один заход)

1. Папка каталога + locator + `init`/`routes` + composable страниц (как Keyword).
2. Binding + перенос Engine/registry/`purchase_surcharge` + тесты Engine.
3. Хендлеры броска/движения в Game; регистрация при импорте `rollEngine` / класса `ActionExecutionService` (§3).
4. Склейка admin; вырезать mechanics из Rule routes/init.
5. `main.ts`; потребители на публичку Mechanic.
6. Eslint + vitest затронутого, затем полный гейт.
7. Документы §9.

Не делить «сначала только админка»: Engine в Rule — причина, почему сплит откладывали.

## Что не входит

- PHP Engine / hydrator payload на сервере.
- HTTP `rule.*`, seed справочника.
- Вынос `/admin/mechanics` из оболочки User.
- DELETE/`active` у механики.
- Сужение публички спек Rule (`CharacteristicNumber` и т.д.).
- Модуль `Reference`.
- Перенос `AdvantageDropService` в Mechanic.

## Риски

1. Оставить `resolveActive(rules: Rule[])` — Mechanic снова знает Rule.
2. Реэкспорт Engine из `Rule/init` «для совместимости» — заход фиктивный.
3. Два ключа локатора: RuleSpace Instance и стор Mechanic смотрят в разные.
4. Забыть донора Game на **синглтоне**: `rollEngine.test` изолирован, а `hitRollService.test` и соседние бьют в `rollEngine` Instance.
5. `RollMechanicContext` оставить в Mechanic вместе с `AdvantageModifier` — ребро Mechanic → Rule.
6. Тесты файла ревизии / `injuryRollService.test` без смены пути `mockMechanics`. `mockRules` трогать только если меняется форма payload.
7. CharacterEditorService: конструктор `mechanicEngine` с нового init, не боковой импорт Service.
8. Character-тесты без `registerMechanicModule`: purchase должен быть на синглтоне с импорта Instance, не только из `main.ts`.
9. `PurchaseSurchargeHandler.ts` сейчас два экспорта (событие + хендлер) — после переезда два файла.
10. `rollEngine.test` импортирует `MechanicEngine` / `MechanicHandlerRegistry` / хендлеры броска: после переезда классы из Mechanic `init`, хендлеры из Game.
12. Вешать Game-хендлеры только в `registerGameModule` / `main.ts` — тесты hit/check/injury смотрят в пустой синглтон.
13. Класть регистрацию `movement_state` в `actionExecutionService.ts` Instance — риск цикла `Game/init` ↔ Instance (`getGameApi`).
14. Регистрировать в Engine payload `injury_efficiency` / `exhaustion_wound` / `roll` — это не хендлеры `runEvent`.

## Todo

- [x] **folder** — дерево §1, locator, init, routes, страницы+composable.
- [x] **engine** — Binding, registry, purchase_surcharge, тесты.
- [x] **donors** — Game хендлеры + `RollScoreAdjustService` + регистрация при импорте `rollEngine` / ActionExecution, не только `registerGameModule`.
- [x] **wire** — main, Roleplay admin concat.
- [x] **consumers** — Rule / RuleSpace / Character / Game: публичка Mechanic, не Store.
- [x] **tests** — store + engine + eslint internals + импорты.
- [x] **docs** — §9, CODE_GAP снят.
