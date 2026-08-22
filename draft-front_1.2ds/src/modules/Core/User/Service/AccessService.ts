import type { User } from '@/modules/Core/User/Dto/User';

/**
 * Проверка прав текущего пользователя по плоскому списку ключей.
 * super_admin обходит все проверки (ТР §4 «Super-admin bypass»).
 * Админ-секции (доступ к /admin, пункты меню) — реестр в `Core/User/init.ts`
 * (`registerAdminSection`/`getAdminSections`/`isAdmin`).
 */
export class AccessService {
  hasAnyPermission(user: User | null | undefined, keys: string[]): boolean {
    if (!user) return false;
    if (user.super_admin) return true;
    const perms = user.permissions ?? [];

    return keys.some((k) => perms.includes(k));
  }

  hasAllPermissions(user: User | null | undefined, keys: string[]): boolean {
    if (!user) return false;
    if (user.super_admin) return true;
    const perms = user.permissions ?? [];

    return keys.every((k) => perms.includes(k));
  }
}
