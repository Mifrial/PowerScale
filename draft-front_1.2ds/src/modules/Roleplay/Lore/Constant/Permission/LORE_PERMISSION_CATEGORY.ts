import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const LORE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'lore',
  label: 'Кодекс',
  actions: [
    { key: 'create', label: 'Создание миров' },
    { key: 'view_all', label: 'Просмотр всех' },
    { key: 'edit_all', label: 'Редактирование всех' },
  ],
};
