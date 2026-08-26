# Кампания ревью фронта (актуальный промт)

Канон: [`draft-front_1.2ds/frontend-rules.md`](../../draft-front_1.2ds/frontend-rules.md) (все разделы, особенно DAG §2, Utils, §7). Сессия: [`AGENTS.md`](../../AGENTS.md). ТР: [`docs/tr/TR.md`](../tr/TR.md) §13 (канон 2026-08-25, DAG 2026-08-26) и §15 п.6–7.

**Не канон:** `docs/review/context-*.md` и старый [`review-module-prompt.md`](review-module-prompt.md) — история. Не «всем только Core», не «не дублировать находки волны N», не план работ в конце ревью. Смотри текущий код и `frontend-rules.md`.

Живой список замечаний: [`docs/review/findings.md`](../findings.md). Один файл на всю кампанию.

Порядок кампании (не смешивать в одной сессии без явной просьбы):

1. **Фаза DAG** — промт A. **Сделано 2026-08-26** (итог в `findings.md` секция DAG).
2. **Ревью модулей** по одному: Rule → Chat → Character → Game. Промт B + один блок специфики. Только анализ, правки не делать.
3. **Порядок правок** по `findings.md`. Промт C. Потом отдельные сессии «делай пункт N».

---

## Общее для любой сессии кампании

- Порт 3000 не трогать. Браузер MCP не использовать. Читать лениво.
- Конфликт код ↔ правила: правим код или явно правило в том же заходе.
- Межмодульный доступ — публичные точки (`init.ts`, `useXxxStore()`, `Interface/`/`Dto/`/`Enum/`). Core (Engine/UI) — напрямую. `Core/Engine` не импортирует `Core/UI`.
- Плагин: хост не импортирует донора; донор зовёт `register*` из своего `init`.
- `Utils/` — независимые stateless мелочи без хозяина-класса. Домен (удар, DOT, валидация спеки) — не Utils.
- Для нетривиальных развилок — варианты с плюсами/минусами, рекомендация и обоснование; без молчаливого выбора.
- Гейт при правках кода: `npm run format` + `npm run lint` + `npx vue-tsc --noEmit` + `npm run test` из `draft-front_1.2ds/` (vitest часто нужен `all` из‑за sandbox).

---

## Промт A — фаза DAG (новая сессия)

Скопировать от сюда до конца блока A.

Ты правишь **только межмодульные зависимости** фронта `draft-front_1.2ds` под `frontend-rules.md` §2 (прикладной DAG). Не рефакторить внутренности модуля (не переносить hitRoll в сервис, не пилить Vue, не чинить F17), если это не нужно, чтобы убрать запрещённый импорт.

Канон рёбер (вниз):

- Rule ← только Core. Rule **не** импортирует Space.
- Space ← Core + Rule (Space владеет ревизией).
- Chat ← только Core. Chat **не** импортирует Roleplay (ни Rule, ни Mechanic, ни Character/Game).
- Character ← Core + Rule + публичный init Chat (`register*`).
- Game ← Core + Character + Rule + Chat-хост + Space.
- Справочник живёт у хозяина: формы типа урона — Rule, не Character.

Инвентарь известных инверсий (проверь и дополни grep по `from '@/modules/`):

- Rule-страницы / композаблы → `useSpaceContext` / стор Space.
- `Messages/Chat` → `Roleplay/Rule` (`IChatRulesProvider`, `ChatRulesResolution`, `init.ts`).
- Rule → `Character/Constant/DAMAGE_TYPE_FORMS`.

Как чинить (рамка, не единственный патч):

- Rule↔Space: `spaceId` / ревизия — проп, роут или inject-ключ, объявленный в **Rule**; Space только `provide`. Не тащить контекст в Core.
- Chat↔Rule: контракт хоста **opaque / generic**, без Dto Rule. Провайдеры Game/Character кладут правила в свой слой, Chat видит только то, что нужно чипам (имена по code, spaceId, revision) — уточни форму, если не очевидно: плюсы/минусы, рекомендация, потом код.
- DAMAGE_TYPE_FORMS: перенос в Rule, импорты Character/Game/Rule поправить.

Критерий готово: нет запрещённых импортов; тесты/tsc зелёные; в `docs/review/findings.md` секция DAG — что сделано и что осталось сознательно.

Коммит только если попросят.

---

## Промт B — ревью одного модуля (новая сессия, READ-ONLY)

Скопировать **общий блок B** + **один** блок специфики. Одна сессия = один модуль. Очередь: Rule → Chat → Character → Game. Space — только если явно сказано.

**Ничего не менять** (ни код, ни `frontend-rules.md`, ни ТР). Только читать и дописать [`docs/review/findings.md`](../findings.md) в секцию этого модуля. Сначала прочитай `findings.md` целиком (DAG + уже открытые пункты). Не дублировать: сознательные хвосты фазы A в секции DAG — не переносить в модуль, пока это не регресс. Старый `review-module-prompt.md` не использовать.

Ты — ревьюер. Соответствие `frontend-rules.md` §§1–7 + качество. Фаза A выпрямила граф; запрещённый импорт — пункт P1/P2 «регресс DAG», не чинить.

### Общий блок B (копировать всегда)

Чек-лист (не «всем только Core»):

1. SFC: script→template→style; одна задача; ~250 строк — сигнал декомпозиции по задачам, не пилить по счётчику.
2. `.vue` только UI; домен — классы `Service/` (не файлы функций); синглтоны `Service/Instance/`; спеки `*SpecService`; Utils — только независимые мелочи; композаблы в `Composables/`, не в `Component/`.
3. Анатомия; в корне модуля только `init.ts`/`routes.ts`; один экспорт на TS-файл (исключения: init/routes; Mock — фикстуры, мультиэкспорт ок).
4. Импорты: публичные точки (`init.ts`, `useXxxStore()`, `Interface/`/`Dto/`/`Enum/`). Locator / `getXxxApi()`, не прямой класс API. DAG **после A**:
   - Rule = Core + Chat `register*` / Dto сегмента чипа; **не** Space.
   - Chat = Core; opaque `ChatRulesContext`; **не** Roleplay.
   - Character = Core + Rule + публичный Space + Chat `register*`; **не** Game.
   - Game = Core + Character + Rule + Chat `register*` + Space.
5. Плагин: хост без Dto донора; донор зовёт `register*` из своего `init`.
6. Именование §3.
7. Типы только в Dto/Interface/Enum; `import type`; без `any`.
8. Реактивность: не деструктурировать props/store вслепую; plain Dto в state; `structuredClone`.
9. §7: слои; DOM (виртуализация длинных списков, не считать в шаблоне, снимать слушатели); запросы (лишний fetch/poll, watch, debounce); XSS (`v-html`, чат), секреты в моках, валидация входа.
10. YAGNI vs копипаста; F17; тесты `__tests__/` зеркалят модуль, F8 (критичная логика, без UI-mount).

Не сканируй весь репозиторий. Иди по `init.ts`, страницам, сервисам, импортам из чужих модулей.

Формат **одной** записи (дописывай в открытые секции модуля, id без дыр: следующий свободный `{Rule|Chat|Character|Game}-{n}`):

```
### {Rule|Chat|Character|Game}-{n} — {короткий заголовок}
- Модуль: Roleplay/Rule
- Критичность: P1 (баг/контракт) | P2 (правило/домен) | P3 (улучшение)
- Где: path:line
- Правило: §…
- Суть: …
- Зависит от: {id} / ничего
- Фикс (черновик): …
```

В конце сессии в чат (не вместо файла): сколько пунктов, 2–3 самых жёстких, нетривиальные развилки с плюсами/минусами. **План работ по модулю не писать и не выполнять.**

### Специфика Roleplay/Rule

Модуль: `draft-front_1.2ds/src/modules/Roleplay/Rule/`.

Контракты `init.ts`: `getRuleApi` / `getKeywordApi`, `registerRevisionRulesFetcher`, `useRuleHostContext` / `ruleHostContextKey`, донор Chat (`RuleChip`, token source). Спеки — `Service/Spec/`. Механики и валидация — классы, не Utils. Не ходить в Space store / `useSpaceContext`. Не ревьюить страницы Space, кроме того, что Rule от них получает через inject/фетчер.

### Специфика Messages/Chat

Модуль: `draft-front_1.2ds/src/modules/Messages/Chat/`.

Хост: реестры `register*` без Dto Rule/Game/Character. `IChatRulesProvider.resolve` → opaque `ChatRulesContext`. Sync (`ChatSyncService` и вотчеры), XSS сообщений, виртуализация списка. Не ревьюить доноров Game/Character, кроме соблюдения контракта хоста (нет утечки Dto Roleplay в Chat).

### Специфика Roleplay/Character

Модуль: `draft-front_1.2ds/src/modules/Roleplay/Character/`.

Публичный Space (`init` / сторы / Dto) — канон. Game только через зарегистрированные `IInGameSheetSource` и `ICharacterSessionOverlay`. `buildCharacterChatRulesContext` — имена/ссылки, без RollEngine. Фокус: редактор листа, черновик, модерация на уровне листа. Не уходить в боевой слой Game.

### Специфика Roleplay/Game

Модуль: `draft-front_1.2ds/src/modules/Roleplay/Game/`.

Донор Chat: `buildChatRulesContext`, провайдеры ревизий, вложения броска. Ревизию берёт из Space сам. Combat / hit / DOT: Utils vs класс-сервис (§2). Не тащить правки внутрь Character (лист — через плагины Character, не наоборот).

---

## Промт C — порядок правок (после всех B, READ-ONLY к коду)

Прочитай `docs/review/findings.md`. Предложи порядок закрытия открытых пунктов: сначала то, от чего зависят другие; не прыгать по модулям зря; P1 раньше косметики P3, если нет блокера. Для спорных кластеров — плюсы/минусы. Итог допиши в `findings.md` секцией «Очередь правок». Код не менять, пока не скажут «делай очередь с пункта …».
