import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const LOGGER_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'logger',
  label: 'Журнал',
  actions: [{ key: 'view', label: 'Просмотр' }],
};
