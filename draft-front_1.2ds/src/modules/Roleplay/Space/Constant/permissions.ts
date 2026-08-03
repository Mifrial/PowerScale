import type { PermissionCategory } from '@/modules/Core/User/Interface/IPermissionRegistry';

export const SPACE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'space',
  label: 'Пространства',
  actions: [
    { key: 'create', label: 'Создание' },
    { key: 'view_all', label: 'Просмотр всех' },
    { key: 'edit_all', label: 'Редактирование всех' },
  ],
};
