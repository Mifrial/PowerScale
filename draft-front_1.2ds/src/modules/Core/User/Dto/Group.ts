export interface Group {
  id: number
  name: string
  active: boolean
  memberCount: number
  permissions: string[]
  createdAt?: string
}
