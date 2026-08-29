import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';

/**
 * Инстанс чувства на персонаже: статус и дальность из спеки правила,
 * а `value` и `modifiers` — результат грантов `sense_modify`.
 */
export interface CharacterSenseValue {
  ruleId: string;
  value: number;
  modifiers: CharacteristicModifier[];
  status: SenseStatus;
  radius: DimensionalNumberValue;
}
