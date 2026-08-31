import { CharacterOverviewService } from '@/modules/Roleplay/Character/Service/Overview/CharacterOverviewService';
import { FormulaEvaluationService } from '@/modules/Roleplay/Character/Service/FormulaEvaluationService';
import { ITEM_LABELS } from '@/modules/Roleplay/Character/Constant/ITEM_LABELS';
import {
  derivedCharacteristicService,
  itemModifierService,
  formatStateEffectsService,
} from '@/modules/Roleplay/Rule/init';
import { stateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/Instance/stateRuntimeEffectsService';
import { liveActionPointsLimitService } from '@/modules/Roleplay/Character/Service/Instance/liveActionPointsLimitService';
import { racialInnateGearService } from '@/modules/Roleplay/Character/Service/Instance/racialInnateGearService';
import { weaponAttackRangeService } from '@/modules/Roleplay/Character/Service/Instance/weaponAttackRangeService';

export const characterOverviewService = new CharacterOverviewService(
  new FormulaEvaluationService(),
  ITEM_LABELS,
  derivedCharacteristicService,
  itemModifierService,
  formatStateEffectsService,
  stateRuntimeEffectsService,
  liveActionPointsLimitService,
  racialInnateGearService,
  weaponAttackRangeService,
);
