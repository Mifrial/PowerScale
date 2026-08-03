import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType'

/** Различительный тег типа: его наличие в тегах способности указывает на этот тип (resolveTypeFromKeywords). */
export const ABILITY_TYPE_DISTINCTIVE_KEYWORD: Record<AbilityType, string> = {
  trait: 'trait',
  feature: 'feature',
  skill: 'skill',
  action: 'action',
  process: 'process',
  spell: 'spell',
}
