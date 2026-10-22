# План Game 36 — владелец персонажа в ударе 1 → 1

**Статус:** ~СДЕЛАНО~

**Актуально на момент плана 36.** Широкий удар тогда оставался writer-only. Это ограничение заменено планом 37 — [`game-plan-37-wide-strike-owner-actions.md`](game-plan-37-wide-strike-owner-actions.md): владельцы целей отвечают независимо.
**Реализация:** ~PHP~, ~VUE~
**Опираться на:** ~PHP~

## Состояние

Сделано в коде:

- бэкенд `GameStrikes` / `GameStrikeRepository` / `IGameStrikes`: права по действующему персонажу для `declareStrike` и `resolveStrike`, `findLatest()` для отличия закрытого удара от чужого;
- фронтенд: `GameAccessService.canWriteStrike`, `GameStrikeDefenseService` (+ instance/export), ожидание и «Взять на себя» в `HitLaunchDialog`, авто-открытие/закрытие чужого окна и прогрев имён в `GameChatTab`, `opened/refused/remaining` в `GamePhpTurnResult` и `continueOrAbort()`.

Проверено: `npx vitest run src/modules/Roleplay/Game` — 742 теста, 109 файлов, зелёные; `vue-tsc` и ESLint по затронутым файлам без новых ошибок; из `www/mifrial` `composer maps-check` и `composer docs-check` зелёные.

MySQL-тесты починены: `GameStrikeMysqlTest` — 20 тестов, 1205 утверждений, зелёные. В фикстуре `worldWithWeapon` убран невалидный `junk`, добавлены `dodge`, состояния `disabled`/`unconscious`, `accuracy` профилей, автоматические `strength`/`stamina`, лимит `focus`, базовая `agility`. Добавлен конкурентный тест «писатель + владелец защитника»: `runConcurrentStrikeRequests()` принимает актора, права и payload по воркерам; ровно один запрос успешен, второй получает `GAME_CONFLICT`/`GAME_INVALID`.

Попутно исправлена гидрация ревизии из кэша в `RuleVersionRecord`: вложенные `JsonObject` в `spec` и `mechanics` разворачиваются в PHP-массивы, иначе сборка NPC и удара падала на `Mechanic id is invalid`; закреплено `RuleVersionRecordTest::testNestedJsonObjectsAreUnwrapped()`. Отказ публикации битого item-spec закреплён в `RuleRevisionSpecGuardTest::testBrokenItemArmorSpecRejects()`.

Живой стенд проверен: владелец персонажа объявляет и закрывает удар за своего персонажа, GM видит ожидание реакции и кнопку «Взять на себя», чужой игрок окно удара не получает. Поток `1 → 1` закрыт. Широкий удар на момент этого шага оставался writer-only; его права и частичные ответы заменены планом 37.

## Задача

Сейчас `game.declareStrike` и `game.resolveStrike` пускают только писателя игры: владельца карточки, `gm` или `game.edit_all`. Владелец персонажа получает `AUTH_DENIED`, хотя интерфейс боевой карточки уже разрешает ему атаковать своим персонажем (`CombatCardPanel` → `launchHit` при `model.canEdit`). Это ошибка: владелец персонажа должен действовать за него и в атаке, и в защите.

Дополнительно GM по умолчанию видит форму защиты за чужого персонажа. Нужно показывать ожидание реакции и отдельную кнопку перехвата; владелец защитника должен получать форму сам.

Затрагивается только поток `1 → 1`. Широкий удар (`GameWideStrikes`) на момент плана 36 оставался writer-only; это ограничение заменено планом 37.

## Контракт прав

| Действие | Актор | Разрешено |
|---|---|---|
| `declareStrike` | атакующий | писатель игры или владелец активного персонажа-атакующего |
| `resolveStrike` | защитник | писатель игры или владелец активного персонажа-защитника |
| любой шаг | NPC | только писатель |

«Писатель» — ровно `game.ownerId`, роль участника `gm` или глобальный `game.edit_all`. Per-game `game.edit` прав на удар не даёт.

Это выравнивание с уже существующими сценариями: `GameWoundActions::assertWriter` и `GameTurns::assertActor` разрешают владельцу персонажа-актора. `GameStrikes` было исключением.

Прежняя строка плана 13 «`player` удар не объявляет» отменяется: она фиксировала ошибку.

## Бэкенд

### `GameStrikeRepository`

- `findLatest(int $battleId): ?array` — последняя строка боя по `id`. Нужна, когда открытого удара уже нет: отличить «ваш удар только что закрыли» (`GAME_INVALID`) от «это не ваш удар» (`AUTH_DENIED`).

### `GameStrikes`

- `ready()` больше не вызывает `assertWriter`. Добавить `?array $actingEntity = null`; после видимости, реплея и проверки статуса, если entity передан, вызвать `assertStrikeActor()`.
- `declareStrike()` передаёт `$choice['attacker']`.
- `resolveStrike()` передаёт `null`: защитник берётся из строки удара, а не из тела.
- `assertStrikeActor(GameRecord $game, int $actorUserId, bool $editAll, array $entity)`:
  - писатель → разрешить;
  - `type !== 'character'` → `AUTH_DENIED`;
  - `characters->getByPair($game->getId(), $id)`;
  - владелец не совпал или `status !== 'active'` → `AUTH_DENIED`;
  - `GameNotFoundException` → `AUTH_DENIED`.
- `close()` получает `$editAll`:
  - `$open = findOpen($battleId)`;
  - если `$open === null`:
    - писатель → `GAME_INVALID`;
    - иначе `$latest = findLatest($battleId)`;
    - нет строки → `AUTH_DENIED`;
    - `assertStrikeActor()` по защитнику последней строки;
    - после успешной проверки → `GAME_INVALID`;
  - если `$open !== null`: `assertStrikeActor()` по `defender_kind/defender_id` до `battleOf()`;
  - далее без изменений.
- `assertWriter()` заменить на `isWriter()`; мёртвый метод не оставлять.
- Конструктор не меняется: `$this->characters` уже есть.
- Новых блокировок нет. «Первый принятый выигрывает» остаётся на CAS версии боя.

### Документация PHP

- `IGameStrikes` и `GameStrikes`: в PHPDoc `declareStrike`/`resolveStrike` указать «писатель или владелец соответствующего персонажа; NPC — только писатель».

## Бэкенд-тесты

`GameStrikeMysqlTest`:

- владелец персонажа-атакующего успешно объявляет удар;
- чужой игрок за этого персонажа → `AUTH_DENIED`;
- игрок за NPC-атакующего → `AUTH_DENIED`;
- GM/владелец игры объявляет за персонажа игрока → успех;
- владелец персонажа-защитника успешно закрывает удар;
- чужой игрок за защитника → `AUTH_DENIED`;
- игрок за NPC-защитника → `AUTH_DENIED`;
- повтор после закрытия от владельца защитника → `GAME_INVALID`, не `AUTH_DENIED`;
- конкурентные GM + владелец защитника с разными ключами → ровно один успех, второй `GAME_CONFLICT`/`GAME_INVALID`.

Fixture:

- `admitOwnedBy(int $ownerUserId, ...)` или параметр владельца в `admit()`;
- второй пользователь через `gameUserAccounts()->addFromInput`;
- `runConcurrentStrikeRequests` принимает акторов по worker, а не всегда `ownerUserId`.

## Фронтенд

### `GameAccessService`

- `canWriteStrike(user, detail)`: `game.edit_all`, `detail.game.ownerId`, роль `gm`. Per-game `game.edit` не учитывать.
- Тесты в `gameAccessService.test.ts`.

### `GameStrikeDefenseService`

Новый stateless-сервис в `Service/` + `Service/Instance/` + экспорт из `init.ts`:

- `defenderKey(strike)`;
- `isDefenderOwner(strike, userId, memberships)`;
- `shouldAutoOpen(...)` — только писатель или владелец защитника;
- `canResolve(...)`:
  - владелец защитника → true;
  - NPC + писатель → true;
  - писатель + персонаж + явный override → true;
  - иначе false;
- `waitingText(...)` — «Ожидаем реакцию {Игрок} за {Персонаж}», для NPC — «Ожидаем реакцию ведущего за {НПС}», fallback `#id`.
- Unit-тесты всех веток.

### `HitLaunchDialog`

- Новые props: `canWriteStrike`, `userNames`.
- `phpStrikeOpen` = `phpDeclared`.
- `resolvedDefenderKey` = `phpDefenderKey ?? opponentKey`.
- `phpCanResolve` — через `GameStrikeDefenseService`.
- `isPhpDefenseStep = phpStrikeOpen && !phpDebt && phpCanResolve`.
- `isPhpAwaiting = phpStrikeOpen && !phpDebt && !phpCanResolve`.
- `isCompose = offer === null && !phpStrikeOpen` (ожидание больше не показывает compose).
- После `declareStrike`:
  - владелец защитника / NPC+писатель / уже нажатый override → форма защиты;
  - иначе — ожидание, форма защиты и кнопка «Принять и бросить» скрыты.
- GM по умолчанию видит ожидание для персонажа; кнопка «Взять на себя» ставит `phpOverride=true`.
- Для NPC форма сразу.
- `phpOverride` сбрасывается при открытии/закрытии.
- `isDefenderStep` и `canSubmit` не считают ожидание шагом защиты.
- Долг: `continuePhpDebt()` не должен безусловно ставить `phpDeclared=false`. `GamePhpTurnResult` дополнить `opened/refused/remaining`; `continueOrAbort()` возвращает результат; если `opened`, перейти в защиту/ожидание.
- Emit `strike-refresh` при ошибках PHP-удара.

### `GameChatTab`

- `canWriteStrike` через новый метод; передать в диалог `can-write-strike` и `user-names`.
- `tryOpenPhpStrike()`:
  - проверку закрытия выполнять до раннего `if (hitOpen) return`;
  - `openStrike === null` и открыт привязанный PHP-диалог (`hitPhpBound`) → закрыть/сбросить;
  - удар открыт, но пользователь не писатель и не владелец защитника → не открывать, привязанный чужой PHP-диалог закрыть; окно набора нового удара не трогать;
  - привязка `hitPhpBound` ставится при авто-открытии, при совпадении защитника в `tryOpenPhpStrike` и по `settled`/`strike-refresh`, сбрасывается в `onHitClosed`;
  - авто-открытие только для писателя и владельца защитника;
  - для писателя по умолчанию `phpOverride=false`.
- `strike-refresh`: перечитать `getGame` + `getBattleSheets`, обновить контекст, вызвать `tryOpenPhpStrike`; закрывать только если удара нет или пользователь больше не может действовать.
- SSE `strike/turn/battle/wound` уже вызывает `tryOpenPhpStrike`; после закрытия удара PHP-диалоги закрываются.
- Прогрев имён: один раз `ensureUsers(memberships.map(m => m.characterOwnerId))` через `useUserCatalog`; `#id` — fallback.
- Баннер «Есть открытый удар — ответить» для писателя/владельца защитника, если открыт другой диалог: окно набора нового удара не трогаем, но пользователь должен видеть открытый удар и переключиться на защиту сам.
- Данные открытого удара диалог берёт из пропа `phpOpenStrike`, а не из глобального контекста боя: контекст обновляется и сбрасывается между событиями, и шаг защиты без данных удара превращался в «ожидание реакции защитника» у самого защитника.

### Атака игрока

Фронтенд уже открывает атаку владельцу: `CombatCardPanel.launchHit` при `model.canEdit`, `HitLaunchDialog` compose не гейтится `canEdit`. `canWriteStrike` не используется для запрета атаки — только для перехвата и ожидания. После снятия writer-only бэкенд перестаёт отвечать `AUTH_DENIED`. Добавить регрессионную проверку: игрок объявляет удар своим персонажем.

## Документы

- Этот файл — источник контракта прав для удара `1 → 1`.
- `docs/tr/game-plan-13.md`: поправить раздел «Права» и строку таблицы ошибок про `game.edit`; сослаться на план 36.
- `docs/tr/game-plan-35.md` и широкий удар не меняются.
- Из `www/mifrial`: `composer maps`, `composer maps-check`, `composer docs-check`.

## Приёмка

- Владелец персонажа объявляет удар своим персонажем.
- Чужой игрок не может объявить или закрыть удар за этого персонажа.
- Владелец защитника видит форму и закрывает удар.
- GM видит «Ожидаем реакцию …» и может явно взять защиту на себя.
- NPC-атаку и NPC-защиту выполняет только писатель.
- Игрок, не владеющий защитником, не видит окно удара.
- После закрытия удара открытые PHP-диалоги закрываются/обновляются через SSE.
- Второй конкурентный запрос получает конфликт и обновляет окно.
- Широкий удар на момент плана 36 оставался writer-only; заменено планом 37.

## Вне скоупа

- Широкий удар и частичные реакции — вынесены в план 37.
- Новые таблицы, блокировки, права `game.edit` на удар.
- Изменение модели членства и `ownerName` в бэкенде.
- Автор сообщения чата при защите игроком: схема «автор — разрешивший, спикер — атакующий» сохраняется; при необходимости выносится отдельно.
