import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const MESSENGER_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'messenger',
  title: 'Мессенджер',
  to: '/messenger',
  icon: 'mdi-message-text',
  order: 20,
};
