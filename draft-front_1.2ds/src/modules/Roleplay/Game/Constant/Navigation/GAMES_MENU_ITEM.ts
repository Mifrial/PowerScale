import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const GAMES_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'games',
  title: 'Игры',
  to: '/games',
  icon: 'mdi-gamepad-variant',
  order: 60,
};
