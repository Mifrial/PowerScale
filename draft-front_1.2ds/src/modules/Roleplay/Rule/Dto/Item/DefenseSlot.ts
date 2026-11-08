import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface DefenseSlot {
  defense: DimensionalNumberValue;
  durability: number;
  source_code: string | null;
  /** Слот держит и внутренний урон. */
  protects_internal?: boolean;
}
