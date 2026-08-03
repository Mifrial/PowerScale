import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';

/** Ресурс (type='resource') — определение ресурса персонажа. */
export interface ResourceSpec {
  is_dimensional: boolean;
  initial_value: DimensionalNumberValue | number | null;
}
