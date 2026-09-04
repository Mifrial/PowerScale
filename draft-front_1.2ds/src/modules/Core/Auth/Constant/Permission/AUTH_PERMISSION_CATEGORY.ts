import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const AUTH_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'auth',
  label: 'Аутентификация',
  actions: [{ key: 'user.edit', label: 'Смена чужого пароля' }],
};
