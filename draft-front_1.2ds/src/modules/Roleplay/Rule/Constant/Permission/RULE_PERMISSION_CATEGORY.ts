import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

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
