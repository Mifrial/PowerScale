import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const GROUPS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'groups',
  title: 'Группы',
  to: '/admin/groups',
  icon: 'mdi-account-group',
  order: 10,
  sectionId: ADMINISTRATION_MENU_SECTION_ID,
};
