import type { DerivedOperator } from '@/modules/Roleplay/Rule/Enum/DerivedOperator';

export interface ParsedDerivedFormula {
  operator: DerivedOperator;
  codes: [string, string];
}
