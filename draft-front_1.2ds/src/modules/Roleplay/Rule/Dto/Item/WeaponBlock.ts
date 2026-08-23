import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { BlockProfile } from '@/modules/Roleplay/Rule/Dto/Item/BlockProfile';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

export interface WeaponBlock {
  min_strength: DimensionalNumberValue | null;
  block_profile: BlockProfile | null;
  weapon_profiles: WeaponProfile[];
  /** Прочность оружия (размерное число; «5↑»→{5|1}, «Огромная(+2)»→{5|2}). */
  durability?: DimensionalNumberValue;
  /** Минимум ОД на удар этим оружием (оковка посоха — 2). */
  min_action_cost?: number | null;
}
