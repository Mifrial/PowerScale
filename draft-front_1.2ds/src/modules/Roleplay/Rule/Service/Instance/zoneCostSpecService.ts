import { ZoneCostSpecService } from '@/modules/Roleplay/Rule/Service/Spec/ZoneCostSpecService';
import { ABILITY_EMPTY_COST_TABLE } from '@/modules/Roleplay/Rule/Constant/Ability/ABILITY_EMPTY_COST_TABLE';

export const zoneCostSpecService = new ZoneCostSpecService(ABILITY_EMPTY_COST_TABLE);
