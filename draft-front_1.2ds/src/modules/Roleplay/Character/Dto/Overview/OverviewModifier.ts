import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface OverviewModifier {
  source: string;
  sourceRuleId: string | null;
  sourceHref: string | null;
  sourceResolved: boolean;
  /** Роль источника в читаемом виде, напр. «от мастерства», «от предмета»; null для источников без правила. */
  sourceRole: string | null;
  /** Уровень способности-источника, если применимо. */
  sourceLevel: number | null;
  delta: number;
  target: string;
  targetHref: string | null;
  scope: string | null;
  /** Потолок (ограничение сверху) характеристики — от экипированного предмета. */
  limit?: DimensionalNumberValue | null;
  /** Человекочитаемая формула потолка («Сила − 3»); null для фикс. значения/макс. ловкости. */
  limitFormula?: string | null;
}
