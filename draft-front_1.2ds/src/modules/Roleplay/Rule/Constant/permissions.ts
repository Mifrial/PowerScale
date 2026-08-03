import type { PermissionCategory, AdminSection } from '@/modules/Core/User/Interface/IPermissionRegistry';

export const RULE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'rule',
  label: 'Правила',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
    { key: 'delete', label: 'Удаление' },
  ],
};

export const KEYWORD_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'keyword',
  label: 'Признаки',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
    { key: 'delete', label: 'Удаление' },
  ],
};

export const KEYWORDS_ADMIN_SECTION: AdminSection = {
  id: 'keywords',
  title: 'Признаки',
  to: '/admin/keywords',
  icon: 'mdi-tag-multiple',
  permission: 'keyword.view',
};
