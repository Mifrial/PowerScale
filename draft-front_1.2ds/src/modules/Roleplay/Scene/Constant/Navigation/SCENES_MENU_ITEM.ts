import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const SCENES_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'scenes',
  title: 'Локации',
  to: '/scenes',
  icon: 'mdi-map-legend',
  order: 56,
};
