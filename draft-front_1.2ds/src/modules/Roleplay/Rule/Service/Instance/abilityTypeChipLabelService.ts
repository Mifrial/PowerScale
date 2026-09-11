import { AbilityTypeChipLabelService } from '@/modules/Roleplay/Rule/Service/AbilityTypeChipLabelService';
import { ABILITY_TYPE_LABELS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_LABELS';
import { ABILITY_TYPE_CHIP_REFINEMENTS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_CHIP_REFINEMENTS';

export const abilityTypeChipLabelService = new AbilityTypeChipLabelService(
  ABILITY_TYPE_LABELS,
  ABILITY_TYPE_CHIP_REFINEMENTS,
);
