import { permissionKeysOfGroupId } from '@/modules/Core/User/Mock/mockGroups';

/** Мёрж прав всех групп пользователя по id (дедупликация, порядок не важен). */
export function resolvePermissions(groupIds: number[]): string[] {
  const set = new Set<string>();
  for (const groupId of groupIds) {
    for (const key of permissionKeysOfGroupId(groupId)) set.add(key);
  }

  return [...set];
}
