import type { AbilitySpec } from '@/modules/Roleplay/Rule/Dto/Ability/AbilitySpec';

export interface MagicStudyLearned {
  ruleCode: string;
  domainCode: string | null;
  spec: AbilitySpec;
}
