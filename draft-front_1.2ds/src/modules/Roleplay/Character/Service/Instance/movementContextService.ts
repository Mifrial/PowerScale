import { MovementContextService } from '@/modules/Roleplay/Character/Service/MovementContextService';
import { raceSpecService } from '@/modules/Roleplay/Rule/init';

export const movementContextService = new MovementContextService(raceSpecService);
