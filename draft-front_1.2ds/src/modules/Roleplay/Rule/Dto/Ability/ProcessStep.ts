import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface ProcessStep {
  code: string;
  name: string;
  description: string;
  costs: { resource_code: string; amount: DimensionalNumberValue | number }[];
}
