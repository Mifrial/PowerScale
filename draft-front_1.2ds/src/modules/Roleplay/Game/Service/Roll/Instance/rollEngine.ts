import { RollEngine } from '@/modules/Roleplay/Game/Service/Roll/RollEngine';
import { mechanicEngine, registerMechanicHandler } from '@/modules/Roleplay/Mechanic/init';
import { rollAdvantageHandler } from '@/modules/Roleplay/Game/Service/Handler/RollAdvantageHandler';
import { rollSixOneHandler } from '@/modules/Roleplay/Game/Service/Handler/RollSixOneHandler';
import { rollCriticalStrikeHandler } from '@/modules/Roleplay/Game/Service/Handler/RollCriticalStrikeHandler';

registerMechanicHandler(rollAdvantageHandler);
registerMechanicHandler(rollSixOneHandler);
registerMechanicHandler(rollCriticalStrikeHandler);

export const rollEngine = new RollEngine(mechanicEngine);
