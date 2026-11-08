import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const NOTES_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'notes',
  title: 'Заметки',
  to: '/notes',
  icon: 'mdi-note-multiple-outline',
  order: 25,
};
