# План Keyword 3 — Vue-модуль `Roleplay/Keyword`

**Статус:** каркас Vue сделан, 2026-09-06. Нарезка Vue — [`vue-rule-split-plan-01.md`](vue-rule-split-plan-01.md) заход 1. HTTP уже в PHP Keyword ([`keyword-plan-02.md`](keyword-plan-02.md)). Эталон переезда папки — [`rulespace-plan-10.md`](rulespace-plan-10.md). Канон рёбер — [`architecture.md`](architecture.md). Фронт — `draft-front_1.2ds/frontend-rules.md`. `DEC-062`, `DEC-064`.

Цель: справочник признаков живёт в **`src/modules/Roleplay/Keyword/`**. Locator, `init`, админ-роуты `/admin/keywords`, CRUD UI, мок и `useKeywords` — этот модуль. Rule хранит `keywordIds` и валидирует ссылки, но не владеет HTTP/стором/страницами. Не PHP. Не Mechanic. Не смена URL. Не seed. Не переименование `fetchTags` / `*Tag` в сторе (хвост эскиза, не этот заход).

`CODE_GAP` после влива: **только Mechanic** ещё в папке Rule.

## Термины

| Термин | Смысл |
|---|---|
| Каталог Keyword | Строка справочника: `id`, `code`, `name`, `description`, `active`. |
| Ссылка на правиле | `Rule.keywordIds: number[]`. Хозяин — Rule. |
| Код в spec | `keyword_code` / `ABILITY_TYPE_KEYWORDS` / `KeywordRef`. Хозяин — Rule, не модуль Keyword. |
| `RevisionFileKeyword` | Вид файла ревизии в RuleSpace. Не Dto каталога. |

## Решения

### 1. Папка и публичка

Новый модуль:

```text
Roleplay/Keyword/
  init.ts
  routes.ts
  Interface/IKeywordApi.ts
  Dto/Keyword.ts
  Dto/CreateKeywordData.ts
  Dto/UpdateKeywordData.ts
  Service/KeywordApi.ts
  Store/keywords.ts
  Composables/useKeywords.ts
  Page/KeywordsListPage.vue
  Page/KeywordEditPage.vue
  Constant/Grid/keywords/columns.ts       # как User/Notifications: Constant/Grid/{сущность}/; не плющить
  Constant/Grid/keywords/filterFields.ts
  Constant/Permission/KEYWORD_PERMISSION_CATEGORY.ts
  Constant/Permission/KEYWORDS_ADMIN_SECTION.ts
  Mock/mockKeywords.ts
  Mock/mockKeywordApi.ts
  __tests__/Store/keywords.test.ts
```

Имена классов, Pinia id `keywords`, actions `keyword.*` — без смены.

`init.ts` публикует только:

- `registerKeywordApi` / `getKeywordApi`
- `useKeywords` (узкий фасад; **не** реэкспорт `useKeywordStore`)
- `registerKeywordModule`

Тип `Keyword` соседи берут из `Dto/Keyword.ts`, не баррель.

Locator: **`Roleplay.Keyword.Service.KeywordApi`**. Старый ключ `Roleplay.Rule.Keyword.Service.KeywordApi` не оставлять и не алиасить.

Keyword → Engine, UI, User (плагин). **Keyword ↛ Rule / RuleSpace / Character / Game.**

### 2. Регистрация

`main.ts` (composition root):

- import `registerKeywordApi` / `registerKeywordModule` из `Keyword/init`;
- mock/real `KeywordApi` из папки Keyword;
- `registerKeywordModule()` рядом с `registerRuleModule()`, не внутри Rule.

`registerKeywordModule`:

- `registerPermissionCategory(KEYWORD_PERMISSION_CATEGORY)`
- `registerAdminSection(KEYWORDS_ADMIN_SECTION)`

`registerRuleModule` больше не трогает категорию `keyword` и секцию «Признаки».

### 3. URL и склейка admin

Пути **`/admin/keywords`**, `new`, `:id/edit` и имена роутов `Keywords` / `KeywordNew` / `KeywordEdit` без смены. `requiresAny` как сейчас (`keyword.view` / `create` / `edit`).

`Keyword/routes.ts` экспортирует `adminChildren` (только keywords).

`Roleplay/routes.ts`:

```text
roleplayAdminChildren = [
  ...keywordAdminChildren,
  ...ruleAdminChildren,   // mechanics остаются в Rule до захода Mechanic
]
```

Имя `tagAdminChildren` убрать. `Rule/routes.ts` после выреза keywords оставляет только `mechanics` (+ `ruleCtxChildren` без изменений).

Оболочка `/admin` User не переезжает (`DEC-062`).

### 4. Поверхность: стор чужим нельзя

Сейчас `useKeywordStore` импортируют из Rule: `RuleEditPage`, `RuleDetailPage`, `AbilityEditor`, `RuleSlider`. После переезда это чужой `Store/` — линтер запретит. Страницы Keyword (`KeywordsListPage`, `KeywordEditPage`) остаются на своём Pinia.

Правило: чужой модуль — только `useKeywords` / `getKeywordApi` / `Dto` / `Interface` / `Mock`.

Чтение списка и `fetchTags` / `error` — `useKeywords()`. CRUD стора (`createTag`, `fetchTag`, `deactivateTag`, `currentTag`, `loading`) снаружи Keyword не звать и в фасад не добавлять: ими пользуются только свои страницы. `loading` списка на админке остаётся в сторе, не в `useKeywords`.

`useKeywords` отдаёт `computed`. В `<script>` у бывших `keywordStore.keywords` / `keywordStore.error` — `.value`. В шаблоне авторазвертка.

`RuleSlider`: `props.keywords ?? useKeywords().keywords` (то же `.value` в script). Не прокидывать стор.

`getKeywordApi` снаружи — там, где уже конструктор (RuleSpace `revisionFileCatalogService` / `revisionFileCatalogSyncService`). Редактору Rule достаточно `useKeywords`.

### 5. DAG потребителей (явные рёбра)

Импорт `Dto/Keyword`, `IKeywordApi`, `useKeywords`, `getKeywordApi`, mock-фикстур — это ребро на Keyword.

| Модуль | Зачем | Канон |
|---|---|---|
| Rule | `Dto/Keyword` (валидация, лейблы, карточки, спеки); `useKeywords` в редакторе/слайдере; `Mock/mockKeywords` из `mockRules` | уже Rule → Keyword |
| RuleSpace | `getKeywordApi` в Instance catalog/sync; `IKeywordApi`/`Keyword` в сервисах и тестах файла ревизии; `useKeywords` в PublishDialog | **добавить** RuleSpace → Keyword (публично). PHP RuleSpace ↛ Keyword не меняем |
| Character | `Dto/Keyword` (сервисы, вкладки, тесты); `useKeywords` (AbilityTab, CharacterSheetEditor, useCharacterCardDraft); mock в `attractiveness.test` | **добавить** Character → Keyword |
| Game | `useKeywords` (`CombatCardPanel`, `initiativeCharacteristic`) | **добавить** Game → Keyword |

Полный список путей — grep `Roleplay/Rule/Dto/Keyword`, `useKeywords`, `useKeywordStore`, `mockKeywords`, `IKeywordApi`, `getKeywordApi`. Не копировать в план. User (`AdminDashboard`, фикстура секции в `permissionRegistry.test`) URL не меняет, импортов Keyword не имеет.

Реэкспорта `getKeywordApi` / `useKeywords` из `Rule/init` **нет**. Иначе заход не закрывает долг.

Фикстуры: `Rule/Mock/mockRules.ts` и тесты Rule/Character/RuleSpace, которые берут `keywords` из `mockKeywords` — импорт **`Keyword/Mock/mockKeywords`** (мок между модулями допустим).

### 6. Что не переезжает

Оставляем в Rule (это не каталог):

- `Dto/Ability/KeywordRef.ts`
- `Constant/Ability/ABILITY_TYPE_KEYWORDS.ts`, `GROUP_DOMAIN_KEYWORD_CODES.ts`, `ABILITY_TYPE_DISTINCTIVE_KEYWORD.ts`
- `Constant/Item/ITEM_MODIFIER_PRICE_KEYWORD_PRIORITY.ts`
- `Rule.keywordIds`, валидация ссылок в `RuleValidationService`

В Game: `Constant/Combat/ATTACK_KEYWORD_IDS.ts` (id из контента боя, не модуль Keyword).

В RuleSpace: `Dto/RevisionFileKeyword.ts` и ключи файла.

### 7. Docs этого захода

- `architecture.md`: у Keyword снять «Vue-папка CODE_GAP»; у CODE_GAP оставить только Mechanic; дописать рёбра Character / Game / RuleSpace → Keyword.
- `TR.md`: ссылка на этот файл (план 2 оставить как HTTP).
- `vue-rule-split-plan-01.md`: todo keyword отметить, когда код влит. Не закрывать весь CODE_GAP и не писать Character → Mechanic в этом заходе.
- `rule-roadmap.md`: Vue Keyword вынесен; Mechanic — ещё «Позже»/CODE_GAP.
- `keyword-plan-02.md` не переписывать историю; одна строка «Vue-папка — план 3».

Не закрывать весь Vue-сплит в roadmap, пока Mechanic в Rule.

### 8. Тесты и гейт

Перенести `Rule/__tests__/Store/keywords.test.ts` → `Keyword/__tests__/Store/keywords.test.ts`; `registerKeywordApi` с нового init.

Поправить импорты в тестах Character / RuleSpace / Rule (`mockKeywords`, тип `Keyword`, `IKeywordApi`).

Eslint: модуль определяется путём `modules/{Group}/{Name}/`, списка модулей нет. Отдельного правила `filename` в фронте **нет** (это было в umbrella-плане по ошибке). В `no-foreign-module-internals.test.ts`: Game не импортирует `Keyword/Store`; можно `Keyword/init` и `Dto/Keyword`. `Roleplay/routes.ts` — файл группы, импорт `Keyword/routes` допустим.

Не phpunit. Не mount Vuetify. Не трогать PHP cs.

Гейт: в `draft-front_1.2ds` — `npm run format` → `npm run lint` → `npx vue-tsc --noEmit` → `npm run test`. Dev-сервер не запускать.

## Порядок работ (один заход)

1. Создать папку, перенести файлы, поправить внутренние `@/`-пути, locator, `init`/`routes`.
2. Склеить `roleplayAdminChildren`; вырезать keywords из `Rule/routes.ts` и `registerRuleModule`.
3. `main.ts`.
4. Заменить чужие `useKeywordStore` / старые пути Dto/Api/mock.
5. Тесты eslint + vitest затронутых файлов, затем полный гейт.
6. Документы §7.

## Что не входит

- Mechanic Vue.
- HTTP правил, PHP Keyword.
- Вынос `/admin/keywords` из оболочки User.
- Реактивация, смена `code`, физический DELETE, seed.
- Переименование методов `fetchTags` / `createTag`.
- Сужение публички Rule (спеки, Engine).

## Риски

1. Забыть `RuleSlider` / `AbilityEditor` / `RuleEditPage` / `RuleDetailPage` — eslint internals.
2. `useKeywords` без `.value` в script — tsc.
3. Реэкспорт из `Rule/init` «для совместимости» — заход фиктивный.
4. `mockRules` и тесты import/mock без смены пути.
5. Два ключа локатора: Instance RuleSpace и стор Keyword смотрят в разные.
6. `PublishDialog` / `CharacterSheetEditor`: смешанный импорт из `Rule/init` — разрезать на Rule + Keyword.

## Todo

- [x] **folder** — дерево §1, locator, init, routes.
- [x] **wire** — main, Roleplay admin concat, Rule без keyword-плагина.
- [x] **consumers** — Rule / RuleSpace / Character / Game: публичка Keyword, не Store.
- [x] **tests** — keywords store + импорты + eslint internals.
- [x] **docs** — architecture: Keyword без CODE_GAP папки; Mechanic ещё долг; три новых ребра.
