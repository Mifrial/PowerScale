export interface Rule {
  id: string
  code: string
  type: RuleType
  name: string
  description: string
  spaceId: number
  spec?: Record<string, any>
  tagIds?: number[]
  mechanicId?: number | null
  createdAt: string
  updatedAt?: string
}

export type RuleType = 'simple' | 'race' | 'characteristic' | 'ability' | 'resource' | 'item' | 'damage_type'

export interface RuleVersion {
  id: number
  ruleId: string
  code: string
  spaceId: number
  versionA: number
  versionB: number
  versionC: number
  name: string
  description: string
  spec?: Record<string, any>
  tagIds?: number[]
  mechanicId?: number | null
  createdAt: string
}

export interface CreateRuleData {
  code?: string
  type: RuleType
  name: string
  description: string
  spec?: Record<string, any>
  tagIds?: number[]
  mechanicId?: number | null
}

export interface UpdateRuleData {
  code?: string
  name?: string
  description?: string
  spec?: Record<string, any>
  tagIds?: number[]
  mechanicId?: number | null
}
