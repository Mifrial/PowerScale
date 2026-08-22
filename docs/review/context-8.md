# Контекст ревью 8 — «Один экспорт на файл» (фронт-wide sweep)

Волна 2026-08-03. По решению пользователя: файл экспортирует ровно одну
сущность; исключения — только `init.ts` и `routes.ts`. Применено по всему
фронту (Core + Roleplay + Messages + router/shell).

## Правило (frontend-rules.md)

- Дополнено: **«Один экспорт на файл»** (стр. 10). Исключения — `init.ts` и
  `routes.ts` (включая связку `routes` + `adminChildren`). Всё прочее, включая
  парные типы, сервисные синглтоны, наборы констант — один экспорт на файл.
- Новый паттерн: **сервисные синглтоны — `Service/Instance/`**. Класс один на
  файл (`Service/XService.ts`), экземпляр — `Service/Instance/xService.ts`.
  НЕ в `init.ts` (тянет Vue-зависимости → ломает unit-тесты, см. ниже) и НЕ в
  файле класса (имена `XService`/`xService` различаются только регистром —
  коллизия case-insensitive ФС, TS1149).

## Найденные в ходе волны проблемы

1. **Case-коллизия (TS1149)**: синглтон рядом с классом в одном каталоге
   (`AccessService.ts` + `accessService.ts`) — файлы различаются только
   регистром. Решение: `Service/Instance/`.
2. **Синглтон в `init.ts` ломает тесты**: `Game/init.ts` импортирует
   Vue-компоненты → `rollParser/rollCalc/chat.store` падали с
   `Unknown file extension ".css"`. Решение: синглтоны в чистых TS-файлах
   `Service/Instance/`, тесты импортируют их напрямую.

## Выполнено

- **Utils**: `datetime.ts`→`formatDatetime.ts`; `formMapper.ts`→`ruleToForm.ts`;
  `filterExtract.ts`→`extractFilterValue.ts`+`extractStringFilter.ts`;
  `profile.ts`→`initials.ts`+`displayName.ts`. `gridSettings.ts`/`filterSettings.ts`
  (по 3 функции) → `Utils/gridSettings/` + `Utils/filterSettings/` по функции на файл.
- **registries** (общий Map) → синглтон-объекты `rendererRegistry`/`filterHandlerRegistry`
  (один экспорт, register/get как методы).
- **Dto/Interface**: payload-типы из `Interface/*Api` → `Dto/`
  (CreateUserData/UpdateUserData, CreateGroupData/UpdateGroupData,
  CreateKeywordData/UpdateKeywordData, CreateMacroData/UpdateMacroData,
  CreateTemplateData/UpdateTemplateData, NotificationFilters/NotificationPage,
  ParsedCommand, ChatToolbarContext, ChatSyncConfig); парные типы разведены
  (ActionResponse/ActionError, DimensionalNumberValue/DimensionalNumberBaseRange,
  PublishDiff/ProblemEntry, ReferenceTargetType/ReferenceError/AbilityStructureError/
  RaceStructureError, NamedOption, CreateDraftParams, ParsedRollCommand/ParsedRollFormula/
  DiceRng). Баррель `IPermissionRegistry` упразднён.
- **Interface/Grid**: ColumnDrag/ScrollEars/ColumnResize/FilterBuffer разведены по одному интерфейсу на файл.
- **Service**: классы очищены от синглтонов; синглтоны — `Service/Instance/`
  (Core/User accessService, Core/Auth passwordValidatorService, Game rollService,
  Rule: ability/item/resource/process/raceSpec + ruleDraft/Diff/Reference/Validation).
- **Constant**: permissions по константе на файл (`Constant/Permission/`),
  манифесты гридов (columns/filterFields) в `Constant/Grid/<entity>/`,
  `rollLimits`→8 файлов в `Constant/Roll/`, `chatType`→CHAT_CONFIG+chatIcon+chatColor,
  `chatStoreConfig`→PAGE_SIZE+MAX_STORED.
- **Enum**: `ChatPermission` из usePermissions → `Enum/ChatPermission`.
- **router/shell/Store**: `router/access.ts`→`access.ts`+`RouteAccessContext.ts`+
  `RouteAccessDecision.ts`; `router/meta.ts`→`BreadcrumbItem.ts`+`BreadcrumbResolver.ts`+
  `meta.ts` (только module augmentation); `shell/navItems.ts`→`shell/Dto/NavItem.ts`+
  `navItems.ts`; `Space/Store/spaceRevision.ts`→`Dto/RevisionKind.ts`+`Dto/RevisionContext.ts`+store.

## Закрытие волны

**Состояние:** `vue-tsc --noEmit` чисто, `vitest run` 183/183, `npm run lint`
чисто, `npm run format:check` чисто. Проверка: файлов с ≥2 экспортами вне
`init.ts`/`routes.ts` не осталось.

Ссылки: `frontend-rules.md` (правило), `docs/review/context-7.md` (предыдущая волна).
