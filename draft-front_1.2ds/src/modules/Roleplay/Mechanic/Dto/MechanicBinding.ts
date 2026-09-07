import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';

/**
 * Срез правила для Engine: не весь Rule.
 */
export interface MechanicBinding {
  ruleCode: string;
  mechanicId: number | null;
  mechanicPayload: MechanicPayload | null;
}
