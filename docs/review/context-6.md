# Контекст ревью 6 — Core/Auth + Core/User (порядок в модулях ядра)

Живой файл волны (2026-08-03). Core/Engine и Core/UI приведены в порядок
(ревью 1–5). Волна: ревью и рефакторинг `Core/Auth` и `Core/User` по
`frontend-rules.md` (именование, дублирование, типизация, архитектура).

## Решения (2026-08-03)

1. **A1** `Constant/passwordPolicy.ts` → `Constant/defaultPasswordPolicy.ts`.
   Дефолт-политика была продублирована в 3 местах (Constant, `Store/auth.ts`
   init, `Mock/mockAuth.ts getPasswordPolicy()`). Остаётся один источник —
   `DEFAULT_PASSWORD_POLICY`; store и mock импортируют его.
2. **A2** `Dto/LoginForm.ts` (содержал `FormRef`) — имя ≠ содержимое; `FormRef`
   был продублирован 4 раза. Каноничный тип — `VForm` из `vuetify/components`
   (как в `UserForm.vue`). Файл удалён, inline-дубликаты убраны.
3. **A3** `getInitials` в `Mock/mockAuth.ts` — мёртвый (импортёров нет;
   есть `Core/User/Utils/profile.ts initials`). Удалён.
4. **A4** Гость: смена sentinel `userId===0` на **дискриминированный юнион**:
   `Core/Auth/Dto/Session.ts` (`anon | guest | user{userId}`). `Store/auth.ts`
   переписан (guestLogin/login/register/logout/checkAuth + getters
   `isAuthenticated`/`isGuest`/`userId`). Причина: sentinel 0 — класс тихих
   багов `if(userId)` vs `if(userId!==null)`; юнион исключает противоречивые
   состояния и риск протечки 0 в API-вызовы (userId гостя = null). Session —
   чисто клиентский контракт состояния (Dto), бэк его не видит. Потребители
   ходят через getters — поведение не меняется (проверено: router, shell,
   chat usePermissions/ChatList/ChatMessenger).
5. **U1** `GroupDetailPage` читал `mockGroupMembers` напрямую (протечка mock
   в Page; в `IGroupApi` нет эндпоинта участников). Добавлен
   `IGroupApi.getGroupMembers(id)` + `Dto/GroupMember` + mock-эндпоинт +
   `Store/groups.ts fetchGroupMembers`; страница берёт данные из store.
6. **U2** `UserProfilePage.save()` строил `data: Record<string, unknown>` →
   `store.updateUser` (обход `UpdateUserData`), пустые строки → `null`,
   `Object.assign` — глубокая мутация inline. Переведено на типизированный
   `UpdateUserData` из editForm.
7. **P3** (по выбору): rename `loginOrEmail` (mockLogin, LoginPage ref);
   `MockUser = Omit<User,'permissions'> & { password: string }` (убрать
   дублирование полей User); общий abortable `delay` + `fetchGroups` учитывает
   signal; единый тип string-фильтра `{mode,value}` в `Store/users.ts`.

## Остатки на следующие круги

- `IPermissionRegistry.ts` — баррель из 3 типов (8 импортёров); оставлен как
  публичная точка (осознанно).
- `users store setGuest()` — отображаемый объект «Гость» (id:0) остаётся
  display-only для SideBar/Dashboard; не сессия (A4 его не трогает).
- `routes.ts` (User): `useGroupStore()` в meta crumb — оставлено (работает).

## Правила верификации

- После каждой фазы: `vue-tsc --noEmit` + `vitest run`.
- Полная волна: `npm run lint` + `npm run format:check`.
- `vite build` — веха релиза, делает пользователь; dev-сервер на 3000 не трогать.

## Закрытие волны (2026-08-03)

**Состояние на закрытие:** `vue-tsc` чисто, `vitest` 183/183, `eslint .` без
ошибок, `prettier --check` чисто.

Выполнено:
- **A1** `Constant/defaultPasswordPolicy.ts` (rename); `Store/auth.ts`
  инициализирует `passwordPolicy` из `DEFAULT_PASSWORD_POLICY`;
  `Mock/mockAuth.ts getPasswordPolicy()` возвращает константу. Три копии
  литерала сведены к одной.
- **A2** `FormRef` → `VForm` (`vuetify/components`) в Login/Register/
  ForgotPassword/ResetPassword; удалён `Dto/LoginForm.ts`.
- **A3** удалён мёртвый `getInitials` из `Mock/mockAuth.ts`.
- **A4** `Core/Auth/Dto/Session.ts` (anon|guest|user) + `Store/auth.ts`
  переписан: `session` ref + getters `isAuthenticated`/`isGuest`/`userId`
  (computed) + `setUserSession`/`setGuestSession`/`setAnonSession`; экшены
  guestLogin/login/register/logout/checkAuth переведены на них. Потребители
  (router, shell, chat) через getters — поведение без изменений.
- **U1** `IGroupApi.getGroupMembers` + `GroupApi` (action
  `userGroup.getMembers`) + mock `getGroupMembers` + `Store/groups.ts`
  `groupMembers`/`fetchGroupMembers`; `GroupDetailPage` берёт участников из
  store (прямой импорт `mockGroupMembers` убран).
- **U2** `UserProfilePage.save()` — типизированный `UpdateUserData` вместо
  `Record<string,unknown>` + `Object.assign` (мутация убрана, возврат из API).
- **P3** rename `loginOrEmail` (mockLogin, LoginPage); `MockUser =
  Omit<User,'permissions'> & { password: string }`; общий
  `Core/Engine/Mock/abortableDelay.ts` (mockAuth/mockUsers/mockGroups),
  `fetchGroups`/`fetchGroup`/`createGroup`/`updateGroup`/`deactivateGroup`
  учитывают `signal`; `Store/users.ts` — единый тип `StringFilterValue`
  (Core/UI) вместо 7 inline-повторов.
- TR.md: актуализированы ссылки на `defaultPasswordPolicy.ts`, `Session.ts`,
  `VForm`.

**Остатки на следующие круги:**
- `IPermissionRegistry.ts` — баррель (оставлен осознанно как публичная точка).
- `users store setGuest()` — display-объект «Гость» (id:0) остаётся.
- `routes.ts` (User): `useGroupStore()` в meta crumb — оставлено.
- `Store/auth.ts` `fetchPasswordPolicy` возвращает `passwordPolicy.value` при
  ошибке (тихий фолбэк) — оставлено (дефолт валиден).
