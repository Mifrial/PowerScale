import { StrikeProcedureRegistry } from '@/modules/Roleplay/Game/Service/Strike/StrikeProcedureRegistry';
import { strikeV1 } from '@/modules/Roleplay/Game/Service/Strike/strikeV1';

export const strikeProcedureRegistry = new StrikeProcedureRegistry();
strikeProcedureRegistry.register(strikeV1);
