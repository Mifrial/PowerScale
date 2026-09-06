# Нарезка: файл ревизии

**Статус:** план, 2026-09-06. Канон — [`rule-system.md`](rule-system.md), [`DEC-060`](decisions.md). Оператор — [`rulespace-plan-04.md`](rulespace-plan-04.md), [`rulespace-plan-06.md`](rulespace-plan-06.md), [`rulespace-plan-10.md`](rulespace-plan-10.md). Справочники — [`keyword-plan-02.md`](keyword-plan-02.md), [`mechanic-plan-02.md`](mechanic-plan-02.md). Нарезка Rule — [`rule-roadmap.md`](rule-roadmap.md). Фронт — `draft-front_1.2ds/frontend-rules.md`.

Цель линии: переносный JSON **опубликованной** ревизии мира. На внешней границе — semantic `code` (правила, признаки, механики). Persist и HTTP среза **не** меняют форму: `keywordIds` / `mechanicId` остаются внутренними. Файл собирает и разбирает **клиент** из уже существующих `ruleSpace.getRevision`, `keyword.getList`, `mechanic.getList`, draft и `commitDraft`.

Не Rule Engine. Не новые `RuleType`. Не Vue-сплит Keyword/Mechanic. Не actions `ruleSpace.import*` / `export*`.

План-файл пишем, когда шаг начинается. Пока нет файла — контракт шага в этом роудмапе.

## Зачем нет HTTP файла

Отдельный action «принять/отдать файл» не нужен: файл — документ для человека и для переноса между стендами/мирами. Сервер уже отдаёт срез и принимает черновик. Дублировать тот же состав вторым транспортом (multipart/JSON blob) — второй контракт, вторая трансляция id↔code и риск разъехаться с `commitDraft`. Трансляция живёт в Vue-сервисе файла. Если когда-нибудь понадобится серверный импорт без браузера — это новая задача, не шаг этой нарезки и не OPEN «обязательно потом».

## 0. Сделано / блокеры

Keyword HTTP и Mechanic HTTP **сделаны** (шаги 11–12 Rule). Клиент каталогов есть (папка Rule, `CODE_GAP` сплита не этот трек).

Экспорт v3 сделан (шаг 2). Импорт в черновик сделан (шаг 3). Снимки справочника пишутся в каталог (шаг 4): used upsert. HTTP среза и `commitDraft` — планы RuleSpace 4–6/10; их JSON **не** равен файлу.

## 1. Формат v3 и разбор

[`revision-file-plan-01.md`](revision-file-plan-01.md). **Каркас Vue сделан.** Envelope, внешнее правило без внутренних id, ошибки `{ code, path, stage }`. Serialize/parse/assemble/materialize. Не HTTP файла. Не `commitDraft`.

## 2. Экспорт опубликованной ревизии

[`revision-file-plan-02.md`](revision-file-plan-02.md). **Каркас Vue сделан.** Клиент: срез → свежий `keyword.getList` / `mechanic.getList` → файл v3. Tombstone и секции входят. Снимки только used. Нет строки справочника → ошибка, не omit. Кап 500 как у HTTP, без `*.getByCode`. Не импорт-preview.

## 3. Импорт в черновик

[`revision-file-plan-03.md`](revision-file-plan-03.md). **Каркас Vue сделан.** Parse v3 → preview → apply в `useRuleDrafts` и `sectionCatalog.saveDraft`. Не `commitDraft`. База — **latest**. Есть черновик — тройка: Отмена / приоритет черновика / приоритет выгрузки. Tombstone в файле — `active: false` в draft, не `removedCodes`. Публикация — PublishDialog (`DEC-015`). Снимки справочника на этом шаге ещё не пишутся в каталог.

## 4. Upsert справочника из снимков

[`revision-file-plan-04.md`](revision-file-plan-04.md). **Каркас Vue сделан.** Импорт мира досоздаёт и обновляет **used** keyword/mechanic из файла (`create`/`update`, keyword `deactivate`). Не дамп всего каталога. Не реактивация. Не DELETE mechanic. Затем overlay шага 3. Не HTTP файла.

## Позже (не шаги 1–4)

- HTTP файла / CLI импорта без браузера.
- Смена JSON `commitDraft` на codes вместо id.
- Режим «файл = полный состав» без флага `removeMissing`.
- Миграция legacy v1/v2 с `keywordIds` (отклонять, не угадывать id).
- Вынос Vue Keyword/Mechanic.
- Закрытый enum `RuleType`, hydrator spec, spell, Engine.

## Параллелить нельзя

- 2 без 1.
- 3 без 1; 3 без 2 не начинать: иначе импорт тестируют на фикстурах, а кнопка Экспорт ещё пишет v2.
- 4 без 3: upsert каталога встраивается в apply/preview шага 3.
- 2 и 3 не параллелить в одной сессии: разные критерии (стабильный dump vs apply/diff).
- Менять JSON `keyword.*` / `mechanic.*` / среза / `commitDraft` ради файла. Vue `IMechanicApi` create/update — **дописать клиент** под уже существующие actions, не новый PHP.
- Класть codes в slice-кэш часов.

## Границы модулей

| Кто | Файл |
|---|---|
| RuleSpace Vue | envelope, UI, секции, оркестрация preview/apply, upsert снимков |
| Rule Vue | внешний payload правила; клиент mechanic create/update |
| Keyword / Mechanic PHP | без изменений JSON; create/update/deactivate как есть |
| PHP Rule / RuleSpace | не этот трек |
| Versioning | не знает о файле |
| Draft persist | apply импорта; ключи localStorage не равны файлу ревизии |
