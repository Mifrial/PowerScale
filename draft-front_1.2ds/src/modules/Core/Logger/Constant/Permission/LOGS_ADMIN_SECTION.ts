import type { AdminSection } from '@/modules/Core/User/Interface/AdminSection';

export const LOGS_ADMIN_SECTION: AdminSection = {
  id: 'logs',
  title: 'Журнал',
  to: '/admin/logs',
  icon: 'mdi-text-box-search-outline',
  permission: 'logger.view',
};
