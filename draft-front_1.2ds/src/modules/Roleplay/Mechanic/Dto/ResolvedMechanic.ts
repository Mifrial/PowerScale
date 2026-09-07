import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';
import type { MechanicHandler } from '@/modules/Roleplay/Mechanic/Interface/MechanicHandler';

/** Механика активного правила, разрешённая до хендлера: code@version + payload. */
export interface ResolvedMechanic {
  handler: MechanicHandler;
  payload: MechanicPayload | null;
}
