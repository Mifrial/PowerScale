export interface NavItem {
  icon: string
  label: string
  to: string
  exact?: boolean
}

export const navItems: NavItem[] = [
  { icon: 'mdi-home', label: 'Главная', to: '/', exact: true },
  { icon: 'mdi-message-text', label: 'Мессенджер', to: '/messenger' },
  { icon: 'mdi-bell-ring', label: 'Уведомления', to: '/notifications' },
  { icon: 'mdi-cube-outline', label: 'Пространства', to: '/spaces' },
  { icon: 'mdi-account-group', label: 'Персонажи', to: '/characters' },
  { icon: 'mdi-gamepad-variant', label: 'Игры', to: '/games' },
  { icon: 'mdi-account-multiple', label: 'Пользователи', to: '/users' },
]
