import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { BlockProfile } from '@/modules/Roleplay/Rule/Dto/Item/BlockProfile';
import type { CharacteristicLimit } from '@/modules/Roleplay/Rule/Dto/Item/CharacteristicLimit';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

export interface ShieldBlock {
  min_strength: DimensionalNumberValue | null;
  block: BlockProfile;
  /** Прочность щита (размерное число). */
  durability?: DimensionalNumberValue;
  /** Атакующие профили (щит — также оружие: «дробящий удар»). */
  weapon_profiles?: WeaponProfile[];
  /** Ограничения характеристик формулой («Макс. Ловкость/Реакция: [Сила]»). */
  characteristic_limits?: CharacteristicLimit[];
}
