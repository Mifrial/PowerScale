import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ResourceLimitBonus } from '@/modules/Roleplay/Character/Dto/ResourceLimitBonus';

export interface ResourceValue {
  ruleId: string;
  /** Текущее значение — размерное число (безразмерный ресурс — размер 0). */
  current: DimensionalNumberValue;
  /** Базовый лимит — размерное число (безразмерный ресурс — размер 0). */
  base: DimensionalNumberValue;
  /** Бонусы и штрафы к лимиту; лимит = база + сумма дельт. */
  bonuses: ResourceLimitBonus[];
}
