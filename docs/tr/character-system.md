# Система персонажей

**Статус:** текущий frontend/domain канон, 2026-08-30. Backend storage и lifecycle-контракты помечены `OPEN`, если они не подтверждены кодом.

## Персонаж и версия правил

Персонаж строится по конкретной ревизии правил пространства `(spaceId, revision)` (`DEC-006`). Backend хранит одно актуальное состояние персонажа — `actualCharacter`; истории `CharacterVersion` у персонажа нет. Browser draft — временные изменения только в браузере. Одобренная игровая копия хранится отдельно в membership игры и не является историей персонажа.

Личные заметки персонажа принадлежат владельцу и привязаны к `characterId`; они не входят в `CharacterVersion` и не видны другим пользователям. Frontend-контракт использует `CharacterDetail.ownerNotes` и отдельную операцию обновления заметок. Backend storage и authorization остаются частью backend-контракта.

Расчёты характеристик, способностей, ресурсов, модификаторов, инвентаря и боевых значений выполняются доменными сервисами. Компоненты отвечают за ввод и отображение, а не за вычисление производных данных.

## Создание

Канонический flow создания включает:

1. выбор расы и вида/подвида;
2. получение наследуемых характеристик, способностей и ограничений;
3. распределение очков характеристик;
4. настройку личности и соответствующих очков;
5. настройку развития;
6. закупку стартового инвентаря по `ItemSpec.cost_gm`;
7. проверку обязательных требований и лимитов;
8. сохранение версии персонажа.

Свободное создание, отрицательные/исчерпанные очки, владение оружием и лимиты должны валидироваться доменным контуром, а не только UI.

## Редактирование и состояние

После создания персонаж может редактироваться владельцем с учётом прав и выбранной ревизии. Backend сохраняет actual character только после успешной проверки. Изменение actual character разрешено и между сессиями, и во время активной сессии: оно проходит общий Character mutation pipeline, optimistic guard и validation. В активной сессии Game получает факт успешного изменения через integration boundary; Character не импортирует Game и не отвечает за доставку в SSE или Chat.

На персонаже могут присутствовать состояния, включая раны, истощение и отравление. Poison — контент правила, а отравление в персонаже — `state`. Состояния могут иметь decay/periodicity и влиять на проверки, характеристики или бой.

Модификаторы предметов и способностей могут влиять на характеристики, проверки, атаки и защиту. Экипировка и inventory должны сохранять доменные инварианты.

## Inventory

Обязательный контур:

- просмотр и редактирование количества/свойств;
- экипирование;
- стартовая покупка;
- custom items;
- item modifiers;
- получение и выдача loot;
- передача предметов;
- перенос при миграции правил;
- влияние предметов на характеристики и бой.

Полноценные игровые покупка, продажа, discard и обмен описаны в [`game-system.md`](game-system.md). Стартовый `moneyBudget` — исторический бюджет создания, не постоянный лимит.

## Validation и lifecycle

`validation` — вычисляемый результат по персонажу и ревизии:

```text
{
  valid: boolean,
  problems: [{ code, message, path?, stage? }]
}
```

Машинным идентификатором является `code`, а не локализованный `message`.

Frontend ранее использовал `draft | ready | moderation | needs_fix`. В целевой модели `moderation` не является статусом Character, а `needs_fix` — только вычисляемый результат проверки, не значение в БД. Эти значения не заменяют серверную validation. Удаление backend lifecycle status и миграция consumers — `CODE_GAP / implementation OPEN`.

## Membership персонажа в игре

Персонаж может находиться не более чем в одной игре одновременно. Membership хранит ссылку на `characterId`, полную immutable-копию принятого состояния `approvedCharacterVersion`, review metadata, техническую revision для concurrency guard и сессионный `gameOverlay`. Overlay хранит только состояние Game/session, а не второй полный лист Character. Отдельной истории версий Character нет.

```text
GameCharacterEntry
├── gameId
├── characterId
├── status: submitted | active | left
├── approvedCharacterVersion: full snapshot copy | null
├── reviewState: clean | changes_pending | returned
├── returnedAt / returnReason / returnMessageId
├── membershipRevision: technical CAS counter
└── gameOverlay
```

`submitted` без approved snapshot — новая заявка. `active` с отличием actual character от approved snapshot — принятый персонаж с изменениями, ожидающими модерации; такой персонаж нельзя отклонить как заявку. `left` обозначает завершённое участие и не даёт права удалить уже принятое участие через reject.

## Session и overlay

Во время активной сессии `actualCharacter` остаётся единственным persisted листом персонажа. Игровое действие изменяет его только в момент применения authoritative effect; решение игрока может до этого изменить только Game process/offer state. `gameOverlay/gameState` хранит initiative, battle/process state, offers, pending effects и transient markers, но не полный `CharacterVersion`.

`approvedCharacterVersion` остаётся immutable baseline для moderation и допуска следующей сессии. Он не является источником текущего листа внутри уже начатой сессии. После успешной semantic mutation actual membership получает `reviewState = changes_pending`; это не прерывает текущего участника, но блокирует следующую сессию до approve.

Одна `playing` session может содержать несколько независимых battles. `endBattle` закрывает только текущий battle и отменяет его unresolved processes/offers; `stopGameSession` завершает всю session и очищает оставшееся transient Game state. Ни один из переходов не выполняет полный character commit, не откатывает применённые effects и не запускает approve. Если продолжается тот же battle, сохраняются его `battleId` и process state; новый battle получает новый identity.

Frontend/mock R3 может подготовить typed `GameSessionState`,
`GameBattleState` и `GameStateSnapshot`, но эта boundary не является доказательством
backend durable state. NPC использует `npc.version` как authoritative sheet и
`npc.actual_version` как единственный технический CAS counter, без approved
snapshot или player moderation lifecycle.

R4-FE может подготовить opt-in `GameCombatCommand` через Game API boundary для
решений атаки/защиты и mock authoritative effect. Decision command изменяет
только Game process/offer state; actual Character/NPC меняется только при
applied authoritative effect. Новый mock path использует Character actual и
`npc.version`/`npc.actual_version`, возвращает versions и changed entity keys,
но не переключает legacy `GameCombatOverlay`, не загружает read projections и
не является backend transaction/SSE implementation. `changes_pending` не
удаляет active participant; membership review metadata обновляется в той же
authoritative boundary.

R7-FE добавляет публичную mock/frontend lifecycle boundary для нескольких
battles в одной session, active-participant guards, approve/return CAS,
terminal process cleanup и recovery/idempotency fixtures. Новый
authoritative path не выполняет повторный full-sheet commit при stop.
Оставшиеся legacy `GameCombatOverlay` mutators могут временно использовать
compatibility commit до R8; это не меняет канонический actualCharacter и не
является новым источником листа.

## Модерация

Персонаж требует модерации, если `approvedCharacterVersion == null` или `getCharacterDiff(approvedCharacterVersion, actualCharacter).hasChanges` равно `true`. `getCharacterDiff` — единый semantic comparator для moderation tab, `needsModeration`, `reviewState`, changed sections и допуска следующей сессии. `isCharacterChanged` допустима только как тонкий helper над его `hasChanges`; отдельного алгоритма сравнения нет. История версий и отдельный review snapshot не нужны.

Diff сравнивает все semantic поля листа, включая `states`, resources, inventory/equipment и persistent fields состояний. Operational battle markers, например compatibility-поле `wound.heldBy`, в semantic diff не входят.

- approve для submitted создаёт active membership и сохраняет approved snapshot;
- approve для active обновляет approved snapshot копией actual character;
- return for rework не удаляет membership, сохраняет причину и отправляет сообщение в обсуждение персонажа;
- reject удаляет только ещё не принятую submitted-заявку;
- active-персонажа нельзя reject;
- `canStartSession` проверяет membership, approved/actual, `getCharacterDiff`, совместимость revision, validation и отсутствие блокирующего repair state;
- `isActiveSessionParticipant` проверяет уже начатое участие и не требует `actualCharacter == approvedCharacterVersion`; membership в `returned` не принимает новые session/battle commands;
- `needsModeration` и `reviewState` не заменяют active-participant predicate;
- новая сессия блокируется, пока есть semantic diff, `changes_pending`, `returned`, несовместимая revision или repair state.

Approve разрешён во время active session. Backend атомарно проверяет ожидаемые `actual_version` и `membershipRevision`, повторно выполняет validation и сохраняет snapshot actual. Approve не изменяет actual и не прерывает сессию; если одна из версий устарела, операция получает conflict и не меняет ни одну сторону. Следующая actual mutation снова создаёт diff и `changes_pending`.

## Миграция

При смене ревизии игры все несовместимые персонажи должны пройти миграцию. Миграцию выполняет владелец персонажа; после неё персонаж снова проходит модерацию. До миграции и повторного approve следующая сессия этого персонажа заблокирована. Смена ревизии блокирует запуск следующей сессии только для несовместимых персонажей, а не игру целиком.

Миграция сохраняет actual character до успешного результата, переносит выбранные правила, способности, ресурсы, inventory и ссылки по `code`, валидирует результат и переводит персонажа в moderation flow. Владелец может переводить персонажа на разрешённую ревизию вне игры. Source и target revision при repair для `needs_fix` совпадают. Migration во время active session запрещена session guard-ом. `spaceCode` и `rulesRevision` игры нельзя менять при `status = playing`; изменение ревизии не является допустимым способом продолжить текущую сессию.

Физическая схема membership, backend storage, optimistic locking, удаление browser draft и полная модель NPC требуют отдельной реализации (`OPEN`); они не должны возвращать A/L/O/P или историю CharacterVersion. NPC не получает approved snapshot, draft или moderation baseline: его persisted sheet — `npc.version`, а `npc.actual_version` — только технический optimistic-lock counter.

## Подробный frontend-контракт

### Карточка персонажа

Маршрут `/characters/:id` содержит вкладки «Обзор», «Описание», «Способности», «Инвентарь» и «Обсуждение». Обзор показывает привязку к ревизии, характеристики с модификаторами, ресурсы current/max, деньги, состояния, защиту по слотам и атаки с оценкой формул.

Вкладка способностей использует поиск, фильтры «Все/Избранное/Навык/Черта/Заклинание» и раскрывающиеся строки. Для действия отображаются ОД, для заклинания — сотворение, сложность и длительность. Избранное хранится per-character в localStorage. Состояния показываются только в обзоре, а не в описании.

### Редактор

`/characters/new` выбирает пространство, ревизию и лимиты ОС/ОР/денег, затем открывает `/characters/new/editor`. `/characters/:id/edit` создаёт copy-on-write draft исходного персонажа.

Порядок этапов редактора:

```text
Раса → Характеристики → Основа → Личность → Развитие → Инвентарь → Описание
```

Шапка показывает бюджеты ОС/ОЛ/ОР/денег. Черновик сохраняется локально и может быть сохранён в любой промежуточной валидности. «Готово» запускает проверку имени, лимитов и требований способностей.

Реализованы в текущем frontend-контуре:

- выбор расы с видом/наследованием и предупреждением о сбросе несовместимых значений;
- покупка характеристик по лестницам и отображение live-значений;
- фильтры способностей по доступности, расе и общедоступности;
- возраст и личность, если в ревизии есть правило age;
- развитие, агрегаты и множественные навыки с domain/domainCode; изучение волшебства экземплярами пути (`magic_study` + `path_code`), попап путей, группа характеристик `magic`;
- описание;
- общий переиспользуемый `CharacterSheetEditor`;
- редактор inventory остаётся частично реализованным.

### Сохранение и редактирование

«Сохранить черновик» допускает перерасход лимитов и невыполненные требования. «Готово» требует имени, расы, лимитов и требований. При смене расы значения и способности, несовместимые с новой моделью, сбрасываются только после предупреждения и подтверждения.

Редактирование готового персонажа обновляет `actualCharacter` только после успешной проверки; локальный editor draft является только UI-состоянием. Вход из Game UI не создаёт отдельную Character storage path: обычный editor и in-game editor используют общий Character mutation service, typed patch с `expectedActualVersion` и whole-operation CAS без merge/rebase. Разрешённое редактирование во время active session публикует `CharacterChanged` после commit; Game сам проверяет active memberships, visibility и policy доставки. Ведущий с `game.edit_inventory` может редактировать инвентарь при соблюдении этих же mutation и authorization boundaries.

Frontend/mock R6-FE реализует этот boundary с `commandId`, повтором того же
patch после transport failure, сохранением dirty local draft при внешнем
изменении и явным reload/discard для stale conflict. `CharacterChanged` —
только post-commit integration fact: production persistence, outbox,
after-commit delivery и server-side authorization остаются backend
требованием. Character не импортирует Game.

### Сессионные детали

Draft сохраняется при каждом изменении и периодически, не теряется при закрытии браузера и использует namespace `character:${id}` или `npc:${id}`. Одна и та же логика редактора переиспользуется для NPC, но visibility и backend-хранение NPC требуют отдельного контракта.

Отрицательные ОЛ сгорают, если не были потрачены. При отсутствии лимитов ОС/ОР персонаж может быть готов при корректных расчётах и требованиях.

### Migration acceptance

Миграция на новую ревизию:

1. сохраняет actual character до успешного результата;
2. создаёт мигрированное состояние без истории CharacterVersion;
3. ремапит ссылки по глобальному `code`;
4. переносит inventory и деньги без повторной закупки;
5. превращает предмет с удалённым правилом в custom item;
6. валидирует по реальным лимитам персонажа и бонусам GM;
7. выдаёт `ok`, `resolved` или `conflicts`;
8. для conflicts открывает resumable editor на новой ревизии.

История версий Character и rollback не являются целевым контрактом. PHP `character.migrate` закрывает пункты 1–5 и 7 для лимитов строки: наличные без повторной закупки, предмет без живого правила — custom item, `conflicts` не пишет actual. Продолжаемый редактор — повтор того же действия с телом листа, не серверный черновик. Бонусы GM и потолки игры в это действие не входят: их считает Game, когда появится membership (C8). Смена мира этой операцией не делается.

## Custom rules и visibility

Custom rule персонажа хранится в `CharacterVersion.customRules`, не привязан к revision и переживает смену revision. Владелец может оформить его как Rule или заменить ссылкой на правило текущей ревизии; обе операции должны проходить через единый update router.

`SheetVisibility` задаёт audience (`all`, `gm` или список user ids) и sections. Владелец и super-admin видят всё, GM с `fullAccess` видит всё, остальные получают только разрешённые секции. Если лист вообще недоступен, detail/list должны вести себя как NotFound, а не раскрывать наличие объекта.

## Магия

Полная таксономия источников/веток и runtime сотворения — `DEFERRED` ([`spell-roadmap.md`](spell-roadmap.md)). В текущем frontend-редакторе: гранты `magic_path` / `magic_study` (`path_code`, слоты `max_instances`/`paid_cost`), покрытие `includes_path_codes`, требования `has_magic_path` / `magic_path_experience`. Game-каст и применение `spell_upgrade` на бросок ещё не входят. `contentStatus` и runtime-support независимы.
