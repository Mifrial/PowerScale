import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'

export interface ProcessStep {
  code: string
  name: string
  description: string
  costs: { resource_code: string; amount: DimensionalNumberValue | number }[]
}
