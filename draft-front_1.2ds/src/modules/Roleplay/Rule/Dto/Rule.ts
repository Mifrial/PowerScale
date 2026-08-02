import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'

export interface Rule {
  id: string
  /** Семантический ключ правила (глобально уникален). Задаётся при создании и не меняется. */
  code: string
  type: RuleType
  name: string
  description: string
  spaceId: number
  spec?: RuleSpec
  keywordIds?: number[]
  mechanicId?: number | null
  createdAt: string
  updatedAt?: string
}
