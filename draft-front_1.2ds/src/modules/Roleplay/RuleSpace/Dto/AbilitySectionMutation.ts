import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';

export interface AbilitySectionMutation {
  sections: AbilitySection[];
  errors: string[];
}
