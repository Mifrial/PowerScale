import { AbilityCheckAdvantagesService } from '@/modules/Roleplay/Character/Service/AbilityCheckAdvantagesService';
import { aggregateSourceDeltasService } from '@/modules/Roleplay/Rule/init';

export const abilityCheckAdvantagesService = new AbilityCheckAdvantagesService(aggregateSourceDeltasService);
