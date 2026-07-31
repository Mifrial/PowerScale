export interface User {
  id: number
  name: string
  surname?: string
  nickname?: string
  login: string
  email: string
  groups: string[]
  registered: string
  active: boolean
  lastLogin?: string
  avatar_file_id?: number
  super_admin?: boolean
}

export interface Group {
  id: number
  name: string
  active: boolean
  memberCount: number
  permissions: string[]
  createdAt?: string
}

export const PERMISSION_KEYS = {
  user: ['view', 'view_sensitive', 'create', 'edit', 'deactivate'],
  user_group: ['view', 'create', 'edit', 'deactivate'],
  space: ['create', 'view_all', 'edit_all'],
  rule: ['view', 'create', 'edit', 'delete'],
  character: ['create', 'view'],
  game: ['create', 'view_all', 'edit_all'],
  tag: ['view', 'create', 'edit', 'delete'],
  notification_template: ['view', 'create', 'edit', 'delete'],
} as const

export const PERMISSION_LABELS: Record<string, string> = {
  user: 'Пользователи',
  user_group: 'Группы',
  space: 'Пространства',
  rule: 'Правила',
  character: 'Персонажи',
  game: 'Игры',
  tag: 'Теги',
  notification_template: 'Шаблоны уведомлений',
}

export const ACTION_LABELS: Record<string, string> = {
  view: 'Просмотр',
  view_sensitive: 'Просмотр скрытых полей',
  create: 'Создание',
  edit: 'Редактирование',
  delete: 'Удаление',
  deactivate: 'Деактивация',
  view_all: 'Просмотр всех',
  edit_all: 'Редактирование всех',
}
