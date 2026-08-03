import type { RouteLocationNormalized, RouteLocationRaw } from 'vue-router';
import type { User } from '@/modules/Core/User/Dto/User';
import { accessService, isAdmin } from '@/modules/Core/User/init';

export interface RouteAccessContext {
  isAuthenticated: boolean;
  isGuest: boolean;
  user: User | null;
}

export type RouteAccessDecision = { allow: true } | { allow: false; redirect: RouteLocationRaw };

/**
 * Чистая функция решения «пустить / куда редиректить» — без работы с роутером,
 * чтобы логика была тестируемой. Guard в router/index.ts только подключает её.
 *
 * Порядок проверок:
 * 1. auth-layout: авторизованный (не гость) уходит на Dashboard.
 * 2. Не авторизован → Login.
 * 3. Гость: только `meta.guestAllowed` (публичные страницы, без проверки ключей).
 * 4. `requiresAll` (AND), затем `requiresAny` (OR).
 * 5. «Свой vs чужой»: страница своего профиля/редактирования доступна владельцу всегда (ТР §4).
 *
 * Отказ (гость вне guestAllowed, провал requiresAll/requiresAny) ведёт на единую
 * страницу 404/403 (`NotFound`) — без раскрытия причин (ТЗ, этап VIII #40).
 */
export function evaluateRouteAccess(to: RouteLocationNormalized, ctx: RouteAccessContext): RouteAccessDecision {
  if (to.meta.layout === 'auth') {
    if (ctx.isAuthenticated && !ctx.isGuest) return { allow: false, redirect: { name: 'Home' } };

    return { allow: true };
  }

  if (!ctx.isAuthenticated) return { allow: false, redirect: { name: 'Login' } };

  if (ctx.isGuest) {
    return to.meta.guestAllowed ? { allow: true } : { allow: false, redirect: { name: 'NotFound' } };
  }

  // Раздел «Администрирование»: доступ через реестр админ-секций (isAdmin).
  if (to.meta.admin && !isAdmin(ctx.user)) {
    return { allow: false, redirect: { name: 'NotFound' } };
  }

  // «Свой vs чужой» (ТР §4): владелец всегда может смотреть/редактировать свой профиль.
  if (ctx.user && (to.name === 'UserProfile' || to.name === 'UserEdit')) {
    if (String(to.params.id) === String(ctx.user.id)) return { allow: true };
  }

  const requiresAll = to.meta.requiresAll;
  if (requiresAll?.length && !accessService.hasAllPermissions(ctx.user, requiresAll)) {
    return { allow: false, redirect: { name: 'NotFound' } };
  }

  const requiresAny = to.meta.requiresAny;
  if (requiresAny?.length && !accessService.hasAnyPermission(ctx.user, requiresAny)) {
    return { allow: false, redirect: { name: 'NotFound' } };
  }

  return { allow: true };
}
