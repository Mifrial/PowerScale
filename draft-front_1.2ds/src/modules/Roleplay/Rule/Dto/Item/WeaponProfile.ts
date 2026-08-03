import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

export interface WeaponProfile {
  type: 'strike' | 'throw' | 'shoot';
  distance: Formula;
  range: Formula | null;
  damage: { formula: Formula; damage_type_code: string | null };
  penetration: Formula;
  accuracy: DimensionalNumberValue;
}
