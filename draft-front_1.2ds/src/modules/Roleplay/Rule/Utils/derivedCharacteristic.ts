import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';

export type DerivedOperator = 'min' | 'max';

export interface ParsedDerivedFormula {
  operator: DerivedOperator;
  codes: [string, string];
}

/** Разбирает формулу производной характеристики вида `min(a, b)` / `max(a, b)`. */
export function parseDerivedFormula(formula: string): ParsedDerivedFormula | null {
  const match = formula.trim().match(/^(min|max)\(\s*([^,]+)\s*,\s*([^)]+)\)$/i);
  if (!match) return null;

  return { operator: match[1].toLowerCase() as DerivedOperator, codes: [match[2].trim(), match[3].trim()] };
}

/** Значение производной характеристики как min/max значений баз (по значению, без округления). */
export function evaluateDerivedValue(
  formula: string | ParsedDerivedFormula,
  valueOf: (code: string) => DimensionalNumberValue | undefined,
): DimensionalNumberValue | null {
  const parsed = typeof formula === 'string' ? parseDerivedFormula(formula) : formula;
  if (!parsed) return null;

  const [a, b] = parsed.codes;
  const av = valueOf(a);
  const bv = valueOf(b);
  if (av === undefined || bv === undefined) return null;

  if (parsed.operator === 'min') {
    return new DimensionalNumber(av).compare(new DimensionalNumber(bv)) <= 0 ? av : bv;
  }

  return new DimensionalNumber(av).compare(new DimensionalNumber(bv)) >= 0 ? av : bv;
}
