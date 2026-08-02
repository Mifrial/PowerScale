import type { PermissionCategory, AdminSection } from '@/modules/Core/User/Interface/IPermissionRegistry'

export const NOTIFICATION_TEMPLATE_PERMISSION_CATEGORY: PermissionCategory = {
  key: 'notification_template',
  label: 'Шаблоны уведомлений',
  actions: [
    { key: 'view', label: 'Просмотр' },
    { key: 'create', label: 'Создание' },
    { key: 'edit', label: 'Редактирование' },
    { key: 'delete', label: 'Удаление' },
  ],
}

export const TEMPLATES_ADMIN_SECTION: AdminSection = {
  id: 'templates',
  title: 'Шаблоны уведомлений',
  to: '/admin/notification-templates',
  icon: 'mdi-bell-cog',
  permission: 'notification_template.view',
}
