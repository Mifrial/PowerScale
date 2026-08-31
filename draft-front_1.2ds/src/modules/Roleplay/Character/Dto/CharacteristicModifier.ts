import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface CharacteristicModifier {
  sourceRuleCode: string | null;
  sourceLabel: string | null;
  delta: number;
  target: string;
  scope: string | null;
  /** Потолок (ограничение сверху) характеристики — от экипированного предмета (доспех/щит). */
  limit?: DimensionalNumberValue | null;
  /** Человекочитаемая формула потолка («Сила − 3»); null для фикс. значения/макс. ловкости. */
  limitFormula?: string | null;
}
