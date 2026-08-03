# Контекст ревью 4 — доработка модуля Core/Engine и правил фронта

Живой файл волны (2026-08-03). Детальная вычитка модуля `Core/Engine` +
актуализация `frontend-rules.md` по итогам модуля. Остальные модули — вне волны
(см. «Остатки на следующие круги»).

## Решения (2026-08-03)

1. **Типы — только в типовом слое.** Именованные типы объявляются только в
   `Dto/`/`Interface/`/`Enum/`, один тип на файл. В файлах с кодом (`Service/`,
   `Value/`, `Composables/`, `Component/`, `.vue`) именованных типов нет;
   тривиальная одноразовая форма — inline в сигнатуре, без имени. Обоснование:
   «приватные» типы стабильно протекают в шаринг (9 Service-файлов с
   экспортируемыми типами) — вынос сразу исключает повторное вытаскивание.
   `frontend-rules.md` §3.
2. **`Enum/` не сливается с `Dto/`** — разделение сохраняется: `Enum/` = плоский
   string-literal union без payload, `Dto/` = структуры и юнионы с payload
   (граница выстрадана в context-3). Формулировка границы усилена в §3.
3. **Composables — только корневая `Composables/` модуля** (подпапки по
   группировке); в `Component/` композаблов нет. stateless → `Utils/` или
   приватная функция (YAGNI). Кросс-модульные: отображение —
   `Core/UI/Composables/`, инфраструктура — `Core/Engine/Composables/`. §2.
4. **Engine/UI**: общий UI проекта живёт в `Core/UI`, не в `Core/Engine`;
   `Core/Engine` не импортирует `Core/UI` (зависимость UI → Engine). §2.
5. **Именование усилено**: доменный смысл + терминология бэка (`serviceCode`, а
   не `key`); generic-имена (`key`/`value`/`data`/`map`/...) — только при истинной
   родовости; локальные переменные и параметры — как публичные; булевы —
   предикаты; однобуквенные — только math/loop. §3.
6. **Комментарии «только почему»** вместо тотального запрета: нетривиальные
   инварианты и причины, `TODO` с обоснованием; «что»/«как» — через осмысленные
   имена и код. §3.
7. **Классы-сервисы**: поля-члены, не переприсваиваемые после инициализации
   (инжектированные зависимости, константные настройки) — `private readonly`;
   переприсваиваемые — обычные `private` (фикс по замечаниям Sonar). §3.

## Объём волны (согласовано)

1. Доки: создать context-4.md; актуализировать TR.md (структура Core/Engine + UI,
   решения, блок волны).
2. Правила: frontend-rules.md §2/§3 (см. Решения).
3. Core/Engine:
   - типы → `Dto/`: `DimensionalNumberValue`/`DimensionalNumberBaseRange` →
     `Dto/DimensionalNumber.ts` (~30 импортеров + тест), `HttpClientConfig` →
     `Dto/HttpClientConfig.ts`, `HttpResponse` → `Dto/HttpResponse.ts`;
   - `init.ts`: ре-экспорты из `Dto/`, удалить мёртвый ре-экспорт `serviceLocator`;
   - `Engine.runAction` — `encodeURIComponent(action)`;
   - `ServiceLocator` — `key`→`serviceCode`, `value`→`service`, `map`→`services`;
   - `useAbortable` — убрать лишний `ref` (контроллер не переприсваивается);
   - `DateTime` — приватный хелпер относительного формата, `h`/`m`→`hours`/`minutes`;
   - тест `container.test.ts` → `__tests__/Service/serviceLocator.test.ts`;
   - комментарии `CsrfApi`/`DimensionalNumber` по политике «только почему».
   - инжектированные зависимости во всех классах-сервисах — `private readonly`
     (15 файлов: Core/Engine + Core/Auth/User + Messages + Roleplay);
   - поля `ServiceLocator.services` и `CsrfApi.cookieName` — `private readonly`
     (не переприсваиваются); `ChatSyncService.lastSync` — mutable, без изменений.
4. Core/UI (минимальный тач): `useGridPage` → `Core/UI/Composables/` (4 страницы);
   `useFilterBuffer` → `Core/UI/Composables/` (`FilterBar.vue`, `FilterBar/index.ts`).
5. Верификация: `vue-tsc --noEmit` + `vitest run`.

## Рескан-находки Core/Engine

- **Композаблы**: `useGridPage` зависит от `Core/UI/Dto` (Row/Sort/Pagination/
  FilterValue) — зависимость Engine→UI вопреки разделению; это отображение →
  `Core/UI/Composables/`. `useAbortable` — инфраструктура, в Engine на месте.
- **Типы в файлах кода**: `Value/DimensionalNumber.ts` экспортирует
  `DimensionalNumberValue`/`DimensionalNumberBaseRange` (контракт данных, ~30
  импортеров); `Service/HttpClient.ts` экспортирует `HttpClientConfig` и держит
  приватный `HttpResponse<T>`. По рулингу — все в `Dto/`.
- **Имена**: `ServiceLocator.set/get(key, value)` + `map` — generic-имена при
  доменном «строковый код сервиса» (зеркало бэка `ServiceLocator.php`).
- **init.ts**: ре-экспорт `serviceLocator` не используется никем (все тянут из
  `Service/ServiceLocator`) — мёртвый экспорт + утечка инфраструктуры.
- **Engine.runAction**: `` `/run?action=${action}` `` без `encodeURIComponent`.
- **Мелочи**: `useAbortable` — `ref` вокруг никогда не переприсваиваемого
  контроллера; `DateTime` — дубль веток относительного формата, имена `h`/`m`;
  тест назван `container.test.ts` (тестирует ServiceLocator) — по §5 тесты
  зеркалят структуру модуля.
- **Соответствует правилам**: `Interface/ICSRFApi`, `Dto/ActionResponse`,
  `Mock/mockCsrf` (типизирован контрактом), `Value/DateTime`, отсутствие `any`,
  классы-сервисы статeless, `init.ts` как точка сборки, `__tests__/Value/…`
  зеркалит структуру.

## Правила верификации

- После каждой фазы: `vue-tsc --noEmit` + `vitest run`.
- Dev-сервер на 3000 не трогать; `vite build` не делаем (веха релиза, пользователь).

## Остатки на следующие круги

- «Типы в Service» в Rule/Game/Chat (9 файлов): `ChatSyncConfig`,
  `RuleValidationService` (`ReferenceTargetType`/`ReferenceError`/
  `AbilityStructureError`/`RaceStructureError`), `RuleReferenceService`
  (`NamedOption`), `RuleDiffService` (`PublishDiff`/`ProblemEntry`),
  `RuleDraftService` (`CreateDraftParams`), `RollService`
  (`ParsedRollCommand`/`ParsedRollFormula`/`DiceRng`).
- Типы данных в `Interface/*Api`: `INotificationApi` (`NotificationFilters`/
  `NotificationPage`), `INotificationTemplateApi` (`CreateTemplateData`/
  `UpdateTemplateData`), `IKeywordApi`, `ICommandHandler` (`ParsedCommand`).
- Core/UI типы в файлах кода: `useFilterBuffer` (`FilterBuffer`), `filterValues.ts`
  (`ActiveChip`/`MaybeFilterValue`) — после переноса композаблов в `Composables/`.
- Декомпозиция AbilityEditor 495 / AbilityCard 294 и др. (хэнд-офф context-3).

## Закрытие волны (2026-08-03)

**Состояние на закрытие:** `vue-tsc --noEmit` чисто, `vitest` 177/177.
Выполнено: правила (frontend-rules.md §2/§3 — типы в типовом слое, Composables,
Engine/UI, именование, комментарии, `readonly`-зависимости), доки (context-4.md,
TR.md), Core/Engine (типы → `Dto/`, init.ts, ServiceLocator, runAction,
useAbortable, DateTime, тест), Core/UI (useGridPage → `Core/UI/Composables/`,
useFilterBuffer → `Composables/`), `readonly`-члены классов (15 файлов —
инжектированные зависимости, + `ServiceLocator.services`/`CsrfApi.cookieName`).
`Core/Engine` больше не импортирует `Core/UI` (проверено grep'ом).
Гит: изменения в контексте волны; коммит — по решению пользователя.

**Остатки на следующие круги:** типы в Service Rule/Game/Chat (9 файлов), типы
данных в `Interface/*Api`, `FilterBuffer`/`ActiveChip`/`MaybeFilterValue`
(Core/UI), декомпозиция AbilityEditor/AbilityCard (хэнд-офф context-3).

