import { describe, it, expect } from 'vitest';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import type { DimensionalNumberBaseRange } from '@/modules/Core/Engine/Dto/DimensionalNumberBaseRange';

const RANGE: DimensionalNumberBaseRange = { min: 3, max: 5 };

const dn = (base: number, size: number) => DimensionalNumber.from({ base, size });

describe('DimensionalNumber.toNumber', () => {
  it('{3|-1} = 1 (округление вниз)', () => {
    expect(dn(3, -1).toNumber()).toBe(1);
  });
  it('{4|0} = 4', () => {
    expect(dn(4, 0).toNumber()).toBe(4);
  });
  it('{3|+1} = 6', () => {
    expect(dn(3, 1).toNumber()).toBe(6);
  });
  it('{3|+2} = 12', () => {
    expect(dn(3, 2).toNumber()).toBe(12);
  });
});

describe('DimensionalNumber.modify (без диапазона)', () => {
  it('сдвиг базы без границ', () => {
    expect(dn(4, 0).modify(1).value).toEqual({ base: 5, size: 0 });
    expect(dn(3, 0).modify(-1).value).toEqual({ base: 2, size: 0 });
  });
});

describe('DimensionalNumber.modify (диапазон характеристик)', () => {
  it('{4|0} + 1 = {5|0}', () => {
    expect(dn(4, 0).modify(1, RANGE).value).toEqual({ base: 5, size: 0 });
  });
  it('{5|0} + 1 = {3|+1} (переполнение → размер)', () => {
    expect(dn(5, 0).modify(1, RANGE).value).toEqual({ base: 3, size: 1 });
  });
  it('{3|0} - 1 = {5|-1}', () => {
    expect(dn(3, 0).modify(-1, RANGE).value).toEqual({ base: 5, size: -1 });
  });
  it('{4|-1} + 4 = {5|0}', () => {
    expect(dn(4, -1).modify(4, RANGE).value).toEqual({ base: 5, size: 0 });
  });
  it('{5|0} + 6 = {5|+2}', () => {
    expect(dn(5, 0).modify(6, RANGE).value).toEqual({ base: 5, size: 2 });
  });
  it('{3|0} - 7 = {5|-3}', () => {
    expect(dn(3, 0).modify(-7, RANGE).value).toEqual({ base: 5, size: -3 });
  });
});

describe('DimensionalNumber.add', () => {
  it('{4|0} + {2|0} = {6|0}', () => {
    expect(dn(4, 0).add(dn(2, 0)).value).toEqual({ base: 6, size: 0 });
  });
  it('{1|+1} + {5|-1} = {9|-1} (выравнивание по меньшему размеру)', () => {
    expect(dn(1, 1).add(dn(5, -1)).value).toEqual({ base: 9, size: -1 });
  });
});

describe('DimensionalNumber.subtract', () => {
  it('{5|-1} - {1|+1} = {1|-1}', () => {
    expect(dn(5, -1).subtract(dn(1, 1)).value).toEqual({ base: 1, size: -1 });
  });
  it('отрицательный результат возвращается как есть', () => {
    expect(dn(1, -1).subtract(dn(5, -1)).value).toEqual({ base: -4, size: -1 });
  });
});

describe('DimensionalNumber.equalsStrict', () => {
  it('равенство по base и size', () => {
    expect(dn(4, 0).equalsStrict(dn(4, 0))).toBe(true);
    expect(dn(4, 0).equalsStrict(dn(4, 1))).toBe(false);
    expect(dn(4, 0).equalsStrict(dn(2, 1))).toBe(false);
  });
});

describe('DimensionalNumber.equals (нестрогое, по значению)', () => {
  it('{4|0} == {2|1} (выравнивание по меньшему размеру)', () => {
    expect(dn(4, 0).equals(dn(2, 1))).toBe(true);
    expect(dn(2, 1).equals(dn(4, 0))).toBe(true);
  });
  it('{3|0} != {5|-1} (3 vs 2.5)', () => {
    expect(dn(3, 0).equals(dn(5, -1))).toBe(false);
  });
  it('{3|0} != {2|0}', () => {
    expect(dn(3, 0).equals(dn(2, 0))).toBe(false);
  });
});

describe('DimensionalNumber.compare', () => {
  it('{3|0} < {2|1} (3 < 4)', () => {
    expect(dn(3, 0).compare(dn(2, 1))).toBe(-1);
  });
  it('{5|-1} > {2|0} (2.5 > 2)', () => {
    expect(dn(5, -1).compare(dn(2, 0))).toBe(1);
  });
  it('{4|0} == {2|1} → 0', () => {
    expect(dn(4, 0).compare(dn(2, 1))).toBe(0);
  });
  it('симметричность', () => {
    expect(dn(2, 1).compare(dn(3, 0))).toBe(1);
  });
});

describe('DimensionalNumber.toString', () => {
  it('формат отображения', () => {
    expect(dn(3, 0).toString()).toBe('3');
    expect(dn(3, 1).toString()).toBe('3↑');
    expect(dn(3, -1).toString()).toBe('3↓');
    expect(dn(3, 2).toString()).toBe('3↑²');
    expect(dn(3, -2).toString()).toBe('3↓²');
  });
});

describe('DimensionalNumber.withSize', () => {
  it('вниз по размеру умножает базу на 2', () => {
    expect(dn(5, 0).withSize(-1).value).toEqual({ base: 10, size: -1 });
  });
  it('вверх по размеру делит базу на 2 с округлением вниз', () => {
    expect(dn(4, -2).withSize(-1).value).toEqual({ base: 2, size: -1 });
    expect(dn(3, -2).withSize(-1).value).toEqual({ base: 1, size: -1 });
  });
  it('{0|-n} при подъёме размера остаётся нулём', () => {
    expect(dn(0, -5).withSize(-1).value).toEqual({ base: 0, size: -1 });
  });
});

describe('DimensionalNumber.foldNegativeBase', () => {
  it('−x успехов размера n → 0 успехов размера n−x', () => {
    expect(dn(-2, 0).foldNegativeBase().value).toEqual({ base: 0, size: -2 });
    expect(dn(-3, 1).foldNegativeBase().value).toEqual({ base: 0, size: -2 });
  });
  it('неотрицательную базу не трогает', () => {
    expect(dn(0, -1).foldNegativeBase().value).toEqual({ base: 0, size: -1 });
    expect(dn(4, 0).foldNegativeBase().value).toEqual({ base: 4, size: 0 });
  });
});

describe('DimensionalNumber.parse', () => {
  it('разбирает строковое представление (формат toString)', () => {
    expect(DimensionalNumber.parse('3')).toEqual({ base: 3, size: 0 });
    expect(DimensionalNumber.parse('3↑')).toEqual({ base: 3, size: 1 });
    expect(DimensionalNumber.parse('3↓')).toEqual({ base: 3, size: -1 });
    expect(DimensionalNumber.parse('4↑²')).toEqual({ base: 4, size: 2 });
    expect(DimensionalNumber.parse('5↓²')).toEqual({ base: 5, size: -2 });
  });

  it('некорректная строка → {0, 0}', () => {
    expect(DimensionalNumber.parse('')).toEqual({ base: 0, size: 0 });
    expect(DimensionalNumber.parse('abc')).toEqual({ base: 0, size: 0 });
  });
});

describe('DimensionalNumber.from', () => {
  it('round-trip', () => {
    expect(DimensionalNumber.from({ base: 3, size: 2 }).value).toEqual({ base: 3, size: 2 });
  });
});
