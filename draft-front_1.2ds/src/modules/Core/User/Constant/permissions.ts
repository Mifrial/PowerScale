import type { PermissionCategory, AdminSection } from '@/modules/Core/User/Interface/IPermissionRegistry';

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

export const GROUPS_ADMIN_SECTION: AdminSection = {
  id: 'groups',
  title: 'Группы',
  to: '/admin/groups',
  icon: 'mdi-account-group',
  permission: 'user_group.view',
};
