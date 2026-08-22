import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { ActionCharacteristicValue } from '@/modules/Roleplay/Rule/Dto/Item/ActionCharacteristicValue';

export interface WeaponProfile {
  type: 'strike' | 'throw' | 'shoot';
  distance: Formula;
  range: Formula | null;
  damage: { formula: Formula; damage_type_code: string | null };
  penetration: Formula;
  accuracy: DimensionalNumberValue;
  /** Базы «Силы удара/броска/выстрела» действия (пусто = характеристика персонажа). */
  action_characteristics?: ActionCharacteristicValue[];
  /** «Дальнобойность»: шаг (в ипари), за каждые который сила броска/выстрела падает на размер. */
  falloff?: DimensionalNumberValue;
}
