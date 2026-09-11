import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface SpellCastSpellOption {
  ruleCode: string;
  name: string;
  requiredPower: DimensionalNumberValue | null;
  requiredControl: DimensionalNumberValue | null;
}
