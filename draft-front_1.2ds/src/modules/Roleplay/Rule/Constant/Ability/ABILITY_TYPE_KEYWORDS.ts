import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';

export const ABILITY_TYPE_KEYWORDS: Record<AbilityType, string[]> = {
  trait: ['trait'],
  feature: ['feature'],
  skill: ['skill'],
  action: ['skill', 'action'],
  process: ['skill', 'action', 'process'],
  spell: ['skill', 'magic', 'action', 'spell'],
};
