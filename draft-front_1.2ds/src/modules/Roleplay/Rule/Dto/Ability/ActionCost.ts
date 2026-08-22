import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export type ActionCost = { resource_code: string; amount: DimensionalNumberValue | number; label?: string };
