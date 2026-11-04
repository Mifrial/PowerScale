import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const FILE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'file',
  label: 'Файлы',
  actions: [{ key: 'view', label: 'Просмотр' }],
};
