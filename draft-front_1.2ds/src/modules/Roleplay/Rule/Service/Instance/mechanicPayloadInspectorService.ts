import { KNOWN_MECHANIC_PAYLOAD_TYPES } from '@/modules/Roleplay/Mechanic/Constant/KNOWN_MECHANIC_PAYLOAD_TYPES';
import { MechanicPayloadInspectorService } from '@/modules/Roleplay/Rule/Service/MechanicPayloadInspectorService';

export const mechanicPayloadInspectorService = new MechanicPayloadInspectorService(KNOWN_MECHANIC_PAYLOAD_TYPES);
