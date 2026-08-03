import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';
import type { BlockProfile } from '@/modules/Roleplay/Rule/Dto/Item/BlockProfile';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

export interface WeaponBlock {
  min_strength: DimensionalNumberValue | null;
  block_profile: BlockProfile | null;
  weapon_profiles: WeaponProfile[];
}
