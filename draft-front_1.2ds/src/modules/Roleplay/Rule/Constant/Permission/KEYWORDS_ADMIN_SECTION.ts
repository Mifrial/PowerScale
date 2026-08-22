import type { AdminSection } from '@/modules/Core/User/Interface/AdminSection';

export const KEYWORDS_ADMIN_SECTION: AdminSection = {
  id: 'keywords',
  title: 'Признаки',
  to: '/admin/keywords',
  icon: 'mdi-tag-multiple',
  permission: 'keyword.view',
};
