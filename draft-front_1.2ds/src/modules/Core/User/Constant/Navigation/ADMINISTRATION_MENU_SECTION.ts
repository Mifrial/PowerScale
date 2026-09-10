import type { MenuSection } from '@/modules/Core/UI/Dto/Navigation/MenuSection';
import { ADMINISTRATION_MENU_SECTION_ID } from '@/modules/Core/User/Constant/Navigation/ADMINISTRATION_MENU_SECTION_ID';

export const ADMINISTRATION_MENU_SECTION: MenuSection = {
  kind: 'menuSection',
  id: ADMINISTRATION_MENU_SECTION_ID,
  title: 'Администрирование',
  icon: 'mdi-shield-crown',
  order: 100,
};
