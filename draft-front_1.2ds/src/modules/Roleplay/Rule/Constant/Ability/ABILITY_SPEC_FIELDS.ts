import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft'

/** Манифест: какие типоспецифичные поля валидны для каждого типа. */
export const ABILITY_SPEC_FIELDS: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]> = {
  trait: [],
  feature: [],
  skill: [],
  action: ['action_costs'],
  process: ['process'],
  spell: ['action_costs', 'spell'],
}
