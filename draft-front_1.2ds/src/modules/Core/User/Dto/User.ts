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
  /** Дата окончания временной деактивации (ТР §3 users.deactivated_until). */
  deactivated_until?: string
  /** Причина деактивации (ТР §3 users.deactivate_reason). */
  deactivate_reason?: string
  /** Мёрж permission-ключей из групп (resolvePermissions). Источник прав для guard'ов и UI. */
  permissions?: string[]
}
