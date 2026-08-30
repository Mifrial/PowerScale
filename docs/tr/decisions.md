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
- `DEC-059` supersedes A/L/O/P в части Character/Game membership: канон — `actualCharacter` + `approvedCharacterVersion` + `gameOverlay`; owner `character-system.md`/`game-system.md`.

Эти записи фиксируют связь решения и canonical owner; явно помеченные `HISTORICAL/SUPERSEDED` решения заменены указанным новым решением.

## Архитектура, правила и пространство

- `DEC-001` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: membership ранее использовал A/L/O/P.
- `DEC-002` — каноническая модель ресурса: `auto_add`, `limit.base`, `limit.adjustments`.
- `DEC-003` — сообщения используют `ChatAttachment[]`.
- `DEC-004` — `points` является отдельным типом правила.
- `DEC-005` — ссылки на правила используют семантические `*_code`.
- `DEC-060` — публичные поля ссылок на правила используют явные `*_code`-имена (`ruleCode`, `raceRuleCode`, `sourceRuleCode`); numeric `id` — только storage key.
- `DEC-061` — loot имеет статусы `prepared | available | distributed`; интерес игрока хранится отдельно в `game_loot_interest`.
- `DEC-062` — `keyword` — технический термин, в UI используется «признак»; переименование текстов — `CODE_GAP / implementation OPEN`.
- `DEC-063` — Chat sync публикует `ok`/`retrying`, сохраняет cursor при ошибке и ретраит с backoff 1s–30s плюс ручной «Повторить».
- `DEC-064` — межмодульный доступ строится на typed capability-фасадах и constructor DI; ServiceLocator/container остаются на composition root и в публичных фасадах.
- `DEC-065` — это DI и boundary-правило действует для всех прикладных модулей проекта; исключения — Core и plugin registration через public host API.
- `DEC-066` — UI использует adapter boundaries, batch lookup и необязательный `AbortSignal` для отмены устаревших async-запросов.
- `DEC-067` — в Game-ТР фиксируются только минимальные combat/session contract cards; старая A/L/O/P-модерация и three-way reconcile исключены.
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
- `DEC-026` — движение и battleground разделены.
- `DEC-027` — старый план Chat → Game перенесён в историю.
- `DEC-028` — готовность RuleType оценивается независимо по доменной модели, frontend, backend и контенту.

## Character, Game и combat

- `DEC-011` — канонична реализованная боевая карточка.
- `DEC-012` — wide attack `1 → N` реализована; `N → 1` остаётся backlog.
- `DEC-013` — прямые импорты внутренних Game-файлов в Rule/Character исправляются архитектурным рефакторингом.
- `DEC-014` — JSON-клонирование заменяется на `structuredClone`, где возможно.
- `DEC-016` — игровые изменения идут в единый Game overlay; модель слоя уточнена решением `DEC-059`.
- `DEC-024` — validation структурированная и вычисляемая, не lifecycle-статус.
- `DEC-025` — инвентарь является обязательным игровым контуром.
- `DEC-029` — `ActionEffect` является рабочим частичным каноном.
- `DEC-030` — текущий канон проверок и боя включает реализованные сценарии, а не незавершённую матрицу.
- `DEC-036` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: pending projection заменена сравнением approved snapshot и actual character.
- `DEC-037` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: approved snapshot хранится в новой membership-модели.
- `DEC-038` — `HISTORICAL/SUPERSEDED` решением `DEC-059`: moderation использует diff approved/actual и атомарный optimistic guard.
- `DEC-040` — validation использует `valid` и структурированный `problems[]`.
- `DEC-041` — экономика player во время игры идёт в overlay, NPC — в `npc.version`.
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
- `DEC-054` — `contentStatus` — расширяемый enum с `needs_work | ready`.
- `DEC-055` — runtime-support хранится независимо от `contentStatus`.
- `DEC-056` — классификация источников, путей, веток и навыков отложена до выгрузки (`DEFERRED`).

## Экономика

- `DEC-034` — деньги после создания являются балансом, а `moneyBudget` — историческим стартовым бюджетом.
- `DEC-035` — магазин хранит цену покупки, опциональную `sellPrice` и остаток.
- `DEC-044` — базовая цена берётся из `ItemSpec.cost_gm`, валюта хранится в минимальных единицах.
- `DEC-045` — ведущий настраивает права/режимы; разрешённые операции не требуют ручного approve.
- `DEC-047` — все экономические действия журналируются immutable append-only журналом.
- `DEC-048` — overlay authoritative для gameplay, журнал не является полным event-sourcing источником.
- `DEC-049` — операции используют DB-транзакцию и optimistic version check.
- `DEC-050` — каноничен typed `EconomyOperation` с idempotency key и ожидаемыми версиями.
- `DEC-058` — числовые `DEC-001`—`DEC-057` каноничны; тематические ID из `DEC-019` — только дополнительные alias.

## Статусы

- `CURRENT` — подтверждённый текущий контракт доменного документа;
- `REQUIREMENT` — согласованное требование, для которого backend или часть реализации ещё не подтверждены;
- `OPEN` — точные backend-таблицы и endpoint экономики, backend lifecycle статусов и физическая схема некоторых исторических доменов.
- `DEFERRED` — механики и классификация магии до реальной выгрузки.
- `BACKLOG` — кодовые исправления, явно отмеченные в доменных документах; решения сами по себе не означают их выполнения.
- `BACKEND` — отдельная метка реализации: frontend/domain подтверждены, backend-контракт или backend-реализация отсутствуют;
- `HISTORICAL` — решение заменено более поздним DEC и сохраняется только для трассировки.

Решение может одновременно иметь, например, `CURRENT + BACKEND` или `REQUIREMENT + DEFERRED`; оси и canonical owners определены в [`contract-status.md`](contract-status.md). Магия `DEC-023`, `DEC-031`—`DEC-033`, `DEC-039`, `DEC-046`, `DEC-056` не считается выгруженной реализацией до появления контентного evidence.
