import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const FILES_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'files',
  title: 'Файлы',
  to: '/admin/files',
  icon: 'mdi-file-multiple-outline',
  order: 50,
  sectionId: ADMINISTRATION_MENU_SECTION_ID,
};
