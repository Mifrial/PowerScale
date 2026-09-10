import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const SPACES_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'spaces',
  title: 'Пространства',
  to: '/spaces',
  icon: 'mdi-cube-outline',
  order: 40,
};
