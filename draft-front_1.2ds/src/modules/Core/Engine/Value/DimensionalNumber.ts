import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DimensionalNumberBaseRange } from '@/modules/Core/Engine/Dto/DimensionalNumberBaseRange';

/**
 * Размерное число: база и размер (степень двойки) без потери точности при сложении.
 */
export class DimensionalNumber {
  readonly value: DimensionalNumberValue;

  /**
   * Сохраняет пару база/размер.
   *
   * @param value Значение размерного числа.
   */
  constructor(value: DimensionalNumberValue) {
    this.value = value;
  }

  /**
   * Создаёт размерное число из plain-значения.
   *
   * @param v Значение база/размер.
   */
  static from(v: DimensionalNumberValue): DimensionalNumber {
    return new DimensionalNumber(v);
  }

  /**
   * Переводит значение в целое: floor(base × 2^size).
   */
  toNumber(): number {
    return Math.floor(this.value.base * Math.pow(2, this.value.size));
  }

  /**
   * Сдвигает базу на delta с переносом размера при выходе за диапазон.
   *
   * @param delta Сдвиг базы в пунктах текущего размера.
   * @param range Допустимый интервал базы; без него размер не меняется.
   */
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

  /**
   * Сменить размер без дробей: вниз — база ×2 за шаг, вверх — floor(база / 2).
   * {4|-2} → size -1 = {2|-1}; {3|-2} → {1|-1}.
   */
  withSize(targetSize: number): DimensionalNumber {
    const delta = targetSize - this.value.size;
    if (delta === 0) return this;
    if (delta < 0) {
      return new DimensionalNumber({ base: this.value.base * 2 ** -delta, size: targetSize });
    }

    return new DimensionalNumber({ base: Math.floor(this.value.base / 2 ** delta), size: targetSize });
  }

  /**
   * Отрицательная база: −x успехов размера n = 0 успехов размера n−x.
   * {−2|0} → {0|-2}.
   */
  foldNegativeBase(): DimensionalNumber {
    if (this.value.base >= 0) return this;

    return new DimensionalNumber({ base: 0, size: this.value.size + this.value.base });
  }

  /** Поднять размер, если он меньше минимума (попадания: не мельче −1). */
  clampMinSize(minSize: number): DimensionalNumber {
    if (this.value.size >= minSize) return this;

    return this.withSize(minSize);
  }

  /**
   * Складывает два значения, приводя к меньшему размеру.
   *
   * @param other Второе слагаемое.
   */
  add(other: DimensionalNumber): DimensionalNumber {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    return new DimensionalNumber({ base: a + b, size });
  }

  /**
   * Вычитает значение, приводя к меньшему размеру.
   *
   * @param other Вычитаемое.
   */
  subtract(other: DimensionalNumber): DimensionalNumber {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    return new DimensionalNumber({ base: a - b, size });
  }

  /**
   * Умножает базу, размер не меняет.
   *
   * @param multiplier Множитель базы.
   */
  multiply(multiplier: number): DimensionalNumber {
    return new DimensionalNumber({ base: this.value.base * multiplier, size: this.value.size });
  }

  /**
   * Сравнивает базу и размер без нормализации.
   *
   * @param other Второе значение.
   */
  equalsStrict(other: DimensionalNumber): boolean {
    return this.value.base === other.value.base && this.value.size === other.value.size;
  }

  /**
   * Сравнивает численное равенство после приведения к общему размеру.
   *
   * @param other Второе значение.
   */
  equals(other: DimensionalNumber): boolean {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    return a === b;
  }

  /**
   * Сравнивает значения: −1, 0 или 1.
   *
   * @param other Второе значение.
   */
  compare(other: DimensionalNumber): number {
    const size = Math.min(this.value.size, other.value.size);
    const a = this.value.base * Math.pow(2, this.value.size - size);
    const b = other.value.base * Math.pow(2, other.value.size - size);

    if (a < b) return -1;
    if (a > b) return 1;

    return 0;
  }

  /**
   * Целочисленно делит на другое размерное число.
   *
   * @param divisor Делитель.
   */
  divideFloor(divisor: DimensionalNumber): number {
    if (divisor.value.base === 0) throw new Error('Деление на нулевое размерное значение');

    const commonSize = Math.min(this.value.size, divisor.value.size);
    const dividend = BigInt(this.value.base) * 2n ** BigInt(this.value.size - commonSize);
    const divisorValue = BigInt(divisor.value.base) * 2n ** BigInt(divisor.value.size - commonSize);

    return Number(dividend / divisorValue);
  }

  /**
   * Форматирует значение для UI: база и стрелка размера.
   */
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

/**
 * Переводит неотрицательное число в надстрочные цифры.
 *
 * @param n Число для отображения размера.
 */
function toSuperscript(n: number): string {
  const digits = superscriptDigits;

  return String(n)
    .split('')
    .map((d) => digits[Number(d)] ?? d)
    .join('');
}

/**
 * Разбирает надстрочные цифры в число.
 *
 * @param text Строка из символов ⁰–⁹.
 */
function fromSuperscript(text: string): number {
  let value = 0;
  for (const char of text) {
    const digit = superscriptDigits.indexOf(char);
    if (digit < 0) return value;
    value = value * 10 + digit;
  }

  return value;
}
