import type { DimensionalNumberValue } from '@/modules/Core/Engine/Value/DimensionalNumber'
import type { BlockProfile } from './BlockProfile'
import type { WeaponProfile } from './WeaponProfile'

export interface WeaponBlock {
  min_strength: DimensionalNumberValue | null
  block_profile: BlockProfile | null
  weapon_profiles: WeaponProfile[]
}
