import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const TEMPLATES_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'notification-templates',
  title: 'Шаблоны уведомлений',
  to: '/admin/notification-templates',
  icon: 'mdi-bell-cog',
  order: 50,
  sectionId: ADMINISTRATION_MENU_SECTION_ID,
};
