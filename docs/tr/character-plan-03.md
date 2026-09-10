# План Character 3 — контекст ревизии (C2)

**Статус:** PHP сделан, 2026-09-10. `BACKEND_OPEN`. Нарезка — [`character-roadmap.md`](character-roadmap.md). Контракт save — [`character-plan-01.md`](character-plan-01.md). Каркас persist — [`character-plan-02.md`](character-plan-02.md) (**`ICharacters` не менялся**). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). Границы — [`architecture.md`](architecture.md).

Цель: один загрузчик **`(spaceId, revision)`** для C4/C5. Character ходит в **`IRuleSpaces`**, не в `IRules` и не в Versioning. Снимок правил индексируется по `code`; признаки — **коды**, не PHP-ids. Tombstone не живое правило. Спеки не парсить и не замораживать.

После зелёного suite: по-прежнему **`BACKEND_OPEN`**. Save не закрывать.

## Зафиксировано

- Селектор тот же, что в C1: `spaceId` = id часов `rule_space` (= `IRuleSpaces.get`), `revision` = номер, не `rule_revision.id`.
- Не `IRules::findLatestRevision`, не `getRevisionList` как выбор «текущей» ревизии, не `getCatalog`. Один вызов `get` мира + один `getRevision`. Не `findInRevision` в цикле по способностям.
- **Не** вшивать `IRuleSpaces.getRevision` в `ICharacters::add`. C1 mysql ставит только часы без sidecar; ревизия `1` в строке персонажа там не обязана существовать. Проверка среза — на C5 (и на validate C4), не на сыром persist.
- Мир без sidecar (`IRuleSpaces.get` → not found): `CHARACTER_NOT_FOUND`. Часы без sidecar у C1-строки для C2 — дыра persist, не дыра среза.
- Выключенный мир (`RuleSpaceRecord` `active=false`): `CHARACTER_INVALID`, срез не отдавать. Это политика Character на **build / validate / save** (C0: нельзя create/update/validate). RuleSpace при выключенном мире `getRevision` всё ещё отдаёт состав — не опираться на это. Owner GET уже записанного `sheet` (C6) **не** обязан звать этот loader. Историческую загрузку выключенного мира в C2 не добавлять.
- `revision < 1` или битый номер: map `RuleSpaceInvalid` → `CHARACTER_INVALID`. Нет ревизии / нет мира: `RuleSpaceNotFound` → `CHARACTER_NOT_FOUND`.
- Пункт среза `active=false`: **tombstone**, не live. C4 ищет live по code; tombstone — отдельный индекс («было, снято», не «никогда не было»).
- `contentStatus` на load **не** влияет (C0). Не фильтровать live по статусу контента.
- Keyword: Character → Keyword по architecture разрешён. Снимок `RuleVersionRecord::getKeywordIds()` — ids; C2 резолвит в **codes** через `IKeywords`. Хардкод кодов признаков как механика персонажа — по-прежнему запрещён.
- Mechanic: в C2 остаётся **id** (`getMechanicId`). Резолв handler — C3.
- Spec: `array` как есть. Не hydrator, не канон полей, не `IMPLEMENTED` формы.
- Наружу не коды `RULESPACE_*`, `KEYWORD_*`, ST.

## Что даёт этот заход

Порт **`ICharacterRuleSlices`**: `get(int $spaceId, int $revision): CharacterRuleSlice`.

Сосед: `$locator->get(ICharacterContainer::class)->get(ICharacterRuleSlices::class)`.

`ICharacters` остаётся 4 метода. Factory persist не трогать (только User + ST). Новый порт: `IRuleSpaces` + `IKeywords`.

## Что не закрыто

- HTTP, Vue, Game, Chat.
- C3 Engine / Binding.
- C4 build, C5 save, сверка `spaceCode` на HTTP (поле `getSpaceCode()` на срезе — для C5, не роут). C6 read по `sheet` без loader.
- Проверка, что у персонажа `rules_revision` совпадает со срезом — на оркестре C5, не внутри loader как side-effect persist.
- `getByIds` в Keyword не добавлять этим планом.

## DAG прод-PHP (добавка к C1)

Character сервисы среза → `IRuleSpaces` + `IKeywords`. По-прежнему ↛ `IRules`, ↛ Versioning, ↛ Mechanic, ↛ Game.

Тесты mysql C2 **не** как C1 (только `RuleSpaceTable` clock). Нужен мир через `IRuleSpaces` (sidecar + commit состава), плюс Keyword (и карты Rule/Mechanic, как в `RuleSpaceMysqlTest`). Не drop `rule_space`, пока висят FK Rule.

## DTO

**`CharacterRuleSlice`**

- `getSpaceId()`, `getRevision()`, `getSpaceCode()`.
- `findLive(string $code): ?CharacterResolvedRule` — только `active=true`.
- `hasTombstone(string $code): bool`.
- `getLiveRules(): list<CharacterResolvedRule>` — **порядок состава** `RuleRevisionSlice::getItems()`, tombstone пропускать (относительный порядок live как в срезе). Не сортировать по `code`.

Дубль **live** `code` в одном срезе → `CHARACTER_INVALID` (защита; валидный commit Rule так не отдаёт). Один и тот же `code` и в live, и в tombstone → тоже `CHARACTER_INVALID`.

**`CharacterResolvedRule`**

Обёртка над пунктом среза для C4: `getCode`, `getType`, `getName`, `getDescription`, `getSpec`, `getKeywordCodes(): list<string>`, `getMechanicId(): ?int`, `getMechanicPayload()`, `getContentStatus()`.

**Не** отдавать `getKeywordIds()`. C4 не ходит в `RuleVersionRecord` за признаками.

Порядок `getKeywordCodes`: как ids на снимке, каждый id → code; дубль id схлопнуть, сохранив первое вхождение.

Id признака нет в Keyword → `CHARACTER_INVALID` (битый снимок). Выключенный признак в справочнике **не** ошибка: code всё равно резолвится (исторический снимок).

Резолв: уникальные ids со среза, затем `IKeywords::get` по каждому (каталог маленький). Не SQL Keyword. Не тащить весь `getList`, если хватает get по id. Нет `getByIds` — не расширять Keyword этим планом.

## Алгоритм `get`

1. `IRuleSpaces::get($spaceId)` → нет sidecar → `CHARACTER_NOT_FOUND`.
2. Мир `active=false` → `CHARACTER_INVALID` (срез не грузить).
3. `IRuleSpaces::getRevision($spaceId, $revision)` → map ошибок.
4. Пройти `RuleRevisionSlice::getItems()` **в этом порядке**: inactive → tombstone-index; active → live (map + список в порядке обхода) + собрать keyword ids.
5. Резолв ids → codes; собрать `CharacterResolvedRule`.
6. Вернуть slice. Не кэшировать между запросами (детерминизм C5; кэш — отдельное решение).

## Ошибки

| Источник | Лист |
|---|---|
| `RuleSpaceNotFoundException` | `CHARACTER_NOT_FOUND` |
| `RuleSpaceInvalidException` | `CHARACTER_INVALID` |
| `KeywordNotFoundException` | `CHARACTER_INVALID` |
| `KeywordInvalidException` | `CHARACTER_INVALID` |
| Дубль live code / противоречивый состав | `CHARACTER_INVALID` |

Те же листья C1. Новых кодов Action не плодить.

## Модуль

Путь тот же `Roleplay/Character`. В `module.config.php` порт `ICharacterRuleSlices`. Сервис в `Service/`, интерфейс в `Interface/Service/`, DTO в `Dto/`. Фабрика **рядом с C1**, не `Service/Factory/`: `CharacterRuleSlicePortFactory` в `Service/` — чтобы `CharacterPortFactory` по-прежнему не импортировал RuleSpace/Keyword.

PHPDoc на публичном API: `@param` / `@return` / `@throws` на русском, как C1.

## Тесты

Suite `character`. Не ломать `CharacterMysqlTest` C1 (часы без sidecar, add без среза).

**Юнит** (моки `IRuleSpaces` / `IKeywords`, живые DTO Rule при необходимости):

- live по code; keyword ids → codes; `getLiveRules()` в порядке состава, не по alphabet;
- tombstone не в `findLive`, `hasTombstone` true;
- inactive world → INVALID, `getRevision` не звать;
- not found мира/ревизии → NOT_FOUND;
- отсутствующий keyword id → INVALID;
- не звать `IRules`.

**Mysql** (один-два сценария, фикстура как RuleSpace, не как C1):

- `IRuleSpaces.add` + commit правила с keyword → `ICharacterRuleSlices.get` отдаёт code правила и code признака;
- deactivate мира → INVALID;
- несуществующая ревизия → NOT_FOUND.

HTTP `character.*` по-прежнему нет. Boot: резолв `ICharacters` и `ICharacterRuleSlices`; маршрутов create/update нет.

## Критерий закрытия C2

- Порт в контейнере; Character прод-PHP ↛ `IRules` / Versioning.
- Live vs tombstone; keyword **codes**.
- `ICharacters` без изменений контракта.
- Suite зелёный. Статус линии: **`BACKEND_OPEN`**.

## Вне скоупа

C3–C9. Парсинг spec. Latest revision. Активация мира. Проверка published revision на `add`. Vue. Исторический срез выключенного мира.
