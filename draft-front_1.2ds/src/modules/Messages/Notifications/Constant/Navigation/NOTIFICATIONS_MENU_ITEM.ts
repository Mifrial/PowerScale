import type { MenuItem } from '@/modules/Core/UI/Dto/Navigation/MenuItem';

export const NOTIFICATIONS_MENU_ITEM: MenuItem = {
  kind: 'menuItem',
  id: 'notifications',
  title: 'Уведомления',
  to: '/notifications',
  icon: 'mdi-bell-ring',
  order: 30,
};
