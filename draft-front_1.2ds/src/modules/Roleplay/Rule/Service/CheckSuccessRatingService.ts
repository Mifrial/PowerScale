import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { CheckSuccessRating } from '@/modules/Roleplay/Rule/Dto/Check/CheckSuccessRating';

export class CheckSuccessRatingService {
  /** Привести базу к целевому размеру: ×2 за каждый шаг вниз по size. */
  alignBaseToSize(value: DimensionalNumberValue, targetSize: number): number {
    return value.base * Math.pow(2, value.size - targetSize);
  }

  prepareCheckSide(value: DimensionalNumberValue, minSize?: number, foldNegative = true): DimensionalNumber {
    const start = foldNegative ? new DimensionalNumber(value).foldNegativeBase() : new DimensionalNumber(value);
    if (minSize === undefined) return start;

    return start.clampMinSize(minSize);
  }

  private formatMagnitude(value: DimensionalNumberValue, signed?: boolean): string {
    const text = new DimensionalNumber(value).toString();
    if (signed && value.base > 0) return `+${text}`;

    return text;
  }

  /** Подпись после приведения: «0↓ (от +1↓²)», без приведения — обычная запись. */
  formatPreparedMagnitude(
    raw: DimensionalNumberValue,
    options?: { minSize?: number; signed?: boolean; foldNegative?: boolean },
  ): string {
    const prepared = this.prepareCheckSide(raw, options?.minSize, options?.foldNegative !== false).value;
    const preparedText = this.formatMagnitude(prepared, options?.signed);
    if (prepared.base === raw.base && prepared.size === raw.size) return preparedText;

    return `${preparedText} (от ${this.formatMagnitude(raw, options?.signed)})`;
  }

  /**
   * РУ в меньшем размере двух чисел: обе базы ×2 вниз, затем разность.
   * Так 1↓ против 5 = 1↓ против 10↓ → −9, а не −4.5.
   * Успехи с отрицательной базой сначала сворачиваются (−x размера n → 0 размера n−x).
   * Для попаданий minSize = −1: {5|-1} против {8|-2} → {5|-1} vs {4|-1} → РУ 1.
   */
  checkSuccessRating(
    successes: DimensionalNumberValue,
    difficulty: DimensionalNumberValue,
    options?: { minSize?: number },
  ): CheckSuccessRating {
    const left = this.prepareCheckSide(successes, options?.minSize);
    const right = this.prepareCheckSide(difficulty, options?.minSize);
    const targetSize = Math.min(left.value.size, right.value.size);
    const rating = this.alignBaseToSize(left.value, targetSize) - this.alignBaseToSize(right.value, targetSize);
    const passed = left.compare(right) >= 0;

    return { passed, rating };
  }
}
