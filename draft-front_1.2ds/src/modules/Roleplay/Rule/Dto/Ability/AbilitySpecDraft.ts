import type { AbilitySpecBase } from './AbilitySpecBase'
import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'
import type { ActionCost } from './ActionCost'
import type { ProcessSpec } from './ProcessSpec'
import type { SpellSpec } from './SpellSpec'

/** Черновой слой редактора: type опционален, типоспецифичные поля могут «висеть» при смене типа. */
export interface AbilitySpecDraft extends AbilitySpecBase {
  type?: AbilityType
  action_costs: ActionCost[]
  process?: ProcessSpec
  spell?: SpellSpec
}
