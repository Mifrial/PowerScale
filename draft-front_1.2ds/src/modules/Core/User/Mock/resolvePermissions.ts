import { groupPermissions } from '@/modules/Core/User/Mock/groupPermissions';

/** Мёрж прав всех групп пользователя (дедупликация, порядок не важен). */
export function resolvePermissions(groupNames: string[]): string[] {
  const set = new Set<string>();
  for (const name of groupNames) {
    for (const key of groupPermissions(name)) set.add(key);
  }

  return [...set];
}
