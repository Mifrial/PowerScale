import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const USERS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'users',
  title: 'Пользователи',
  to: '/users',
  icon: 'mdi-account-multiple',
  order: 70,
};
