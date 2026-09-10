import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const LOGS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'logs',
  title: 'Журнал',
  to: '/admin/logs',
  icon: 'mdi-text-box-search-outline',
  order: 40,
  sectionId: ADMINISTRATION_MENU_SECTION_ID,
};
