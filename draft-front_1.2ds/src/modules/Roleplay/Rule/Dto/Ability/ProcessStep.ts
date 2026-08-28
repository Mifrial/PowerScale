import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ActionEffect } from '@/modules/Roleplay/Rule/Dto/Ability/ActionEffect';

export interface ProcessStep {
  code: string;
  name: string;
  description: string;
  costs: { resource_code: string; amount: DimensionalNumberValue | number }[];
  interruption: {
    mode: 'normal' | 'emergency';
    effects?: ActionEffect[];
  };
}
