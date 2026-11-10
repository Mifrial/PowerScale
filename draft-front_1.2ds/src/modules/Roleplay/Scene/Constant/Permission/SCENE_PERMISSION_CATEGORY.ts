import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const SCENE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'scene',
  label: 'Локации',
  actions: [
    { key: 'edit', label: 'Редактирование' },
    { key: 'view_all', label: 'Просмотр всех' },
    { key: 'edit_all', label: 'Редактирование всех' },
  ],
};
