import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';
import type { ActionCharacteristicValue } from '@/modules/Roleplay/Rule/Dto/Item/ActionCharacteristicValue';

export interface WeaponProfile {
  type: 'strike' | 'throw' | 'shoot';
  distance: DimensionalFormula;
  range: DimensionalFormula | null;
  damage: { formula: DimensionalFormula; damage_type_code: string | null };
  penetration: DimensionalFormula;
  accuracy: DimensionalNumberValue;
  /** Базы «Силы удара/броска/выстрела» действия (пусто = характеристика персонажа). */
  action_characteristics?: ActionCharacteristicValue[];
  /** «Дальнобойность»: шаг (в ипари), за каждые который сила броска/выстрела падает на размер. */
  falloff?: DimensionalNumberValue;
  /** Польза уклонения: S = Ловкость.modify(dodge_benefit). Нет поля — fallback −3. */
  dodge_benefit?: number;
}
