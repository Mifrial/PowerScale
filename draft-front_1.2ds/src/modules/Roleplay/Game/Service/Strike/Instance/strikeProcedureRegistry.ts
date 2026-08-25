import { StrikeProcedureRegistry } from '@/modules/Roleplay/Game/Service/Strike/StrikeProcedureRegistry';
import { shootV1 } from '@/modules/Roleplay/Game/Service/Strike/shootV1';
import { strikeV1 } from '@/modules/Roleplay/Game/Service/Strike/strikeV1';
import { throwV1 } from '@/modules/Roleplay/Game/Service/Strike/throwV1';

export const strikeProcedureRegistry = new StrikeProcedureRegistry();
strikeProcedureRegistry.register(strikeV1);
strikeProcedureRegistry.register(throwV1);
strikeProcedureRegistry.register(shootV1);
