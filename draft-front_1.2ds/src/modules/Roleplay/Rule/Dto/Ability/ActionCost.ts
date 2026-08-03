import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';

export type ActionCost = { resource_code: string; amount: DimensionalNumberValue | number; label?: string };
