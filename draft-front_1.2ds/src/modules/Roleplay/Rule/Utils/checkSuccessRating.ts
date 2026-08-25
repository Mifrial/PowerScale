import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

export interface CheckSuccessRating {
  passed: boolean;
  /** Целое РУ в меньшем из двух размеров (шаг ×2 вниз, без дробей). */
  rating: number;
}

/** Попадание / атака: успехи не мельче маленького размера (−1); минимум значения {0|-1}. */
export const HIT_MIN_SUCCESS_SIZE = -1;

/** Привести базу к целевому размеру: ×2 за каждый шаг вниз по size. */
export function alignBaseToSize(value: DimensionalNumberValue, targetSize: number): number {
  return value.base * Math.pow(2, value.size - targetSize);
}

export function prepareCheckSide(
  value: DimensionalNumberValue,
  minSize?: number,
  foldNegative = true,
): DimensionalNumber {
  const start = foldNegative ? new DimensionalNumber(value).foldNegativeBase() : new DimensionalNumber(value);
  if (minSize === undefined) return start;

  return start.clampMinSize(minSize);
}

function formatMagnitude(value: DimensionalNumberValue, signed?: boolean): string {
  const text = new DimensionalNumber(value).toString();
  if (signed && value.base > 0) return `+${text}`;

  return text;
}

/** Подпись после приведения: «0↓ (от +1↓²)», без приведения — обычная запись. */
export function formatPreparedMagnitude(
  raw: DimensionalNumberValue,
  options?: { minSize?: number; signed?: boolean; foldNegative?: boolean },
): string {
  const prepared = prepareCheckSide(raw, options?.minSize, options?.foldNegative !== false).value;
  const preparedText = formatMagnitude(prepared, options?.signed);
  if (prepared.base === raw.base && prepared.size === raw.size) return preparedText;

  return `${preparedText} (от ${formatMagnitude(raw, options?.signed)})`;
}

/**
 * РУ в меньшем размере двух чисел: обе базы ×2 вниз, затем разность.
 * Так 1↓ против 5 = 1↓ против 10↓ → −9, а не −4.5.
 * Успехи с отрицательной базой сначала сворачиваются (−x размера n → 0 размера n−x).
 * Для попаданий minSize = −1: {5|-1} против {8|-2} → {5|-1} vs {4|-1} → РУ 1.
 */
export function checkSuccessRating(
  successes: DimensionalNumberValue,
  difficulty: DimensionalNumberValue,
  options?: { minSize?: number },
): CheckSuccessRating {
  const left = prepareCheckSide(successes, options?.minSize);
  const right = prepareCheckSide(difficulty, options?.minSize);
  const targetSize = Math.min(left.value.size, right.value.size);
  const rating = alignBaseToSize(left.value, targetSize) - alignBaseToSize(right.value, targetSize);
  const passed = left.compare(right) >= 0;

  return { passed, rating };
}
