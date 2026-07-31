export interface Space {
  id: number
  code: string
  name: string
  description: string
  revision: number
  active: boolean
  createdAt: string
  rulesCount: number
}

export interface SpaceRevisionMeta {
  revision: number
  publishedAt: string
  ruleCount: number
  changedCount: number
}

export interface SpaceRevision<TRule = any> {
  revision: number
  publishedAt: string
  rules: TRule[]
}

export interface SpaceCreateData {
  name: string
  description: string
  inheritFrom?: number | null
}

export interface SpaceUpdateData {
  name?: string
  description?: string
}
