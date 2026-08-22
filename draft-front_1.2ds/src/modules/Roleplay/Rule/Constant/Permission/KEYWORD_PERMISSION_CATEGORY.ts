import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

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
