import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const USER_GROUP_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'user_group',
  label: 'Группы',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
    { key: 'deactivate', label: 'Деактивация' },
  ],
};
