import { describe, it, expect, beforeEach } from 'vitest';
import type { RouteLocationNormalized } from 'vue-router';
import { evaluateRouteAccess } from '@/router/access';
import type { RouteAccessContext } from '@/router/RouteAccessContext';
import type { User } from '@/modules/Core/User/Dto/User';
import { resetPermissionRegistries, registerAdminSection } from '@/modules/Core/User/init';

beforeEach(() => {
  resetPermissionRegistries();
  registerAdminSection({
    id: 'groups',
    title: 'Группы',
    to: '/admin/groups',
    icon: 'mdi-account-group',
    permission: 'user_group.view',
  });
  registerAdminSection({
    id: 'keywords',
    title: 'Признаки',
    to: '/admin/keywords',
    icon: 'mdi-tag-multiple',
    permission: 'keyword.view',
  });
});

function makeTo(
  partial: Partial<{ meta: Record<string, unknown>; name: unknown; params: Record<string, string> }>,
): RouteLocationNormalized {
  return {
    meta: partial.meta ?? {},
    name: (partial.name ?? undefined) as never,
    params: partial.params ?? {},
  } as unknown as RouteLocationNormalized;
}

const user = (id: number, permissions: string[], superAdmin = false): User => ({
  id,
  name: `U${id}`,
  login: `u${id}`,
  email: `u${id}@t`,
  groups: [],
  registered: '',
  active: true,
  permissions,
  super_admin: superAdmin,
});

const ctx = (u: User | null, guest = false): RouteAccessContext => ({
  isAuthenticated: u !== null,
  isGuest: guest,
  user: u,
});

describe('evaluateRouteAccess', () => {
  describe('auth-layout', () => {
    const login = makeTo({ meta: { layout: 'auth' } });

    it('пускает неавторизованного', () => {
      expect(evaluateRouteAccess(login, ctx(null)).allow).toBe(true);
    });

    it('пускает гостя (гость может открыть логин)', () => {
      expect(evaluateRouteAccess(login, ctx(user(1, []), true)).allow).toBe(true);
    });

    it('авторизованного (не гостя) редиректит на Home', () => {
      const d = evaluateRouteAccess(login, ctx(user(1, [])));
      expect(d).toEqual({ allow: false, redirect: { name: 'Home' } });
    });
  });

  describe('защищённые страницы', () => {
    it('неавторизованного редиректит на Login', () => {
      const d = evaluateRouteAccess(makeTo({ name: 'Users' }), ctx(null));
      expect(d).toEqual({ allow: false, redirect: { name: 'Login' } });
    });

    it('обычная страница без requires пускает любого авторизованного', () => {
      expect(evaluateRouteAccess(makeTo({ name: 'Home' }), ctx(user(1, []))).allow).toBe(true);
    });
  });

  describe('гостевой доступ', () => {
    it('guestAllowed пускает гостя', () => {
      const to = makeTo({ name: 'Spaces', meta: { guestAllowed: true } });
      expect(evaluateRouteAccess(to, ctx(user(0, [], false), true)).allow).toBe(true);
    });

    it('не-guestAllowed страница редиректит гостя на NotFound (даже с requires, которые у гостя есть)', () => {
      const to = makeTo({ name: 'Users', meta: { requiresAny: ['user.view'] } });
      const d = evaluateRouteAccess(to, ctx(user(0, ['user.view'], false), true));
      expect(d).toEqual({ allow: false, redirect: { name: 'NotFound' } });
    });

    it('гость не проходит в админку', () => {
      const to = makeTo({ name: 'Groups', meta: { requiresAny: ['user_group.view'] } });
      expect(evaluateRouteAccess(to, ctx(user(0, [], false), true)).allow).toBe(false);
    });
  });

  describe('requiresAny (OR)', () => {
    const to = makeTo({ name: 'Groups', meta: { requiresAny: ['user_group.view'] } });

    it('пускает при наличии любого ключа', () => {
      expect(evaluateRouteAccess(to, ctx(user(1, ['keyword.view', 'user_group.view']))).allow).toBe(true);
    });

    it('блокирует при отсутствии ключей', () => {
      const d = evaluateRouteAccess(to, ctx(user(1, ['user.view'])));
      expect(d).toEqual({ allow: false, redirect: { name: 'NotFound' } });
    });
  });

  describe('requiresAll (AND)', () => {
    const to = makeTo({ name: 'Sensitive', meta: { requiresAll: ['user.view', 'user.view_sensitive'] } });

    it('пускает при наличии всех ключей', () => {
      expect(evaluateRouteAccess(to, ctx(user(1, ['user.view', 'user.view_sensitive']))).allow).toBe(true);
    });

    it('блокирует при неполном наборе', () => {
      const d = evaluateRouteAccess(to, ctx(user(1, ['user.view'])));
      expect(d).toEqual({ allow: false, redirect: { name: 'NotFound' } });
    });
  });

  describe('super_admin bypass', () => {
    it('проходит requires без ключей', () => {
      const to = makeTo({ name: 'Admin', meta: { requiresAny: ['user_group.view'] } });
      expect(evaluateRouteAccess(to, ctx(user(1, [], true))).allow).toBe(true);
    });
  });

  describe('админ-раздел (meta.admin)', () => {
    const admin = makeTo({ name: 'Admin', meta: { admin: true } });

    it('пускает по ключу зарегистрированной секции', () => {
      expect(evaluateRouteAccess(admin, ctx(user(1, ['keyword.view']))).allow).toBe(true);
    });

    it('блокирует без ключей секций', () => {
      const d = evaluateRouteAccess(admin, ctx(user(1, ['user.view'])));
      expect(d).toEqual({ allow: false, redirect: { name: 'NotFound' } });
    });

    it('super_admin проходит', () => {
      expect(evaluateRouteAccess(admin, ctx(user(1, [], true))).allow).toBe(true);
    });

    it('гость не проходит в админку', () => {
      expect(evaluateRouteAccess(admin, ctx(user(0, [], false), true)).allow).toBe(false);
    });
  });

  describe('«свой vs чужой»', () => {
    const editMeta = { requiresAny: ['user.edit'] };
    const editOwn = makeTo({ name: 'UserEdit', params: { id: '3' }, meta: editMeta });
    const editOther = makeTo({ name: 'UserEdit', params: { id: '9' }, meta: editMeta });

    it('владелец редактирует свой профиль без user.edit', () => {
      expect(evaluateRouteAccess(editOwn, ctx(user(3, ['user.view']))).allow).toBe(true);
    });

    it('чужой профиль требует user.edit', () => {
      const d = evaluateRouteAccess(editOther, ctx(user(3, ['user.view'])));
      expect(d).toEqual({ allow: false, redirect: { name: 'NotFound' } });
    });
  });
});
