import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DimensionalNumberBaseRange } from '@/modules/Core/Engine/Dto/DimensionalNumberBaseRange';

export class DimensionalNumber {
  readonly value: DimensionalNumberValue;

  constructor(value: DimensionalNumberValue) {
    this.value = value;
  }

  static from(v: DimensionalNumberValue): DimensionalNumber {
    return new DimensionalNumber(v);
  }

  toNumber(): number {
    return Math.floor(this.value.base * Math.pow(2, this.value.size));
  }

  modify(delta: number, range?: DimensionalNumberBaseRange): DimensionalNumber {
    if (!range) {
      return new DimensionalNumber({ base: this.value.base + delta, size: this.value.size });
    }
    // Перенос между размерами: шаг базы = (max − min + 1) пунктов = один размер.
    const step = range.max - range.min + 1;
    const sizeDelta = Math.floor(delta / step);
    const baseDelta = delta - sizeDelta * step;
    let base = this.value.base + baseDelta;
    let size = this.value.size + sizeDelta;
    if (base > range.max) {
      base -= step;
      size += 1;
    } else if (base < range.min) {
      base += step;
      size -= 1;
    }

    return new DimensionalNumber({ base, size });
  }

  add(other: DimensionalNumber): DimensionalNumber {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    return new DimensionalNumber({ base: a + b, size });
  }

  subtract(other: DimensionalNumber): DimensionalNumber {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    return new DimensionalNumber({ base: a - b, size });
  }

  equalsStrict(other: DimensionalNumber): boolean {
    return this.value.base === other.value.base && this.value.size === other.value.size;
  }

  equals(other: DimensionalNumber): boolean {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    return a === b;
  }

  compare(other: DimensionalNumber): number {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    if (a < b) return -1;
    if (a > b) return 1;

    return 0;
  }

  toString(): string {
    if (this.value.size === 0) return String(this.value.base);
    const arrow = this.value.size > 0 ? '↑' : '↓';
    const abs = Math.abs(this.value.size);
    const sup = abs >= 2 ? toSuperscript(abs) : '';

    return `${this.value.base}${arrow}${sup}`;
  }

  /**
   * Разобрать строковое представление размерного числа (формат {@link toString}),
   * напр. '3', '4↓', '5↑', '3↓2'. Используется для ключей табличных цен параметров
   * («Магия Х»: '3↓' → {3,-1}).
   */
  static parse(text: string): DimensionalNumberValue {
    const normalized = text.trim();
    const match = /^(-?\d+)(↓|↑)?(.*)$/.exec(normalized);
    if (!match) return { base: 0, size: 0 };

    const base = Number(match[1]);
    if (!match[2]) return { base, size: 0 };
    const direction = match[2] === '↑' ? 1 : -1;
    const sup = match[3];
    const size = sup.length ? direction * fromSuperscript(sup) : direction;

    return { base, size };
  }
}

const superscriptDigits = '⁰¹²³⁴⁵⁶⁷⁸⁹';

function toSuperscript(n: number): string {
  const digits = superscriptDigits;

  return String(n)
    .split('')
    .map((d) => digits[Number(d)] ?? d)
    .join('');
}

function fromSuperscript(text: string): number {
  let value = 0;
  for (const char of text) {
    const digit = superscriptDigits.indexOf(char);
    if (digit < 0) return value;
    value = value * 10 + digit;
  }

  return value;
}
