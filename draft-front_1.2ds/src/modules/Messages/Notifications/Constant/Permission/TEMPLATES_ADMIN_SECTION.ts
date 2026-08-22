import type { AdminSection } from '@/modules/Core/User/Interface/AdminSection';

export const TEMPLATES_ADMIN_SECTION: AdminSection = {
  id: 'templates',
  title: 'Шаблоны уведомлений',
  to: '/admin/notification-templates',
  icon: 'mdi-bell-cog',
  permission: 'notification_template.view',
};
