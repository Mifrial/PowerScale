import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Контекст разрешения SpellValue и сопротивления цели. */
export interface SpellCastResolveInput {
  spellCode: string;
  usedPower: DimensionalNumberValue;
  availableControl: DimensionalNumberValue;
  parameterValues: Record<string, DimensionalNumberValue>;
  hasTarget: boolean;
  /** Явный тип урона для resistance (тест); иначе из `spell.damage`. */
  damageTypeCode?: string | null;
  targetResistanceAmount?: number;
}
