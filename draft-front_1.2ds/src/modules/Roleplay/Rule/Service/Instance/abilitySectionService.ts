import { ABILITY_SECTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_SECTIONS';
import { AbilitySectionService } from '@/modules/Roleplay/Rule/Service/AbilitySectionService';

export const abilitySectionService = new AbilitySectionService(ABILITY_SECTIONS);
