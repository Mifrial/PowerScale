export interface PermissionAction {
  key: string
  label: string
}

export interface PermissionCategory {
  key: string
  label: string
  actions: PermissionAction[]
}

export interface AdminSection {
  id: string
  title: string
  to: string
  icon: string
  permission: string
}
