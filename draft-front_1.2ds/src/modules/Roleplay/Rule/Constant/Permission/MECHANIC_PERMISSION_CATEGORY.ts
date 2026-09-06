import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const MECHANIC_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'mechanic',
  label: 'Механики',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
  ],
};
