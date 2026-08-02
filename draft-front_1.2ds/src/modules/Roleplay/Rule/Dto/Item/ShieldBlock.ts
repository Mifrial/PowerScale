import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import type { BlockProfile } from './BlockProfile'

export interface ShieldBlock {
  min_strength: DimensionalNumberValue | null
  block: BlockProfile
}
