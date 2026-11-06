import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const MAP_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'map',
  label: 'Карты',
  actions: [
    { key: 'edit', label: 'Редактирование' },
    { key: 'view_all', label: 'Просмотр всех' },
    { key: 'edit_all', label: 'Редактирование всех' },
  ],
};
