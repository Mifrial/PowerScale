# Повторная проверка оставшихся findings — 2026-10-01

## Статус пачек — 2026-10-01

Пачки 1 и 2 реализованы. Registry по-прежнему не обновлялся.

- Пачка 1, `REV-FE-002`: launch dialogs больше не подменяют сбой загрузки пустым
  overlay. `AttackLaunchDialog`, `HitLaunchDialog`, `ActionLaunchDialog` и
  `CheckLaunchDialog` держат `loadError` и показывают его. Следующая пачка
  combat owner (`REV-FE-001` / `REV-FE-003`) разблокирована.
- Пачка 2, `REV-UI-002`: `CharacterSheetEditor` ждёт `onSaveChoices` или
  `onSave` и снимает `saving` в `finally` после settle.
- Пачка 2, `REV-UI-003`: `NpcEditPage` при ошибке загрузки показывает текст и
  «Повторить», `loading` сбрасывается.
- Пачка 2, `REV-CHAR-004`: `fetchCharacters` пишет список только если
  `listRequestSequence` ещё актуален. Detail guard был уже раньше.
- Пачка 2, `REV-UI-001`, персонаж в игре: «Редактировать в игре» идёт через
  `canOpenInGameEditor`, а не через любой active membership.

Остаток пачки 2: на `RuleDetailPage` кнопка «Редактировать» по-прежнему без
permission guard, route `RuleEdit` тоже не проверяет право. Это не блокирует
пачки 3 и 8. Переоткрывать `REV-UI-001` только для rule detail, когда
появится owner/membership contract редактирования правила.

Пачки 4 и 6 сделаны.

- Пачка 4, `REV-PERF-001`: `placementsForSections` читает карточки одним
  фильтром по списку `section_id`.
- Пачка 6, `REV-RULE-002`: `MechanicPayloadInspector` стоит на edit и detail.
  Backend-валидация payload по-прежнему отложена (`DEC-REVIEW-006`).

Пачка 3, `REV-CHAR-003`, остаётся подтверждённым дефектом repository, но не
ставится выше дефектов, которые уже видны пользователю. Брать её, когда
следующий шаг — реальная запись персонажа с двух запросов. До public
Character save она обязательна.

`REV-GAME-001` закрыт узко, без пачки 8. `GameMembershipEligibilityService`
задаёт условия старта сессии, речи в чате и участия в сессии.
`GameEditPage` и `GameChatTab` вызывают его. Вынос combat orchestration из
Vue (`REV-FE-001`, `REV-FE-003`) не делался: широкий промпт на всю пачку 8
был лишним. Следующий заход по dialogs только если появится конкретное
расхождение семантики, а не общий перенос.

Пачка 5, `REV-FE-005`, сделана. `null` source больше не склеивается:
`AggregateSourceDeltasService` даёт каждой записи без source свой ключ.

`REV-UI-004` сделан отдельной страницей `NpcDetail`. Игра её не грузит,
пока страницу не открывают. Слайдер не делался.

`REV-FE-006` и `REV-MAGIC-001` правятся отдельно. Пока идёт эта правка,
другие сессии в Game/spell/combat services не запускать.

Read-only проход по findings, которые не были закрыты или отклонены до этой
сессии. Production-код, тесты, schema, configuration, canonical docs и
`var/review/finding-registry.json` не менялись.

Закрытые ранее и здесь не переоткрывались: `REV-RULE-004`, `REV-PHP-001`,
`REV-CON-001`, `REV-CON-002`, `REV-CON-003`, `REV-DATA-001`, `REV-DATA-002`.

Отклонённые ранее и здесь не переоткрывались: `REV-RULE-003`, `REV-SEC-001`,
`REV-SEC-002`, `REV-PHP-002`, `REV-PHP-003`, `REV-PHP-004`, `REV-DATA-003`.

## A. Executive summary

| Итог | Количество |
| --- | --- |
| Подтверждено | 13 |
| Частично подтверждено | 2 |
| Закрыто текущим кодом | 3 (`REV-FE-004`, `REV-RULE-001`, `REV-PERF-002`) |
| Отклонено как недоказанный дефект | 1 (`REV-PERF-003`) |
| Отложено до backend Character/Game | 3 (`REV-BE-001`, `REV-CHAR-001`, `REV-CHAR-002`) |
| Нужно решение до кода | 4 (`REV-SEC-003`, `REV-UI-004`, `REV-DATA-004`, `REV-DATA-005`) |
| Пачек реализации | 8 |

Блокеров уровня «сейчас ломает сохранённые данные или обходит auth» нет.
Ближайший пользовательский дефект — тихий пустой fallback в launch dialogs
(`REV-FE-002`). Перед публичным Character save обязателен условный write
(`REV-CHAR-003`). Backend Game остаётся requirement, а не баг текущего PHP.

Registry после этого документа не обновлён. Закрытие `REV-FE-004`,
`REV-RULE-001` и `REV-PERF-002` вносить только отдельным подтверждённым
шагом.

## B. Verdict

| ID | Verdict | Severity | Confidence | Причина | Owner | Action |
| --- | --- | --- | --- | --- | --- | --- |
| REV-FE-001 | CONFIRMED | P2 | HIGH | Eligibility, cost и process по-прежнему считаются в Vue dialogs. Неверного исхода одним сценарием не доказано, поэтому это не P1. | frontend/game | decision, затем fix |
| REV-FE-002 | RESOLVED | P1 | HIGH | Launch dialogs показывают `loadError` вместо пустого overlay. | frontend/game | reject |
| REV-FE-003 | CONFIRMED | P2 | HIGH | Hydration и speaker/process повторяются в нескольких dialogs. | frontend/game | fix после контракта FE-001 |
| REV-FE-004 | RESOLVED | — | HIGH | `AbilityCheckAdvantagesService` и `EditorCheckBonusesService` вызывают `FormulaEvaluationService`. Ноль для отсутствующей характеристики — явный контракт с тестом. | frontend/character | reject |
| REV-FE-005 | RESOLVED | P2 | HIGH | `null` source не склеивается: у каждой записи без source свой ключ. Указанный source по-прежнему схлопывается. | frontend/character | reject |
| REV-FE-006 | CONFIRMED | P2 | HIGH | `endurance` зашит в `AttackDamageService`. Это production coupling, не fixture. | frontend/game | decision |
| REV-MAGIC-001 | CONFIRMED | P2 | HIGH | `SpellCastExecutionService` и `SpellCastEfficiencyService` ищут конкретные ability/resource codes. | frontend/game/magic | decision |
| REV-RULE-001 | RESOLVED | — | HIGH | `FormulaInput` создаёт все варианты `ScalarFormula`, включая `parameter_floor_div` и size/gap. | frontend/rule | reject |
| REV-RULE-002 | RESOLVED | P2 | HIGH | Edit и detail показывают `MechanicPayloadInspector`. Backend-валидация payload остаётся отдельной задачей. | frontend/rule | reject |
| REV-SEC-003 | RESOLVED | — | HIGH | `csrf: false` на неаутентифицированных auth-маршрутах оставлен: session cookie `HttpOnly` + `SameSite=Lax`. | backend/auth | reject |
| REV-GAME-001 | RESOLVED | P2 | HIGH | Старт сессии, чат и участие идут через `GameMembershipEligibilityService`. | frontend/game | reject |
| REV-GAME-002 | PARTIALLY_CONFIRMED | P2 | HIGH | Stop и update идут двумя вызовами; клиент откатывает status. Общей операции нет. | frontend/game + backend/game | defer атомарность в Game |
| REV-BE-001 | REQUIREMENT | — | HIGH | PHP Game module нет. | backend/game | defer |
| REV-UI-001 | PARTIALLY_CONFIRMED | P2 | HIGH | In-game edit больше не открыт любому active membership. Rule detail edit по-прежнему без permission guard. | frontend | defer rule detail |
| REV-UI-002 | RESOLVED | P2 | HIGH | `finish` ждёт `onSaveChoices` или `onSave` и снимает `saving` после settle. | frontend/character | reject |
| REV-UI-003 | RESOLVED | P2 | HIGH | Ошибка загрузки NPC показывает текст и retry, `loading` сбрасывается. | frontend/game/npc | reject |
| REV-UI-004 | RESOLVED | P2 | HIGH | Полная деталка NPC — отдельная страница `NpcDetail`, игра её не грузит заранее. | frontend/game/npc | reject |
| REV-DATA-004 | OPEN_DECISION | P2 | MEDIUM | Уникальные ключи не покрывают все composition invariants. Само по себе это не дефект: `REV-DATA-003` уже отдан application writer. | backend/rule-space | decision |
| REV-DATA-005 | OPEN_DECISION | P2 | HIGH | Schema по-прежнему `createTable`, не ledger. Это политика (`DEC-REVIEW-009`). | backend/schema | decision |
| REV-PERF-001 | RESOLVED | P2 | HIGH | Placements секций читаются одним фильтром по списку `section_id`. | backend/rule-space | reject |
| REV-PERF-002 | RESOLVED | — | HIGH | `GameChatTab.load` один раз вызывает `getRuntimeEntities` со всеми character keys. | frontend/game | reject |
| REV-PERF-003 | FALSE_POSITIVE | — | MEDIUM | Линейный `rules.find` в hit roll не сопровождается budget и worst-case размером. | frontend/game | defer |
| REV-CHAR-001 | DEFERRED_NOT_IMPLEMENTED | P1 | HIGH | Public Character action нет, привязать owner к actor негде. | backend/character | defer |
| REV-CHAR-002 | DEFERRED_NOT_IMPLEMENTED | P1 | HIGH | `rulesRevision` проверяется как positive int. Дыра проявится на public boundary. | backend/character | defer |
| REV-CHAR-003 | CONFIRMED | P2 | HIGH | `writeGuarded` читает version и пишет в transaction без `SELECT FOR UPDATE` и без условного `UPDATE`. Два соединения могут оба пройти сравнение. | backend/character | defer до записи с двух запросов |
| REV-CHAR-004 | RESOLVED | P2 | HIGH | И detail, и `fetchCharacters` отбрасывают устаревший ответ по sequence. | frontend/character | reject |

## C. Спорные findings

**REV-FE-004 и REV-RULE-001.** Локальные evaluators больше не возвращают ноль
вместо поддерживаемого варианта: они вызывают `FormulaEvaluationService`.
`FormulaInput` создаёт все варианты `ScalarFormula`. Ноль для отсутствующей
характеристики зафиксирован тестом. Переоткрывать только если появится новый
член union без case в evaluator и без mode в editor.

**REV-FE-001 / REV-FE-003.** Логика в dialogs — факт. Это не доказанный
неверный бросок. Крупный перенос в services нужен как один owner перед
backend Game. `REV-FE-002` из этой пачки вынут: пустой `.catch(() => [])` —
отдельный дефект.

**REV-FE-005.** Расхождение агрегаторов подтверждено. Смысл `null` source
каноном не закрыт (`DEC-REVIEW-002`). Код до этой семантики не менять.

**REV-FE-006 / REV-MAGIC-001.** Дефект не в наличии строковых кодов, а в том,
что generic combat/spell services зависят от экземпляров правил. Допустимый
исход — versioned capability adapter (`DEC-REVIEW-003`).

**REV-RULE-002.** Lossless round-trip уже есть. Не хватает generic inspector
или явного unsupported status. Typed editors на каждый payload — отдельное
расширение.

**REV-SEC-003.** Opt-out на login, register, guest, password policy и обоих
шагах reset сохранён. Logout, user create и set password CSRF включают.
Вердикт остаётся решением.

**REV-GAME-001 / REV-GAME-002 / REV-BE-001.** Несогласованный фильтр active
membership — дефект текущего frontend. Отсутствие PHP Game и единой
DB-транзакции stop+settings — requirement. Клиентский rollback в
`GameEditPage` уже есть.

**REV-UI-004.** Просмотр NPC в игре уже есть. Форма permission-aware preview
не выбрана.

**REV-DATA-004.** Отсутствие DB unique не равно ошибке. Переоткрывать, если
обход `RuleSpaceCatalogGuard` создаёт несобираемый снимок, который
application writer обязан был отвергнуть, либо если invariant решено
перенести в storage.

**REV-DATA-005.** Versioned ledger нужен как решение до схемы Game, не как
рефакторинг уже существующих schema в той же пачке.

**REV-PERF-002.** N+1 Character request снят batch projection.

**REV-PERF-003.** Повторный lookup есть. Подтверждённого превышения budget нет.

**REV-CHAR-001 / 002.** Публичной границы нет. **REV-CHAR-003** уже в
существующем repository. **REV-CHAR-004:** detail закрыт generation guard;
открыт только список.

## D. Implementation batches

### 1. Launch dialog error state

Findings: `REV-FE-002`.

Проблема: сбой загрузки overlay выглядит как пустой бой.
Вместе безопасно: один lifecycle loading/error/retry в launch dialogs.
Не смешивать с выносом domain logic.
Зависимости нет. Модуль: Game dialogs. Размер: маленький.
Риск: диалоги начнут блокироваться на необязательных overlay.
Тесты: reject `getCombatOverlays` / effects / sessions показывает error и
retry, не пустой список.
Готово, когда ни один load path не делает `.catch(() => [])` для данных,
без которых решение игрока неверно. Один коммит.

### 2. Edit affordances и save/load lifecycle

Findings: `REV-UI-001`, `REV-UI-002`, `REV-UI-003`, остаток `REV-CHAR-004`.

Проблема: UI обещает действие или «сохранено/загружено» раньше, чем
закончился контракт.
Вместе: client lifecycle/affordance, без смены домена.
Не смешивать с NPC preview (`REV-UI-004`) и с CAS персонажа.
Модули: Rule detail, Characters tab, Character sheet editor, NPC edit,
character store. Размер: маленький.
Риск: скрыть кнопку тому, кому route всё ещё разрешает edit.
Тесты: чужой active membership без edit link; `saving` до resolve parent
save; NPC load error + retry; поздний `fetchCharacters` не затирает новый
список.
Лучше два коммита: affordances и async lifecycle.

### 3. Character lost-update

Findings: `REV-CHAR-003`.

Проблема: optimistic guard не атомарен между соединениями.
Отдельно от frontend generation и от public API.
Модуль: `CharacterRepository`. Размер: маленький.
Тест: два соединения, один expected version, ровно один успех.
Один коммит. Делать до public Character save.

### 4. Catalog placement query

Findings: `REV-PERF-001`.

Проблема: один SQL на секцию.
Сначала замер числа секций на реальном каталоге.
Модуль: `RuleSpaceCatalogRepository`.
Тест: N секций → один запрос, sort сохранён.

### 5. Modifier aggregation owner

Findings: `REV-FE-005`. `DEC-REVIEW-002` решён 2026-10-01: `null` source
не схлопывается, каждый такой modifier уникален. Можно предлагать план.
Owner: `AggregateSourceDeltasService`. Consumer: overview.
Тесты: null source, нулевой delta, плюс и минус одного source, parity
overview/runtime.

### 6. Rule mechanic inspector

Findings: `REV-RULE-002`.
Не смешивать с backend validation payload.
Тесты: round-trip через inspector, unknown payload не теряется, detail
показывает unsupported/generic view.

### 7. Combat vocabulary decision package

Findings: `REV-FE-006`, `REV-MAGIC-001`. Код только после `DEC-REVIEW-003`.
Каждый concrete code классифицировать: vocabulary contract, spec adapter
или удаление из generic service. Тест чужой revision.

### 8. Combat headless owner — не брать целиком

Findings: `REV-FE-001`, `REV-FE-003`. `REV-GAME-001` закрыт отдельно.
После пачки 1 и после короткого headless-контракта.
Не вместе с vocabulary и не вместе с PHP Game.
Размер: большой, несколько коммитов.
`REV-GAME-001` закрыт через `GameMembershipEligibilityService`.
Атомарность `REV-GAME-002` сюда не входит. Общий перенос dialogs не
делался.

Без кода в этой очереди:

- Security decision: `REV-SEC-003` / `DEC-REVIEW-008`.
- Backend readiness: `REV-BE-001`, `REV-GAME-002`, `REV-CHAR-001`,
  `REV-CHAR-002`, `DEC-REVIEW-004`, `DEC-REVIEW-005`, `DEC-REVIEW-010`.
- NPC surface: `REV-UI-004` после `DEC-REVIEW-007`.
- Schema policy: `REV-DATA-004`, `REV-DATA-005`.

## E. Порядок

1. Пачки 1, 2, 4 и 6 сделаны. Остаток пачки 2: rule detail edit без permission.
2. Пачка 3 подтверждена, но берётся непосредственно перед реальной записью
   персонажа с двух запросов. Она блокирует public Character save, не
   текущие пользовательские дефекты.
3. Решения: null-source, combat vocabulary, NPC surface, CSRF, catalog
   invariant owner, migration ledger.
4. Пачки 5 и 7 после соответствующих решений.
5. Пачка 8 целиком не делается. `REV-GAME-001` уже закрыт. `REV-FE-001` и
   `REV-FE-003` остаются до конкретного расхождения dialogs, не до общего
   переноса.
6. Character public actions только после пачки 3 и `DEC-REVIEW-004`.
7. PHP Game после `DEC-REVIEW-005/010`. К этому моменту eligibility helper
   уже есть; копировать Vue dialogs в backend не нужно, пока owner не
   выделен по доказанному расхождению.

## F. Open decisions

Уже записаны и кодом не закрываются: `DEC-REVIEW-002`, `003`, `004`, `005`,
`007`, `008`, `009`, `010`. Плюс развилка `REV-DATA-004`: invariant остаётся
у application writer или переезжает в unique keys. `DEC-REVIEW-006` уже
решён в части lossless payload; backend validation по-прежнему позже.

## G. Residual risks

После этих пачек останутся: нет PHP Game и нет public Character API; два
соединения для RuleSpace CAS и catalog binding по-прежнему hardening;
CSRF и schema ledger без решения; NPC moderation preview без выбранного
surface; линейный rule lookup без профиля; client rollback настроек игры
не равен серверной транзакции.
