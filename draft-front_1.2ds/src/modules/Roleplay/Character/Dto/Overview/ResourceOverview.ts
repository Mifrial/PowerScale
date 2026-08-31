import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { ResourceLimitOverview } from '@/modules/Roleplay/Character/Dto/Overview/ResourceLimitOverview';

export interface ResourceOverview {
  ruleCode: string;
  name: string;
  /** Текущее значение — размерное число. */
  current: DimensionalNumberValue;
  currentLabel: string;
  /** Лимит = база + сумма бонусов и штрафов. */
  max: DimensionalNumberValue;
  maxLabel: string;
  /** Базовый лимит без бонусов и штрафов. */
  base: DimensionalNumberValue;
  baseLabel: string;
  bonuses: ResourceLimitOverview[];
  href: string | null;
  isResolved: boolean;
}
