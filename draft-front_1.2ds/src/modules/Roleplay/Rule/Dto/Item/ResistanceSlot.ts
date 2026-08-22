import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface ResistanceSlot {
  damage_type_code: string | null;
  value: DimensionalNumberValue;
  durability: number;
  source_code: string | null;
}
