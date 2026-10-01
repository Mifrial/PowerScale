import type { MechanicPayload } from '@/modules/Roleplay/Mechanic/Dto/MechanicPayload';

/** Строка списка механик на правиле. */
export interface RuleMechanicRef {
  mechanicId: number;
  mechanicPayload: MechanicPayload | null;
}
