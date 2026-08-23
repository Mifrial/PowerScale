import { InjuryProcedureRegistry } from '@/modules/Roleplay/Game/Service/Injury/InjuryProcedureRegistry';
import { injuryV1 } from '@/modules/Roleplay/Game/Service/Injury/injuryV1';

export const injuryProcedureRegistry = new InjuryProcedureRegistry();
injuryProcedureRegistry.register(injuryV1);
