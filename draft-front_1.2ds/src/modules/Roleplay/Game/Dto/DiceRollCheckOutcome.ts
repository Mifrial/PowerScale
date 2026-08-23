import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Исход слоя проверки на броске (чат: простая проверка vs 0). */
export interface DiceRollCheckOutcome {
  check_code: string;
  difficulty: DimensionalNumberValue;
  passed: boolean;
  rating: number;
}
