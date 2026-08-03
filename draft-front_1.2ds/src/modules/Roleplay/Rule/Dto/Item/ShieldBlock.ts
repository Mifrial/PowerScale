import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';
import type { BlockProfile } from '@/modules/Roleplay/Rule/Dto/Item/BlockProfile';

export interface ShieldBlock {
  min_strength: DimensionalNumberValue | null;
  block: BlockProfile;
}
