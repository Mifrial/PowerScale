import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface ResistanceSlot {
  damage_type_code: string | null;
  value: DimensionalNumberValue;
  /** Порог РУ. null — абсолютная применимость, слот не снимается расходом успеха. */
  durability: number | null;
  source_code: string | null;
}
