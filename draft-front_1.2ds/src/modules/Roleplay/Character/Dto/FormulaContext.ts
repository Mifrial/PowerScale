import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface FormulaContext {
  /** Значения характеристик как размерные числа (база + размерность). */
  characteristicValues: Map<string, DimensionalNumberValue>;
  abilityLevels: Map<string, number>;
  /** Значения параметров текущей способности. undefined — параметра нет, это не ноль. */
  parameterValues?: (code: string) => number | undefined;
  /** База характеристики действия (actionCharacteristic): из action_characteristics профиля оружия; undefined — характеристика персонажа. */
  actionCharacteristicValue?: (
    action: 'strike' | 'throw' | 'shoot',
    characteristic: string,
  ) => DimensionalNumberValue | undefined;
}
