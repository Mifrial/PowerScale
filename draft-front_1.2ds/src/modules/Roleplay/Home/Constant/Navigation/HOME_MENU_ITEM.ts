import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const HOME_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'home',
  title: 'Главная',
  to: '/',
  icon: 'mdi-home',
  exact: true,
  order: 10,
};
