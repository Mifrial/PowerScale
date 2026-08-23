import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

export interface CheckSuccessRating {
  passed: boolean;
  /** Целое РУ в меньшем из двух размеров (шаг ×2 вниз, без дробей). */
  rating: number;
}

/** Привести базу к целевому размеру: ×2 за каждый шаг вниз по size. */
export function alignBaseToSize(value: DimensionalNumberValue, targetSize: number): number {
  return value.base * Math.pow(2, value.size - targetSize);
}

/**
 * РУ в меньшем размере двух чисел: обе базы ×2 вниз, затем разность.
 * Так 1↓ против 5 = 1↓ против 10↓ → −9, а не −4.5.
 */
export function checkSuccessRating(
  successes: DimensionalNumberValue,
  difficulty: DimensionalNumberValue,
): CheckSuccessRating {
  const targetSize = Math.min(successes.size, difficulty.size);
  const rating = alignBaseToSize(successes, targetSize) - alignBaseToSize(difficulty, targetSize);
  const passed = new DimensionalNumber(successes).compare(new DimensionalNumber(difficulty)) >= 0;

  return { passed, rating };
}
