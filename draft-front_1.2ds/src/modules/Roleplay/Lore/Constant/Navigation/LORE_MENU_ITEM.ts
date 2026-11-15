import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const LORE_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'lore',
  title: 'Кодекс',
  to: '/lore',
  icon: 'mdi-book-open-page-variant',
  order: 45,
};
