import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const KEYWORDS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'keywords',
  title: 'Признаки',
  to: '/admin/keywords',
  icon: 'mdi-tag-multiple',
  order: 20,
  sectionId: ADMINISTRATION_MENU_SECTION_ID,
};
