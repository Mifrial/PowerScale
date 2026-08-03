# Контекст ревью 5 — внедрение ESLint + Prettier (тулинг фронта)

Живой файл волны (2026-08-03). Внедрение механического гаранта «строго и всегда»
правил: ESLint + Prettier в `draft-front_1.2ds`. `frontend-rules.md` остаётся
лаконичным «правилом для AI»; детали механики — в `eslint.config.js`.

## Предпосылки

- Проверено: до волны ESLint и Prettier нигде не были установлены/настроены
  (нет в зависимостях, в `node_modules`, глобально, нет конфигов) — разрыв с
  договорённостью на старте проекта.
- Код 2-пробельный; 98% строк короче 120 символов (printWidth = 120).
- `any`/`as any` в production — 0 (11 в тестах), `@ts-ignore`/`!`/`debugger` — 0.

## Решения (2026-08-03)

1. **Prettier**: 2 пробела, `semi`, `singleQuote`, `trailingComma: all`,
   `printWidth: 120`. Отступ фиксируется механически.
2. **ESLint** (Vue 3 + TS, flat config): база `@eslint/js` + `typescript-eslint`
   recommended + `eslint-plugin-vue` `flat/essential` + `eslint-config-prettier`.
   Кастомные правила — маппинг «строго и всегда»:
   - `@typescript-eslint/no-explicit-any` (error; override тесты → off);
   - `@typescript-eslint/consistent-type-imports` (error, `import type`);
   - `@typescript-eslint/prefer-readonly` (error);
   - `@typescript-eslint/no-unused-vars` (error);
   - `@typescript-eslint/no-non-null-assertion` (error);
   - `@typescript-eslint/ban-ts-comment` (error);
   - `@typescript-eslint/array-type` (`{ default: 'array' }`);
   - `eqeqeq` (`{ null: 'ignore' }` — nullish-проверки не трогаем);
   - `padding-line-between-statements` (пустая строка перед `return`, автофикс);
   - `no-debugger` (error); `no-console` (`warn`, `allow: ['error','warn']`);
   - `vue/component-tags-order` (script → template → style);
   - `vue/require-explicit-emits` (error);
   - `vue/no-mutating-props` — из `flat/essential`.
   - `no-unnecessary-type-assertion` — НЕ входит: type-aware, требует
     typed-linting (пропущено осознанно).
3. **Фаза 2 (отдельно, потом)**: `eslint-plugin-import`
   (`no-relative-parent-imports`, `import/order`). Typed-linting — не планируем.
4. **`frontend-rules.md`**: §6 Верификация += `npm run lint` + `npm run format:check`;
   строка-указатель в §3 (механику гарантирует ESLint, детали в `eslint.config.js`).

## Объём волны

1. Доки: context-5.md; TR.md; знаниевый граф (work_item).
2. Установка devDeps в `draft-front_1.2ds`.
3. Конфиги: `.prettierrc.json`, `.prettierignore`, `eslint.config.js`, скрипты.
4. `npm run format` → полный реформат.
5. `npm run lint` (--fix) → ручная зачистка остатков.
6. Верификация: `vue-tsc` + `vitest` + `format:check` + `lint:check`.

## Правила верификации

- После каждой фазы: `vue-tsc --noEmit` + `vitest run`.
- `vite build` — веха релиза, делает пользователь; dev-сервер на 3000 не трогать.

## Остатки на следующие круги

- Фаза 2: `eslint-plugin-import` (`no-relative-parent-imports`, `import/order`).
- «Типы в Service» Rule/Game/Chat (9 файлов); типы данных в `Interface/*Api`;
  `FilterBuffer`/`ActiveChip`/`MaybeFilterValue` (Core/UI); декомпозиция
  AbilityEditor/AbilityCard.

## Закрытие волны (2026-08-03)

**Состояние на закрытие:** `vue-tsc` чисто, `vitest` 177/177, `eslint .` без
ошибок, `prettier --check` чисто.

Выполнено:
- devDeps: `prettier`, `eslint` 10, `typescript-eslint` 8, `eslint-plugin-vue` 10,
  `eslint-config-prettier`, `vue-eslint-parser`, `@eslint/js`.
- Конфиги: `.prettierrc.json` (2 пробела, `semi: true`, singleQuote,
  trailingComma all, printWidth 120), `.prettierignore`, `eslint.config.js`
  (flat: js recommended + typescript-eslint recommended + vue flat/essential +
  prettier; кастомные правила по frontend-rules.md; typed-linting — только
  `src/**/*.ts` для `prefer-readonly`).
- Скрипты: `format`, `format:check`, `lint`, `lint:check`.
- Полный `prettier --write` + `eslint --fix` по проекту; ручная зачистка:
  15 `!`-assertions (guards/`??`/generic `querySelector<HTMLElement>`), мёртвые
  импорты (`ref`/`computed`/`SyncResponse`/`DiceRollSpec`/`createMock*Api`),
  `no-useless-escape`, пустые catch (why-комментарии), дубликат ключа `spec` в
  ItemEditor (локальный ref `spec` → `draft`), пустые интерфейсы → type-алиасы,
  переименования `Shell`→`AppShell`, `Messenger`→`ChatMessenger`
  (включено `vue/multi-word-component-names`).
- Правила: frontend-rules.md §3 — строка-указатель «механика закреплена
  линтером»; §6 — верификация включает `lint`/`format:check`.
- TR.md актуализирован (волна тулинга).

**Открытия волны:**
- `vue/component-tags-order` удалён в eslint-plugin-vue v10 → заменён
  `vue/block-order`.
- `@typescript-eslint/no-unnecessary-type-assertion` (type-aware) автофиксом
  снял НЕобходимые `as HTMLElement` в useScrollEars (сломал сборку) → правило
  убрано из конфига; код переведён на generic `querySelector<HTMLElement>`.
- `no-undef` выключен (стандарт для TS — TS сам проверяет неопределённые);
  `_`-префикс игнорируется в `no-unused-vars` (стандартная конвенция);
  в тестах ослаблены `no-explicit-any` и `no-non-null-assertion`
  (прагматичные идиомы после runtime-проверок), продакшен строгий.

**Остатки на следующие круги:**
- Фаза 2: `eslint-plugin-import` (`no-relative-parent-imports`, `import/order`).
- «Типы в Service» Rule/Game/Chat (9 файлов); типы данных в `Interface/*Api`;
  `FilterBuffer`/`ActiveChip`/`MaybeFilterValue` (Core/UI); декомпозиция
  AbilityEditor/AbilityCard.
