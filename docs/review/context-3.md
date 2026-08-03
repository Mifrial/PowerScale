# Контекст ревью 3 — волна соответствия кода фронта правилам

Живой файл волны (2026-08-03). **ЗАКРЫТ 2026-08-03** — волна выполнена, верификация
зелёная; дальнейшая работа — новый круг детального модульного ревью (см. «Закрытие»).

## Статус (2026-08-03)

- [x] Техдолг «юнионы → Enum/» закрыт как ошибка ревью (Enum/ = string-literal union; юнионы-контракты в Dto/). `RuleSpec` перенесён Enum→Dto, `AbilitySpec` → `AbilityType`.
- [x] Механика: импорты → `@/` (~120 файлов), порядок тегов SFC script→template→style (~95 .vue).
- [x] Декомпозиция 8 больших .vue: SmartGrid 552→203, FilterBar 378→170, MacrosSection 364→106, UserProfilePage 340→214, RaceEditor 450→180, ProcessEditor 437→125, ItemEditor 415→251, RuleEditPage 383→355.
- [x] Мутации редакторов → SpecServices: расширены RaceSpecService/ItemSpecService/AbilitySpecService; созданы ProcessSpecService, RuleReferenceService, RuleDraftService, Utils/Rule/formMapper; дефолты Grant/Requirement/SpellComponent/SpellDuration.
- [x] Константы/дедуп: манифесты страниц → Constant/ модулей; modeOptions, useVModelSync (12 редакторов), initials/displayName → Core/User/Utils/profile, filteredUsers, константы конфигурации.
- [x] Мелочи: PickerItem→Dto, FormRef→LoginForm, mockGroupMembers→Mock/, Config/→Constant/, Vuetify (DiceRollForm/MessengerTabs), rollSummary (ChatInput), split IPermissionRegistry.
- [x] Верификация финальная: vue-tsc чисто + vitest 177/177. TR.md актуализирован (структура + волна 2).

Остатки (осознанно, вне волны): AbilityEditor 495 / SpaceDetailPage 360 / AbilityCard 294 / Messenger 281 / GrantEditor 277 / SpellEditor 275 / RequirementNodeEditor 263 / FormulaInput 257 — «одна задача на файл» выполненной декомпозиции, длина сама по себе не критерий (frontend-rules §1); ItemEditor 251 — шаблон панели «Предмет» оставлен в родителе.

## Решение по техдолгу «юнионы → Enum/» (2026-08-03)

Техдолг из context-2 (п.6 «юнионы AbilitySpec/Grant/Requirement/Formula/
ProcessTransition/SpellComponent/ActionCost/ZoneId пока в Dto/Ability/ — по нашей
терминологии (юнионы → Enum/) их стоит перенести») признан ошибкой ревью и закрыт.
Терминология подтверждена по frontend-rules.md §3:

- **Enum/ — только string-literal union** (AbilityType, RuleType, ChatType и т.п.);
- **Dto/ — контракты данных, включая дискриминированные юнионы с payload**.

Requirement, Grant, Formula, ProcessTransition, SpellComponent, AbilitySpec,
ActionCost, ZoneId остаются в `Dto/Ability/` (верно по правилам).
`frontend-rules.md` §2/§3 НЕ меняются.

Реальная непоследовательность (исправляется в волне):
- `Enum/RuleSpec.ts` — юнион, лежит в `Enum/` вопреки правилу «в Enum только
  string-literal union» → перенос в `Dto/RuleSpec.ts`, обновить 12 импортеров
  (редакторы: AbilityEditor, RaceEditor, ResourceEditor, SpeciesEditor, ItemEditor,
  CharacteristicEditor; Dto: Rule, RuleVersion, CreateRuleData, UpdateRuleData;
  Page/RuleEditPage, Space/Mock/mockSpaces).
- `Dto/Ability/AbilitySpec.ts` — inline-литералы типа дублируют `AbilityType`
  (Enum/Ability/) → импорт `AbilityType`.
- Ре-экспорты типов из `AbilityEditor.vue:297-300` (AbilitySpec/ZoneId/Grant) —
  внешних потребителей нет (grep `from '.*Editors/AbilityEditor'` пуст), удалить.

## Объём волны (согласовано, всё в одном заходе)

1. Доки: создать context-3.md; TR.md — историческое решение RuleSpec → Dto/.
2. Механика: относительные импорты → `@/` (~113 файлов, включая main.ts/router/
   shell); порядок тегов SFC script→template→style (~104 .vue).
3. RuleSpec → Dto/ + AbilitySpec → AbilityType + чистка ре-экспортов.
4. Декомпозиция 8 больших .vue:
   - Core/UI: SmartGrid (Grid/header/GridHeader, GridRow, GridFooter, ScrollEars +
     composables useColumnResize/useColumnDrag/useScrollEars), FilterBar
     (FilterPopup/FilterChips + FilterBar/filterValues.ts + useFilterBuffer).
   - Rule: RaceEditor (RaceCharacteristicsEditor/RaceAbilitiesEditor/
     InheritancePreview + RaceSpecService + RuleReferenceService), ProcessEditor
     (ProcessStepEditor/ProcessTransitionEditor/ProcessStartFailureEditor/
     ResourceCostRow + ProcessSpecService), ItemEditor (Item/WeaponEditor,
     Item/ArmorEditor, Item/ShieldEditor, ItemEquipmentEditor + ItemSpecService +
     Constant/Item/*), RuleEditPage (RuleConflictDialog + Constant/RULE_TYPES +
     RuleDraftService + Utils/Rule/formMapper).
   - Game: MacrosSection (Macros/MacroForm + MacroRollEditor +
     RollService.validateRollSpec/formatRollSpecText + Dto/RollForm).
   - Core/User: UserProfilePage (ProfileInfoCard/ProfileAuthCard/ProfileGroupsCard/
     DeactivateUserDialog).
5. Мутации редакторов → Service/Spec/*: AbilityEditor (requirements/grants/
   action_costs апдейтеры), GrantEditor/RequirementNodeEditor/SpellEditor
   (дефолты+апдейтеры), RuleReferenceService (дедуп lookup'ов Ability/Race/Item).
6. Константы/дедупликация: манифесты страниц → Constant/ модулей; modeOptions
   (NumberFilter/DateTimeFilter/StringFilter), v-model-sync (~15 редакторов →
   useVModelSync), initials/displayName (4 места), фильтры users.ts, константы
   конфигурации (PAGE_SIZE/MAX_STORED/STORAGE_KEY/STORAGE_PREFIX/SLIDER_LIMIT).
7. Мелочи: PickerItem→Core/UI/Dto, FormRef→Dto, mockMembers→Mock/, Config/→
   Constant/, нативные input/button→Vuetify (DiceRollForm/MessengerTabs),
   ChatInput:10 выражение→метод, split IPermissionRegistry.

## Рескан-находки волны (повторный рескан 2026-08-03)

- Системные: порядок тегов во всех ~104 .vue (template первым); относительные
  импорты в ~113 файлах (в т.ч. Rule/Constant/*, Rule/Dto/*, Rule/Component/*,
  Core/User/*, Messages/*, root main.ts/router/shell).
- Архитектура: deep-мутации spec в ItemEditor (прямые присваивания
  spec.value.weapon.*, вотчер subtypes), AbilityEditor/RaceEditor/ProcessEditor/
  GrantEditor/RequirementNodeEditor/SpellEditor (updater-функции инлайн).
- Дубли: typeLabels (RuleDetailPage) копия Constant/RULE_TYPE_LABELS; modeOptions
  в 3 фильтрах; v-model-sync паттерн (inner+2 watchers) в ~15 редакторах;
  initials/displayName в 4 местах (UserProfileSlider, UserProfilePage, users.ts,
  useChatUsers); фильтры users.ts (5 одинаковых блоков).
- Мелочи: интерфейсы-формы в компонентах (PickerItem, RollForm, FormRef);
  mockMembers в GroupDetailPage; Chat/Config/ вместо Constant/; нативные
  input/button в DiceRollForm/MessengerTabs; сложное инлайн-выражение ChatInput:10;
  IPermissionRegistry (3 типа в одном файле).
- Соответствует правилам: any/as any в production (0), TS-enum нет, деструктуризация
  props нет, деструктуризация store без storeToRefs нет, class-инстансы в state нет,
  изобретённых сокращений нет, файлы вне init.ts/routes.ts в корнях модулей нет.

## Правила верификации

- После каждой фазы: `vue-tsc --noEmit` + `vitest run`.
- Dev-сервер на 3000 не трогать; `vite build` не делаем (веха релиза, пользователь).

## Закрытие волны (2026-08-03)

**Состояние на закрытие:** `vue-tsc --noEmit` чисто, `vitest` 177/177.
Гит: ~197 изменённых + 58 новых файлов. TR.md актуализирован (структура фронта +
блок «Волна 2 фронта»); знаниевый граф пополнен (work_item + relations).

**Хэнд-офф на новый круг (детальное модульное ревью).** Цель волны достигнута, но
модули ещё не вычитаны до конца. Кандидаты на разбор по модулям:

- **Rule**:
  - AbilityEditor 495 — шаблон всё ещё большой (секции общее/требования/дары/
    процесс/заклинание/зоны/стоимости); мутации уже в AbilitySpecService, но
    разметку можно резать дальше (панели требований/даров по уровням — кандидаты
    на дочерние компоненты).
  - AbilityCard 294 — большая форматирующая доменная логика
    (reqText/grantLabel/formulaLabel/transitionSummary/durationLabel, ~155-293) —
    кандидат на Utils/Service или дочерние рендеры.
  - GrantEditor 277 / SpellEditor 275 / RequirementNodeEditor 263 / FormulaInput 257
    — «одна задача» выполнена, но проверить вынос чистой логики (многие уже через
    AbilitySpecService/useVModelSync).
  - Проверить: `Constant/Ability/ABILITY_*` и манифесты — нет ли дублей производных
    справочников; ссылочные lookup'ы везде через RuleReferenceService.
- **Space**: SpaceDetailPage 360 (табы/карточки) — после PublishDialog ещё кандидаты;
  проверить формат-функции (formatPublished).
- **Messages/Chat**: Messenger 281 / ChatList / ChatBar — компоненты оболочки чата,
  кандидаты на декомпозицию (строки сообщений, вход, заголовок); ChatInput/rollSummary
  сделан, но список сообщений можно вынести.
- **Messages/Notifications**: TemplateEditPage 206 — форма шаблона, проверить.
- **Core/UI**: Grid/FilterBar декомпозированы; проверить SmartGrid (203) и
  FieldPickerDialog на предмет оставшейся логики в шаблонах.
- **Общее**:
  - Комментарии в коде — правило «без комментариев, если не запрошены»; остались
    пояснительные doc-комментарии (RuleEditPage, RuleDetailPage, RuleEditorBase,
    SimpleRuleEditor, SpaceDetailPage, draftRules.ts, notifications.ts и др.) —
    решать по каждому.
  - Возможные остаточные inline-константы/манифесты, не попавшие в фазу 5 —
    перепроверить при вычитке модуля.
  - Плагинные секции/реестры (Chat, Profile, Admin) — проверить актуальность
    регистраций после рефакторинга.
  - `import type` в середине файлов — искать и поднимать в шапку.
