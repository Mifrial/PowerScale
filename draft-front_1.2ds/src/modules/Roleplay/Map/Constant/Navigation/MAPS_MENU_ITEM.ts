import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const MAPS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'maps',
  title: 'Карты',
  to: '/maps',
  icon: 'mdi-map-outline',
  order: 55,
};
