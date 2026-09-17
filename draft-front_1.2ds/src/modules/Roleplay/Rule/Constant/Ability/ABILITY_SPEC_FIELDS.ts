import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { AbilitySpecDraft } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpecDraft';

/** Манифест: какие типоспецифичные поля валидны для каждого типа. */
export const ABILITY_SPEC_FIELDS: Record<AbilityType, readonly (keyof AbilitySpecDraft)[]> = {
  trait: [],
  feature: [],
  skill: ['action_effects'],
  action: ['action_components', 'action_effects', 'attack_mode', 'max_targets', 'push'],
  process: ['process'],
  spell: ['action_components', 'action_effects', 'attack_mode', 'max_targets', 'spell'],
  group: [],
};
