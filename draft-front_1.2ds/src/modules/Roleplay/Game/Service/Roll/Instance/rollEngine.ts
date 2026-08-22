import { RollEngine } from '@/modules/Roleplay/Game/Service/Roll/RollEngine';
import { mechanicEngine } from '@/modules/Roleplay/Rule/init';

export const rollEngine = new RollEngine(mechanicEngine);
