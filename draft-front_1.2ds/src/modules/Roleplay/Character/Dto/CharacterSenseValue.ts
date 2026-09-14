import type { CharacteristicModifier } from '@/modules/Roleplay/Character/Dto/CharacteristicModifier';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SenseStatus } from '@/modules/Roleplay/Rule/Enum/SenseStatus';
import type { LightingLevel } from '@/modules/Roleplay/Rule/Enum/LightingLevel';

/**
 * Инстанс чувства на персонаже: статус и дальность из спеки правила,
 * а `value` и `modifiers` — результат грантов `sense_modify`.
 */
export interface CharacterSenseValue {
  ruleCode: string;
  value: number;
  modifiers: CharacteristicModifier[];
  status: SenseStatus;
  radius: DimensionalNumberValue;
  /** Худшее освещение, при котором чувство работает как при хорошем. Нет поля — обычное зрение. */
  treatAsGoodDownTo?: LightingLevel;
}
