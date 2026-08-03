import { getPermissionKeys } from '@/modules/Core/User/init';

/**
 * Единый справочник «группа → permission-ключи» для mock-режима.
 * Канонические имена групп — из ТР §4: Администраторы / Игрок / Ведущий.
 * Источник истины прав групп; используется mockAuth (для currentUser.permissions)
 * и mockGroups (список групп в админке).
 * «Администраторы» — все зарегистрированные модулями ключи (реестр прав Core/User);
 * считается лениво (при доступе), чтобы не зависеть от порядка регистрации.
 */
export const GROUP_PERMISSIONS: Record<string, string[]> = {
  Игрок: ['user.view', 'character.create'],
  Ведущий: ['user.view', 'character.create', 'character.view', 'space.view_all', 'rule.view'],
};

/** Права группы; «Администраторы» = полный реестр ключей (лениво). */
export function groupPermissions(groupName: string): string[] {
  if (groupName === 'Администраторы') return getPermissionKeys();

  return [...(GROUP_PERMISSIONS[groupName] ?? [])];
}

/** Мёрж прав всех групп пользователя (дедупликация, порядок не важен). */
export function resolvePermissions(groupNames: string[]): string[] {
  const set = new Set<string>();
  for (const name of groupNames) {
    for (const key of groupPermissions(name)) set.add(key);
  }

  return [...set];
}
