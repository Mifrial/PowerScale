import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const CHARACTERS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'characters',
  title: 'Персонажи',
  to: '/characters',
  icon: 'mdi-account-group',
  order: 50,
};
