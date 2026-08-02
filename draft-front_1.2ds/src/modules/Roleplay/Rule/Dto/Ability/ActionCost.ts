import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'

export type ActionCost = { resource_code: string; amount: DimensionalNumberValue | number; label?: string }
