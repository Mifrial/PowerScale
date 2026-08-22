import type { PermissionCategory } from '@/modules/Core/User/Interface/PermissionCategory';

export const NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'notification_template',
  label: 'Шаблоны уведомлений',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
    { key: 'delete', label: 'Удаление' },
  ],
};
