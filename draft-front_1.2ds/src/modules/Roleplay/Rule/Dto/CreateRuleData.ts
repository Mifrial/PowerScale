import type { RuleType } from '@/modules/Roleplay/Rule/Enum/RuleType'
import type { RuleSpec } from '@/modules/Roleplay/Rule/Enum/RuleSpec'

export interface CreateRuleData {
  /** Опционально: если пусто — генерируется slug-ом из названия. Далее не изменяется. */
  code?: string
  type: RuleType
  name: string
  description: string
  spec?: RuleSpec
  keywordIds?: number[]
  mechanicId?: number | null
}
