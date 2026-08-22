import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const USER_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'user',
  label: 'Пользователи',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'view_sensitive', label: 'Просмотр скрытых полей' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
    { key: 'deactivate', label: 'Деактивация' },
  ],
};
