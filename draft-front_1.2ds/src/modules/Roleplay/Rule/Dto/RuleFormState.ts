import type { RuleSpec } from '@/modules/Roleplay/Rule/Dto/RuleSpec'
import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType'

export interface RuleFormState {
  type: RuleType
  name: string
  code: string
  loadedCode: string
  description: string
  mechanicId: number | null
  keywordIds: number[]
  spec: RuleSpec | null
}
