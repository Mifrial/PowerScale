import type { AdminSection } from '@/modules/Core/User/Interface/AdminSection';

export const MECHANICS_ADMIN_SECTION: AdminSection = {
  id: 'mechanics',
  title: 'Механики',
  to: '/admin/mechanics',
  icon: 'mdi-cog-play',
  permission: 'mechanic.view',
};
