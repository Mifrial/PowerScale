import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface CharacteristicValue {
  ruleCode: string;
  /** База характеристики — размерное число (3–5 + размерность). Итог считается: база + модификаторы. */
  base: DimensionalNumberValue;
  modifiers: CharacteristicModifier[];
  /** Авторитетное значение из листа PHP. Моки его не задают — тогда итог считается из базы и модификаторов. */
  value?: DimensionalNumberValue;
  /** Сумма модификаторов из листа PHP. Только для сверки, значение берётся из value. */
  delta?: number;
}
