import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Эффективная мощь источника, посчитанная сервером. */
export interface SpellSourcePowerValue {
  inventoryId: number;
  ruleCode: string;
  characteristicCode: string;
  base: DimensionalNumberValue;
  bonus: number;
  value: DimensionalNumberValue;
  contributions: { sourceRuleCode: string; delta: number }[];
}
