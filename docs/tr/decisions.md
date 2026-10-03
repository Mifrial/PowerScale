# Актуальные решения

**Статус:** канонический реестр решений, 2026-08-30.

Полные записи находятся в [`../review/tr-decisions-2026-08.md`](../review/tr-decisions-2026-08.md). Этот реестр не заменяет их, но перечисляет каждый действующий ID и его текущий смысл. Решения без отдельной пометки считаются `current`; незавершённые вопросы имеют статус `OPEN`, отложенные — `DEFERRED`, будущие исправления кода — `BACKLOG`.

## Supersession и affected contracts

- `DEC-019` — `HISTORICAL` для внешней стратегии ID; owner `decisions.md`; superseded by `DEC-058`, который оставляет numeric IDs каноническими и допускает thematic aliases.
- `DEC-036` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: `pending` больше не является целевым projection.
- `DEC-037` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: approved snapshot переименован и уточнён как `approvedCharacterVersion`.
- `DEC-038` и `DEC-051` уточняют moderation concurrency, owner `character-system.md`.
- `DEC-040` уточняет validation envelope, owner `rule-system.md`/`character-system.md`.
- `DEC-052` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: projection token A/L/O/P больше не является целевым контрактом.
- `DEC-059` остаётся действующим в части membership и approved snapshot; его утверждения о полном Character sheet в `gameOverlay`, immutable actual во время session и stop-time commit superseded решением `DEC-084`. A/L/O/P mapping остаётся исторически superseded. Канон: `actualCharacter` + `approvedCharacterVersion` + Game-owned `gameOverlay/gameState`; owner `character-system.md`/`game-system.md`.

Эти записи фиксируют связь решения и canonical owner; явно помеченные `HISTORICAL/SUPERSEDED` решения заменены указанным новым решением.

## Архитектура, правила и пространство

- `DEC-001` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: membership ранее использовал A/L/O/P.
- `DEC-002` — каноническая модель ресурса: `auto_add`, `limit.base`, `limit.adjustments`.
- `DEC-003` — сообщения используют `ChatAttachment[]`.
- `DEC-004` — `points` является отдельным типом правила.
- `DEC-005` — ссылки на правила используют семантические `*_code`.
- `DEC-060` — публичные поля ссылок на правила используют явные `*_code`-имена (`ruleCode`, `raceRuleCode`, `sourceRuleCode`); `Rule.id` — `number | null`, только storage key. Реализовано 2026-08-31.
- `DEC-061` — loot имеет статусы `prepared | available | distributed`; интерес игрока хранится отдельно в `game_loot_interest`.
- `DEC-062` — `keyword` — технический термин; пользовательские UI-тексты используют «признак»/«Признаки», не «тег».
- `DEC-063` — Chat sync публикует `ok`/`retrying`, сохраняет cursor при ошибке и ретраит с backoff 1s–30s плюс ручной «Повторить»; `markChatRead` в этот контракт не входит (ack прочтения — в chat-system).
- `DEC-064` — чужой модуль видит `init`, Dto, Interface, Enum (и с `DEC-082` — Constant, Value, Mock, routes); чужие Store/Service/Utils нельзя; статический чужой Component нельзя; Pinia не в локаторе; `useXxxStore` из `init` не реэкспортировать; локатор только в корне сборки и в `init.ts`; доменные `Service/` получают порты в конструктор.
- `DEC-065` — `DEC-064` для всех прикладных модулей, включая Auth и User; исключения — Core/Engine, Core/UI и регистрация плагина через публичный API хоста. Таблица рёбер — [`architecture.md`](architecture.md).
- `DEC-066` — UI использует adapter boundaries, batch lookup и необязательный `AbortSignal` для отмены устаревших async-запросов.
- `DEC-067` — в Game-ТР фиксируются только минимальные combat/session contract cards; исключение A/L/O/P-модерации и three-way reconcile сохраняется, а его прежнее описание full-sheet overlay superseded `DEC-084`.
- `DEC-068` — `EconomyOperation` остаётся backend/domain requirement; frontend economy `NOT IMPLEMENTED`, а `distributeLoot` — отдельный loot flow.
- `DEC-006` — scoped numeric `revision`, пара `(spaceId, revision)`, immutable `publishedAt`.
- `DEC-007` — разделение state, poison, feelings и age.
- `DEC-008` — lifecycle-статусы персонажа не заменяют validation.
- `DEC-009` — навигация Game организована вкладками.
- `DEC-010` — каноничен полный список `RuleType`.
- `DEC-015` — выборочная публикация правил сохраняется как требование.
- `DEC-017` — канонический термин для признака правила: `keyword`.
- `DEC-018` — летопись использует модель `GameTime`.
- `DEC-019` — историческое требование тематических ID; superseded для внешних ссылок решением `DEC-058`, тематические ID остаются дополнительными alias.
- `DEC-020` — отдельные верхнее меню и footer не являются текущим layout-контрактом.
- `DEC-021` — однозначные редакционные ошибки ТР исправляются при финальной миграции.
- `DEC-022` — имя справочника признаков: `keywords`.
- `DEC-026` — абстрактное движение реализовано; battleground — отдельный контур (`DEC-069` принимает requirement-канон).
- `DEC-069` — `battleground-system.md`: логическая модель сцены `REQUIREMENT`, реализация `NOT_IMPLEMENTED`, persistence `OPEN`.
- `DEC-070` — PHP DI: процесс собирает `Application`; локатор каталогизирует контейнеры (в том числе ленивые слоты); `ModuleManager` не держит локатор. Owner [`architecture.md`](architecture.md). Порты Kernel для соседей — `OPEN`.
- `DEC-071` — PHP quality markers: complexity и архитектурные проблемы — ошибки анализа; очевидные исправления делает агент, обоснованные исключения запрашиваются точечно, глобальные отключения запрещены. Owner [`architecture.md`](architecture.md).
- `DEC-072` — PHP-правила отделены от фронтенд-правил и собраны в `docs/tr/php-coding-standards.md`; корневой `AGENTS.md` ссылается на них при работе с бэкендом. Обязательны PHPDoc типов и методов, порядок методов и class quality markers. Owner [`architecture.md`](architecture.md).
- `DEC-077` — фронт: JSDoc обязателен у `class`, у функций вне `.vue` — по возможности (не гейт). PHP: PHPDoc типов и методов. Owner `frontend-rules.md` / [`architecture.md`](architecture.md).
- `DEC-073` — `mifrial/init.php` является чистым bootstrap; API имеет отдельные entrypoint’ы в `mifrial/API/`, а корневой `www/index.php` не является API-контроллером. Owner [`architecture.md`](architecture.md).
- `DEC-074` — параметры action: JSON-объект биндится на имена `handle` **или** на конструктор единственного `IActionInput`; лишние поля и несовпадение типа — `INVALID_PARAMS`. Owner [`architecture.md`](architecture.md).
- `DEC-075` — Action возвращает данные, не `ActionResponse`; доменная ошибка — `ActionException` с кодом. Owner [`architecture.md`](architecture.md).
- `DEC-076` — Kernel: неймспейс `Mifrial\Core\Kernel`, CSRF double-submit, HTTP-статусы, `debug`+trace, `ILogger`, свои исключения, `ApplicationFactory`. Owner [`architecture.md`](architecture.md).
- `DEC-078` — SmartTable: `illuminate/database` без Eloquent и без Laravel-приложения; Basic; админка после Auth; тегированный кэш и runtime-DDL в v1. Версионность вынесена в `DEC-080`. Owner [`smarttable.md`](smarttable.md).
- `DEC-080` — версионность: ленивый `Versioning/Space` (не Core, не оболочка ST). Один репозиторий — свои пространство-время; состав ревизии материализован; срез без нового SQL. `Roleplay/RuleSpace` — оператор правил. Связка нескольких репозиториев — выше. Owner [`versioning-roadmap.md`](versioning-roadmap.md).
- `DEC-081` — драйвер кэша `Core/Cache` (`ICacheStore`); TTL 1..30 суток, без «навсегда». ST `TableCache` и Versioning — политики ключей/тегов. Owner [`cache-plan-01.md`](cache-plan-01.md).
- `DEC-082` — на фронте `Constant/` и `Value/` публичны как Dto (прямой путь, не баррель `init`); `DEC-064` в части «не Constant» сужен, не отменён. Динамический чужой `Component/*.vue` разрешён. Owner [`architecture.md`](architecture.md), [`rule-plan-02-init-surface.md`](rule-plan-02-init-surface.md).
- `DEC-079` — PHP Record: геттеры смысла; New/Patch — карта ключей; JSON-вид отдельно. Owner [`php-coding-standards.md`](php-coding-standards.md) / [`user.md`](user.md).
- `DEC-083` — backend EventManager живёт в отдельном `Core/Event`; публичный порт — `IEventManager` с синхронными `on`/`off`/`fire`, process-local token subscriptions, priority/FIFO и read-only `IEventPayload`. Event identity — полный проверяемый формат `ModuleGroup\ModuleName.Subject::LifecycleOperation`; `EventResult` агрегирует errors, warnings, output payloads и explicit stop/failure status custom result. Token ownership — process-local API-инвариант, не security boundary. Sync MVP transaction-agnostic и не владеет Mail, Agent, Logger, SmartTable или queue. Async queue, afterCommit, outbox и DB-driven subscriptions — `DEFERRED`. Owner [`architecture.md`](architecture.md), [`../specs/event-manager-backend-plan.md`](../specs/event-manager-backend-plan.md). Character/Game outbox integration не входит в sync MVP.
- `DEC-027` — старый план Chat → Game перенесён в историю.
- `DEC-028` — готовность RuleType оценивается независимо по доменной модели, frontend, backend и контенту.

## Character, Game и combat

- `DEC-011` — канонична реализованная боевая карточка.
- `DEC-012` — wide attack `1 → N` реализована; `N → 1` остаётся backlog.
- `DEC-013` — прямые импорты внутренних Game-файлов в Rule/Character исправляются архитектурным рефакторингом.
- `DEC-014` — JSON-клонирование заменяется на `structuredClone`, где возможно.
- `DEC-016` — session/battle/process изменения идут в единый Game overlay; часть о полном Character sheet внутри overlay и его commit в actual superseded `DEC-084`.
- `DEC-024` — validation структурированная и вычисляемая, не lifecycle-статус.
- `DEC-025` — инвентарь является обязательным игровым контуром.
- `DEC-029` — `ActionEffect` является рабочим частичным каноном.
- `DEC-030` — текущий канон проверок и боя включает реализованные сценарии, а не незавершённую матрицу.
- `DEC-036` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: pending projection заменена сравнением approved snapshot и actual character.
- `DEC-037` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: approved snapshot хранится в новой membership-модели.
- `DEC-038` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: moderation использует diff approved/actual и атомарный optimistic guard.
- `DEC-040` — validation использует `valid` и структурированный `problems[]`.
- `DEC-041` — `npc.version` остаётся authoritative для NPC; player money/inventory/loot mutations во время игры идут в actual при применении authoritative effect, а Game overlay хранит только process/battle state. Уточнено `DEC-084`.
- `DEC-043` — ActionEffect и проверки фиксируются как рабочая частичная реализация.
- `DEC-051` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: optimistic guard применяется к approved/actual.
- `DEC-052` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: отдельный projection token A/L/O/P не является целевым контрактом.
- `DEC-057` — деактивация пользователя выполняется диалогом внутри `UserProfilePage`; отдельного маршрута нет.

## Магия и контент

- `DEC-023` — полноценная реализация заклинаний обязательна до релиза.
- `DEC-031` — источник магии — отдельная характеристика/контур.
- `DEC-032` — путь выбирается при каждом касте и не хранится как путь изучения заклинания.
- `DEC-033` — магический контент до релиза должен быть выгружен и отображаться.
- `DEC-039` — список runtime-механик определяется после реальной выгрузки (`DEFERRED`).
- `DEC-042` — критерий готовности типов правил состоит из четырёх независимых статусов.
- `DEC-046` — runtime покрывает репрезентативный набор механик, остальные заклинания отображаются.
- `DEC-053` — общий редакционный `contentStatus` применим ко всем правилам.
- `DEC-054` — `contentStatus` — расширяемый enum `broken | needs_work | ready`.
- `DEC-055` — runtime-support хранится независимо от `contentStatus`.
- `DEC-056` — классификация источников, путей, веток и навыков отложена до выгрузки (`DEFERRED`).

## Экономика

- `DEC-034` — деньги после создания являются балансом, а `moneyBudget` — историческим стартовым бюджетом.
- `DEC-035` — магазин хранит цену покупки, опциональную `sellPrice` и остаток.
- `DEC-044` — базовая цена берётся из `ItemSpec.cost_gm`, валюта хранится в минимальных единицах.
- `DEC-045` — ведущий настраивает права/режимы; разрешённые операции не требуют ручного approve.
- `DEC-047` — все экономические действия журналируются immutable append-only журналом.
- `DEC-048` — Game state/overlay authoritative для gameplay state, а actual Character/NPC authoritative для persisted sheet effects; журнал не является полным event-sourcing источником. Уточнено `DEC-084`.
- `DEC-049` — операции используют DB-транзакцию и optimistic version check.
- `DEC-050` — каноничен typed `EconomyOperation` с idempotency key и ожидаемыми версиями.
- `DEC-058` — числовые `DEC-001`—`DEC-057` каноничны; тематические ID из `DEC-019` — только дополнительные alias.

- `DEC-084` — `actualCharacter` является единственным persisted player sheet и изменяется authoritative Character/Game effects; `approvedCharacterVersion` остаётся immutable moderation baseline, а Game overlay ограничен session/battle/process state. `states`, wounds, injuries, poison, resources, inventory, equipment, money и loot являются частью actual; NPC имеет только `npc.version` и технический `npc.actual_version`. `changes_pending` блокирует следующую session, но не активного participant; `canStartSession` и `isActiveSessionParticipant` разделены. Approve допускается во время active session через CAS actual + membership revision. `endBattle` не завершает session и не запускает approve; stop очищает transient state без полного Character commit. Multi-entity Game commands владеют outer transaction, Character/NPC storage вызывается через transaction-bound ports. Command response и SSE разделены; idempotency records принадлежат владельцу команды. R3-FE может добавить opt-in typed snapshot/mock lifecycle boundary, но не объявляет backend durable state, transaction, SSE или recovery готовыми. Полная запись — [`../review/tr-decisions-2026-08.md`](../review/tr-decisions-2026-08.md).
- `DEC-084` implementation note — R4-FE может подготовить opt-in `IGameApi.submitCombatCommand` для single-target attack/defense mock vertical slice. Он принимает только decisions и expected Game/entity versions, применяет Character/NPC actual только при authoritative effect, хранит idempotency/process fixtures и не переводит backend transactions, SSE, Chat delivery, read projections или legacy `GameCombatOverlay` в готовую реализацию.
- `DEC-085` — R6-FE является frontend/mock readiness boundary: standalone и in-game Character editor используют один typed patch/CAS/idempotency flow; `CharacterChanged` публикуется только после успешной mutation; Game realtime использует numeric cursor и `eventId = <gameId>.<cursor>`, targeted projections и bounded snapshot fallback. Это не подтверждает production EventManager/outbox/SSE, backend visibility/authorization или удаление `GameCombatOverlay`. Owner [`character-system.md`](character-system.md) / [`game-system.md`](game-system.md).
- `DEC-086` — контракт spec для валидатора листа Character C4, 2026-10-02. Не закрывает модуль Rule, HTTP Rule, набор `RuleType` целиком и не объявляет Vue-черновик каноном. Новые расы, черты и заклинания — поправка этой записи, не повторное ожидание каталога. Адаптер листа имеет право читать только перечисленные поля. Поведение TS из запретов ниже в валидатор не копировать. Owner [`character-roadmap.md`](character-roadmap.md) / [`character-plan-01.md`](character-plan-01.md).
  - Типы: `ability`, `race`, `species`, `age`, `magic_path`, `weapon_family`, `script`, `item`. `item_modifier` и прочие типы `rule-system.md` этот валидатор не закрывает.
  - `ability`: дискриминатор `type` (`group` | `spell` | остальное) без тела заклинания, `action_components`, `spell` и процесса; `zones` и цена уровня (`kind`, `levels_cost`, `max_level`, `base_cost`, `step`, `parameter_code`, `per_unit`, `costs`, `tables`); `domain_ref` только `magic-path` | `weapon-family` | `script`; `multiple`, `parent_ability_code`; `parameters` (`code`, `kind`, `min`, `max`, `default`); `grants[].level` и у вложенного гранта `type`, `permanent`; грант `ability` — `ability_code`, `level`; грант `magic_path` — `path_code`; грант `magic_study` — `scope`, `max_cost`, `path_code`, `max_instances`, `paid_cost`; грант `skill_study` — `ability_codes`, `max_level`, `paid_cost`, `max_instances`; грант `money` — `fixed`, `percent`, `apply`; чтобы не принять derived за покупку — `zones.kind = automatic`, `derived_level`, `aggregate`.
  - `race`: `cost_os`; `characteristics[]` — `characteristic_code`, `mode`, `base`, `purchase[].cost`, `purchase[].value`; `age_years[]` — `age`, `ageStart`, `ageEnd`; `abilities[]` — `ability_code`, `automatic`, опционально `parameters` (бесплатная база параметра, не уровень способности); `parent_race_code` — шаг к таблице лет родителя и обход предков-видов за их `abilities`.
  - `species`: `age_years[]`, `abilities[]` с теми же полями, `parent_race_code` в том же подъёме. Каталог способностей вида читается. Контент новых черт эта запись не задаёт.
  - `age`: `type`, `ages[].name`, `ages[].ol`. `ages[].effects` и `featureLimit` в потолок ОЛ не входят.
  - `magic_path`: `type`; `study_cost.discount_fraction`, `study_cost.pair_base_cost`; `includes_path_codes`. `check_code`, `cast_check_code`, `power_characteristic_code`, `control_characteristic_code` — каст, не покрытие и не пара.
  - `weapon_family`: `costs`, если `domain_ref = weapon-family`.
  - `script`: `kind` (`logographic` | иначе алфавит). Чисел лестницы в spec нет.
  - `item`, только при `equipped` и после стека модификаторов: `armor.strength_penalty`, `armor.max_agility`, `armor.characteristic_limits[]` (`characteristic_code`, `limit`), `shield.characteristic_limits[]` (те же два поля). Для денежного потолка закупки, не для надетого: `cost_gm`, `innate`.
  - Запрещено копировать из TS: пару «два за 1» по `localeCompare` и чётности индекса — пара это `studyPairId` и `studyPairRole` из C0 (ровно один `charged` и один `free`; один в группе — только `charged`); пустой `magic_study.path_code` как «покрывает всё» и подстановку пути с соседнего гранта или keyword — грант без пути не покрывает дарованные пути, покрытие это `path_code` и транзитивный `includes_path_codes`; слот `skill_study` и денежный грант «первый в массиве» — неоднозначность без указателя это `problem`; булев `gifted` вместо `grantedBy`; лестницу семьи и письменности по `rule.name` и числа алфавита из констант сервиса; имя ступени «Старый», если годы вышли из `age_years`; потолок `max_agility` как всегда `dexterity` и штраф как всегда `strength`; объявлять `equipped` проверенным по слотам, рукам и конфликту — `occupy_hands` эта сборка не читает; хардкод keyword `magic` как гейт изучения.
  - Не входит: заклинания и их боевые поля, новые расовые черты, HTTP Rule, закрытие модуля Rule. Проверки только по коду и типу (раса, ключ экземпляра, tombstone, имя, структура `customRules`, `active`, `equipped` как булев флаг) spec не читают.

## Статусы

- `CURRENT` — подтверждённый текущий контракт доменного документа;
- `REQUIREMENT` — согласованное требование, для которого backend или часть реализации ещё не подтверждены;
- `OPEN` — точные backend-таблицы и endpoint экономики, backend lifecycle статусов и физическая схема некоторых исторических доменов.
- `DEFERRED` — механики и классификация магии до реальной выгрузки.
- `BACKLOG` — кодовые исправления, явно отмеченные в доменных документах; решения сами по себе не означают их выполнения.
- `BACKEND` — отдельная метка реализации: frontend/domain подтверждены, backend-контракт или backend-реализация отсутствуют;
- `HISTORICAL` — решение заменено более поздним DEC и сохраняется только для трассировки.

Решение может одновременно иметь, например, `CURRENT + BACKEND` или `REQUIREMENT + DEFERRED`; оси и canonical owners определены в [`contract-status.md`](contract-status.md). Магия `DEC-023`, `DEC-031`—`DEC-033`, `DEC-039`, `DEC-046`, `DEC-056` не считается выгруженной реализацией до появления контентного evidence.
