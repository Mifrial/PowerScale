import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Характеристика в модели редактора: база (раса/покупка/дары) + модификаторы способностей. */
export interface EditorCharacteristic {
  ruleCode: string;
  code: string;
  name: string;
  /** База из расы/покупки (без модификаторов). */
  base: DimensionalNumberValue;
  /** Дельта из модификаторов способностей (в пунктах базы). */
  delta: number;
  /** Итог: база + дельта (размерность базы сохраняется). */
  value: DimensionalNumberValue;
  modifiers: CharacteristicModifier[];
}
