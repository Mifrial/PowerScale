# Контекст ревью 7 — комплексное ревью группы Core (Engine, UI, Auth, User)

Финальная волна ревью всего ядра (2026-08-03). Отдельные модули Core
приведены в порядок в волнах 1–6; здесь — кросс-модульная проверка:
анатомия, нейминг, дублирование, зависимости, ServiceLocator/init-паттерны.

## Ревью: что проверялось

- Полная карта файлов Core (4 модуля).
- Анатомия модулей (Dto/Enum/Interface/Service/Constant/Component/Mock/Utils/
  Store/Page/Composables/__tests__) — единообразна.
- Единый паттерн ключей ServiceLocator `Core.<Module>.Service.<Name>`.
- init.ts/routes.ts поверхность и маршрутизация (main.ts, router).
- Переиспользование утилит между модулями (profile.ts, datetime vs DateTime).
- Судьба value-класса `Engine/Value/DimensionalNumber`.

## Findings и решения (2026-08-03)

1. **F1 — ключ ServiceLocator Engine не по паттерну.** `Core.Engine.Http.CsrfApi`
   против `Core.<Module>.Service.<Name>` у Auth/User. Ключ меняется на
   `Core.Engine.Service.CsrfApi` (использовался только в `Engine/init.ts`).
2. **F2 — value-класс `Engine/Value/DimensionalNumber` был «сиротой».** Арифметика
   (`toNumber`, `add`, `subtract`, `modify(range)` с переносом, `toString`)
   вызывалась только из своего теста; UI работал на plain-типе
   `DimensionalNumberValue` (Dto). Дубля арифметики в проде НЕТ (проверено
   поиском `base * 2^size`). **Решение пользователя:** проп `mode` не
   возвращать, value-класс и тип НЕ удалять (понадобится для real-сервера);
   чинить только найденные проблемы.
3. **F2b — дрейф API `DimensionalNumberInput`.** При рефакторинге волн 2–4 из
   `DimensionalNumberInput.vue` был удалён проп `mode?: 'characteristic' |
   'default'` (база [3,5] с переносом), заменён generic `:min/:max`. Три
   потребителя остались передавать фантомный `mode="characteristic"`
   (GrantEditor, WeaponEditor, ShieldEditor) — атрибут ни на что не влиял, база
   `min_strength`/значения потеряла ограничение. **Фикс:** `mode="characteristic"`
   → `:min="3" :max="5"` (идиома миграции как у RaceCharacteristicsEditor/
   RequirementNodeEditor).
4. **F6 — дубль дефолта.** `{ base: 3, size: 0 }` захардкожен дважды в
   `DimensionalNumberInput.vue` (updateBase/updateSize). Вынесен в константу
   `DEFAULT_VALUE`.
5. **F3 (слабое)**: `toString()` value-класса (`3↑2`) и UI-чип
   `DimensionalNumber.vue` (base+arrow+size) — разные представления, не
   переиспользуют друг друга; оставлено.
6. **F4 (leftover)**: `routes.ts` (User) импортирует `useGroupStore()` в meta
   crumb — единственный route-файл Core со store; оставлен осознанно (context-6).
7. **F5 (наблюдение)**: `Engine/init.ts` переэкспортирует HttpClient/Engine/типы
   (нужно real-ветке main.ts), Auth/User — нет; разные поверхности, менять не
   требуется.

## Закрытие волны (2026-08-03)

**Состояние:** `vue-tsc --noEmit` чисто, `vitest run` 183/183, `npm run lint`
чисто, `npm run format:check` чисто.

Выполнено:
- `Engine/init.ts`: ключ `Core.Engine.Http.CsrfApi` → `Core.Engine.Service.CsrfApi`.
- `GrantEditor.vue`, `Item/WeaponEditor.vue`, `Item/ShieldEditor.vue`:
  фантомный `mode="characteristic"` → `:min="3" :max="5"`.
- `DimensionalNumberInput.vue`: `DEFAULT_VALUE` вместо двойного литерала.

Оставлено осознанно:
- `Engine/Value/DimensionalNumber.ts` + тест — каноничная домен-логика
  размерных чисел, к проду не подключена (решение пользователя: не удалять).
- `IPermissionRegistry.ts` баррель, `users store setGuest()` display-объект,
  `routes.ts` useGroupStore, тихий фолбэк `fetchPasswordPolicy` — по context-6.
