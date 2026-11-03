import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SupportSide } from '@/modules/Roleplay/Rule/Enum/Ability/SupportSide';
import type { WeaponProfileType } from '@/modules/Roleplay/Rule/Enum/Item/WeaponProfileType';

/** Узел спеки support: кого и насколько усиливает реакция. */
export interface SupportSpec {
  sides: SupportSide[];
  advantage: number;
  profile?: WeaponProfileType;
  min_mastery?: DimensionalNumberValue;
  source?: string;
}
