import type { AbilityType } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityType';
import type { AbilityTypeChipMode } from '@/modules/Roleplay/Rule/Enum/Ability/AbilityTypeChipMode';

export interface AbilityTypeChipRefinement {
  whenType: AbilityType;
  keywordCode: string;
  mode: AbilityTypeChipMode;
}
