import { StateRuntimeEffectsService } from '@/modules/Roleplay/Character/Service/StateRuntimeEffectsService';
import { aggregateSourceDeltasService, derivedCharacteristicService } from '@/modules/Roleplay/Rule/init';

export const stateRuntimeEffectsService = new StateRuntimeEffectsService(
  aggregateSourceDeltasService,
  derivedCharacteristicService,
);
