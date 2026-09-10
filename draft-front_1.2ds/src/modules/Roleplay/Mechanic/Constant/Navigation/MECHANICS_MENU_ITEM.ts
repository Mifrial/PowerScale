import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const MECHANICS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'mechanics',
  title: 'Механики',
  to: '/admin/mechanics',
  icon: 'mdi-cog-play',
  order: 30,
  sectionId: ADMINISTRATION_MENU_SECTION_ID,
};
