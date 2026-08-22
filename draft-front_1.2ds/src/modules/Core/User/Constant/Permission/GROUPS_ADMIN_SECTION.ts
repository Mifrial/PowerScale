import type { AdminSection } from '@/modules/Core/User/Interface/AdminSection';

export const GROUPS_ADMIN_SECTION: AdminSection = {
  id: 'groups',
  title: 'Группы',
  to: '/admin/groups',
  icon: 'mdi-account-group',
  permission: 'user_group.view',
};
