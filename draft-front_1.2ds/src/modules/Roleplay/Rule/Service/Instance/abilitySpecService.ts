import { AbilitySpecService } from '@/modules/Roleplay/Rule/Service/Spec/AbilitySpecService';
import { ABILITY_SPEC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SPEC_FIELDS';
import { ABILITY_TYPE_PRECEDENCE } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_PRECEDENCE';
import { ABILITY_TYPE_DISTINCTIVE_KEYWORD } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_DISTINCTIVE_KEYWORD';
import { ABILITY_TYPE_SPECIFIC_FIELDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_SPECIFIC_FIELDS';
import { ABILITY_TYPE_KEYWORDS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_TYPE_KEYWORDS';

export const abilitySpecService = new AbilitySpecService(
  ABILITY_SPEC_FIELDS,
  ABILITY_TYPE_PRECEDENCE,
  ABILITY_TYPE_DISTINCTIVE_KEYWORD,
  ABILITY_TYPE_SPECIFIC_FIELDS,
  ABILITY_TYPE_KEYWORDS,
);
