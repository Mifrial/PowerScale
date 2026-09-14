import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Подсказка запуска проверки знания. */
export interface KnowledgeCheckLaunchHint {
  level: number;
  shortage: number;
  raised: DimensionalNumberValue;
  lawDefenseDelta: number;
}
