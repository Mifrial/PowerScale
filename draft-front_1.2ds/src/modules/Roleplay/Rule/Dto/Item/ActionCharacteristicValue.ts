import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';

/** База характеристики действия (actionCharacteristic): значение по умолчанию — характеристика персонажа. */
export interface ActionCharacteristicValue {
  characteristic: string;
  value: Formula;
}
