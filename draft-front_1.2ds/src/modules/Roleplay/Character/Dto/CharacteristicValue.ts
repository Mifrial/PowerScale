import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface CharacteristicValue {
  ruleCode: string;
  /** База характеристики — размерное число (3–5 + размерность). Итог считается: база + модификаторы. */
  base: DimensionalNumberValue;
  modifiers: CharacteristicModifier[];
}
