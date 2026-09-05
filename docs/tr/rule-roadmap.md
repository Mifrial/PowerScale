# Нарезка Roleplay/Rule

**Статус:** план, 2026-09-05. Канон — [`rule-system.md`](rule-system.md). Часы — [`versioning-roadmap.md`](versioning-roadmap.md), [`versioning-plan-01.md`](versioning-plan-01.md). Стандарты — [`php-coding-standards.md`](php-coding-standards.md). `DEC-080`, `DEC-060`, `DEC-062`.

Цель линии: отдельные PHP-модули **`Roleplay/Keyword`**, **`Roleplay/Mechanic`**, **`Roleplay/Rule`**, затем оператор **`Roleplay/RuleSpace`**. Колонка и `Reference` появляются вместе с целевым модулем. В `OPEN` — только то, без чего persist уже живёт (валидация spec по типам, хендлеры Engine).

Порядок сверху вниз. Vue `Rule ↛ Versioning` не отменяется. **PHP Rule → Versioning + Keyword + Mechanic.** PHP Rule ↛ RuleSpace. Keyword ↛ Rule. Mechanic ↛ Rule.

Vue-папка пока держит Keyword/Mechanic внутри Rule (`CODE_GAP`); PHP-модули не ждут сплита фронта.

План-файл пишем, когда шаг начинается. Пока нет файла — контракт шага в этом роудмапе, не «выпало».

## 0. Сделано / блокеры

Versioning 1 (`vt_note`) сделан. ST 18–20, Cache 1 — закрыты.

Хвост часов (карты колонок в `CommitEntry`, `openCluster`) **сделан**.

## 1. Roleplay/Keyword — каркас, не закрытый модуль

[`keyword-plan-01.md`](keyword-plan-01.md). **Каркас PHP сделан** (таблица + `IKeywords`). Модуль после этого шага не считать закрытым: HTTP — шаг 11.

## 2. Roleplay/Mechanic — каркас, не закрытый модуль

[`mechanic-plan-01.md`](mechanic-plan-01.md). **Каркас PHP сделан** (таблица + `IMechanics`). Модуль после этого шага не считать закрытым: HTTP — шаг 12, Engine — «Позже».

Шаги 1 и 2 независимы друг от друга. Оба — до Rule.

## 3. Roleplay/Rule

[`rule-plan-01.md`](rule-plan-01.md). **Каркас PHP сделан.** Кластер `rule*` + `IRules`. `put(code)` резолвит identity; `keywords` — `linkset`; `mechanic_id` — `reference` (hop). HTTP правил **не** в этом модуле.

## 4. RuleSpace 1 — мир и inherit

[`rulespace-plan-01.md`](rulespace-plan-01.md). **Каркас PHP сделан.** Sidecar `code`/имя/`active`. `IRuleSpaces`: add / get / getByCode / getRevision / findInRevision / commit (полный list). Inherit = TX + `keep` состава последней ревизии. Хвост часов `findLatestRevision`. Модуль не закрыт: HTTP — шаг 7.

## 5. RuleSpace 2 — мета и лента ревизий

[`rulespace-plan-02.md`](rulespace-plan-02.md). **Каркас PHP сделан.** Фасад: `update` (Patch name/description; **`code` иммутабелен**), `deactivate` (`active=false`, идемпотентно, не delete часов). Лента `getRevisionList`: `revision`, `publishedAt`, `ruleCount` (= пункты состава, включая tombstone). **`changedCount` отложен** в шаг 6, не заглушка. Хвост часов: `updateSpace` + `getRevisionList` / `RevisionSummary`, не `open(RuleRevisionTable)` из оператора.

## 6. RuleSpace 3 — выборочный commit

[`rulespace-plan-03.md`](rulespace-plan-03.md). **Каркас PHP сделан.** Канон `DEC-015`. Два входа: `commit(list)` как шаг 4; **`commitSelected`** (база + puts + removedCodes), сборка keep/put/tombstone в операторе. Не tagged union в одном `commit`. `IRules` без изменений. Пустые put+removed (все keep) / пустой list → `INVALID`. Кап public — сигнал, не запрет второго метода. `changedCount` ленты не этот заход.

## 7. RuleSpace 4 — HTTP

[`rulespace-plan-04.md`](rulespace-plan-04.md). **Каркас PHP сделан.** Actions в этом модуле, **`ruleSpace.*`** (не `space.*` часов). JSON-вид unix, не Record. Актор без ключей прав (шаг 8). `commitDraft` → `commit` или `commitSelected`. Не actions в `Roleplay/Rule`. Vue `SpaceApi` — шаг 10.

## 8. RuleSpace 5 — права

[`rulespace-plan-05.md`](rulespace-plan-05.md). **Каркас PHP сделан.** HTTP: `space.create` / `view_all` / `edit_all`; sidecar `owner_id`; свои без `*_all`. Не per-object. Vue категория `space` — шаг 10.

## 9. RuleSpace 6 — секции каталога

[`rulespace-plan-06.md`](rulespace-plan-06.md). **Каркас PHP сделан.** Указатель `(revision → section_version)`, шаринг снимка; ревизия иммутабельна; черновик секций не в БД; каталог-only = новая ревизия + all-keep (хвост часов). Не копия дерева на каждый commit правил. Не Vue / seed.

## 10. Vue: Space → RuleSpace

[`rulespace-plan-10.md`](rulespace-plan-10.md). **Каркас Vue сделан.** Папка `Roleplay/RuleSpace`. URL `/space/...` без смены. Клиент `ruleSpace.*`, JSON как PHP. DAG: Rule ↛ RuleSpace; RuleSpace только `provide`. Не Keyword/Mechanic Vue-сплит, не HTTP файла ревизии.

## 11. Keyword HTTP

[`keyword-plan-02.md`](keyword-plan-02.md). **Каркас PHP+Vue сделан.** Публичка `keyword.*` в модуле Keyword: getList / get / create / update / deactivate (`active=false`, не DELETE). Права `keyword.*` на запись; чтение справочника любому актору. Vue остаётся в папке Rule. Не RuleSpace.

## 12. Mechanic HTTP

[`mechanic-plan-02.md`](mechanic-plan-02.md). **Каркас PHP+Vue сделан.** Публичка `mechanic.*` в модуле Mechanic: getList / get / create / update. Нет DELETE/`active`. Права `mechanic.create` / `edit` на запись; чтение любому актору. Vue-клиент каталога в папке Rule. Не Engine, не RuleSpace.

## Позже (явный OPEN, не шаги 4–12)

- Вынос Vue Keyword и Mechanic из папки Rule (закрыть `CODE_GAP`).
- Файл ревизии (импорт/экспорт; коды на границе, не slice-кэш).
- Inherit queue / progress UI — не контракт (`rule-system`).
- Закрытый набор `RuleType` + hydrator/валидация spec по типу — блокер Engine, не persist.
- Хендлеры Engine в модуле Mechanic — блокер Character/Game runtime.
- Второй Versioning-репозиторий; оркестратор нескольких репозиториев.

## Параллелить нельзя

- 3 без 1, без 2, без generic body часов и `openCluster`.
- 4 без 3.
- 5 без 4. 6 без 4. 5 и 6 можно параллелить.
- 7 без 5 и 6 (лента + выборочный commit в JSON `commitDraft`).
- 8 без 7.
- 9 без 4; копирование секций на inherit — после 4, лучше после 6 если секции входят в публикацию.
- 10 без 7.
- 11 без 1. 12 без 2. 11 и 12 независимы от RuleSpace; не блокер шага 4.
- Часы commit внутри Rule; `Versioned*` в SmartTable; eager `Core/*`.
- Keyword/Mechanic как кластер Versioning.
- Карты `keyword`/`mechanic` внутри модуля Rule.
- Секции каталога на `rule_version`.
- Vue-поле `spaceId` на identity `rule`.
- HTTP правил в `Roleplay/Rule`.
