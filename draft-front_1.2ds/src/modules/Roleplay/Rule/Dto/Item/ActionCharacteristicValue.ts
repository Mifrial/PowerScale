import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';

/** База характеристики действия (actionCharacteristic): значение по умолчанию — характеристика персонажа. */
export interface ActionCharacteristicValue {
  characteristic: string;
  value: DimensionalFormula;
}
