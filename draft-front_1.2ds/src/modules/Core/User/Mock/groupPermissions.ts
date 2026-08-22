import { getPermissionKeys } from '@/modules/Core/User/init';
import { GROUP_PERMISSIONS } from '@/modules/Core/User/Mock/GROUP_PERMISSIONS';

/** Права группы; «Администраторы» = полный реестр ключей (лениво). */
export function groupPermissions(groupName: string): string[] {
  if (groupName === 'Администраторы') return getPermissionKeys();

  return [...(GROUP_PERMISSIONS[groupName] ?? [])];
}
