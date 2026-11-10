import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Перевод единиц сцены в ипари и обратно.
 *
 * Единица сцены — целое число; ипари — размерное число `{base|size}`.
 * Разрешение локации обязано быть степенью двойки, иначе точный перевод
 * в размерное число невозможен.
 */
export class SceneUnits {
  /** Единицы сцены → размерное число ипари. */
  toIpari(units: number, resolution: number): DimensionalNumberValue {
    let base = Math.trunc(units);
    let size = -this.exponent(resolution);
    if (base === 0) {
      return { base: 0, size: 0 };
    }

    while (size < 0 && base % 2 === 0) {
      base /= 2;
      size += 1;
    }

    return { base, size };
  }

  /** Размерное число ипари → единицы сцены с округлением. */
  fromIpari(value: DimensionalNumberValue, resolution: number): number {
    const exponent = value.size + this.exponent(resolution);

    return exponent >= 0 ? value.base * 2 ** exponent : Math.round(value.base / 2 ** -exponent);
  }

  /** Размерное число в виде `{base|size}`; при size = 0 — просто число. */
  format(value: DimensionalNumberValue): string {
    return value.size === 0 ? String(value.base) : `{${value.base}|${value.size}}`;
  }

  /** Единицы сцены в виде ипари. */
  formatUnits(units: number, resolution: number): string {
    return this.format(this.toIpari(units, resolution));
  }

  /** Разбор `40` или `{1|-1}`. */
  parse(text: string): DimensionalNumberValue | null {
    const normalized = text.trim();
    const pair = /^\{\s*(-?\d+)\s*\|\s*(-?\d+)\s*\}$/.exec(normalized);
    if (pair !== null) {
      const base = Number(pair[1]);
      const size = Number(pair[2]);

      return base < 0 ? null : { base, size };
    }

    if (/^\d+$/.test(normalized)) {
      return { base: Number(normalized), size: 0 };
    }

    return null;
  }

  /** Текст в единицах сцены; неверный формат — null. */
  unitsFromText(text: string, resolution: number): number | null {
    const parsed = this.parse(text);

    return parsed === null ? null : this.fromIpari(parsed, resolution);
  }

  /** Разрешение — степень двойки. */
  isPowerOfTwo(resolution: number): boolean {
    return Number.isInteger(resolution) && resolution > 0 && (resolution & (resolution - 1)) === 0;
  }

  /** Показатель степени двойки у разрешения. */
  private exponent(resolution: number): number {
    return Math.round(Math.log2(resolution));
  }
}
