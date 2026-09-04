import { describe, it, expect, beforeEach } from 'vitest';
import {
  resetPermissionRegistries,
  registerPermissionCategory,
  registerAdminSection,
  getPermissionCategories,
  getPermissionKeys,
  getAdminSections,
  getAdminSectionPermissions,
  isAdmin,
} from '@/modules/Core/User/init';
import type { User } from '@/modules/Core/User/Dto/User';

const user = (permissions: string[], hasBypass = false): User => ({
  id: 1,
  name: 'U',
  login: 'u',
  email: 'u@t',
  groups: [],
  registered: 0,
  active: true,
  permissions,
  bypass: hasBypass,
});

beforeEach(() => {
  resetPermissionRegistries();
});

describe('реестр категорий прав', () => {
  it('getPermissionKeys собирает `category.action`', () => {
    registerPermissionCategory({
      key: 'user',
      label: 'Пользователи',
      actions: [
        { key: 'view', label: 'Просмотр' },
        { key: 'edit', label: 'Редактирование' },
      ],
    });
    expect(getPermissionKeys()).toEqual(['user.view', 'user.edit']);
  });

  it('повторная регистрация категории не дублирует', () => {
    registerPermissionCategory({ key: 'rule', label: 'Правила', actions: [{ key: 'view', label: 'Просмотр' }] });
    registerPermissionCategory({ key: 'rule', label: 'Правила', actions: [{ key: 'view', label: 'Просмотр' }] });
    expect(getPermissionCategories()).toHaveLength(1);
  });

  it('нет зарегистрированных категорий → пустой список ключей', () => {
    expect(getPermissionKeys()).toEqual([]);
  });
});

describe('реестр админ-секций', () => {
  it('getAdminSectionPermissions собирает permission секций', () => {
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
    expect(getAdminSections()).toHaveLength(2);
    expect(getAdminSectionPermissions()).toEqual(['user_group.view', 'keyword.view']);
  });

  it('isAdmin без зарегистрированных секций → false', () => {
    expect(isAdmin(user(['user_group.view']))).toBe(false);
  });

  it('isAdmin по ключу секции → true', () => {
    registerAdminSection({
      id: 'groups',
      title: 'Группы',
      to: '/admin/groups',
      icon: 'mdi-account-group',
      permission: 'user_group.view',
    });
    expect(isAdmin(user(['user_group.view']))).toBe(true);
  });

  it('isAdmin: обычный игрок без админ-ключей → false', () => {
    registerAdminSection({
      id: 'groups',
      title: 'Группы',
      to: '/admin/groups',
      icon: 'mdi-account-group',
      permission: 'user_group.view',
    });
    expect(isAdmin(user(['user.view', 'character.create']))).toBe(false);
  });

  it('isAdmin: bypass → true', () => {
    expect(isAdmin(user([], true))).toBe(true);
  });

  it('isAdmin: null → false', () => {
    expect(isAdmin(null)).toBe(false);
  });
});
