import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'

export interface RuleVersion {
  id: number
  ruleId: string
  spaceId: number
  versionA: number
  versionB: number
  versionC: number
  name: string
  description: string
  spec?: RuleSpec
  keywordIds?: number[]
  mechanicId?: number | null
  createdAt: string
}
