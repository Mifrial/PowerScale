import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Вход формулы Сложности сотворения (уже разрешённые мощь/контроль и сопротивление). */
export interface SpellCastDifficultyInput {
  requiredPower: DimensionalNumberValue;
  /** Дополнительные шаги Требуемой мощи от выбранного модификатора каста. */
  requiredPowerDelta?: number;
  usedPower: DimensionalNumberValue;
  requiredControl: DimensionalNumberValue;
  availableControl: DimensionalNumberValue;
  /** Итоговое сопротивление магии цели, если заклинание выбирает цель. */
  resistanceModify?: number;
  /** Сколько единиц сопротивления игнорируется выбранным модификатором каста. */
  resistancePenetration?: number;
  /** Одноразовый бонус от тренировки следующего каста. */
  trainingDifficultyDelta?: number;
}
