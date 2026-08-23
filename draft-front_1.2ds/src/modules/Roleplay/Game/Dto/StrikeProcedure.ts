import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/**
 * Константы процедуры ближнего удара. Алгоритм в коде; ревизия выбирает code@version
 * (игры 4.5 и 5 на одном сайте держат разные хендлеры в одном бандле).
 */
export interface StrikeProcedure {
  code: string;
  version: string;
  /** Игнор: РУ защиты 0↓. */
  ignoreDefense: DimensionalNumberValue;
  /** Подсказка уклона (редактирует защитник). 2 скаляра = {4|-1}. */
  dodgeEfficiency: DimensionalNumberValue;
  /** Нижняя граница эффективности блока (размерное). */
  minBlockEfficiency: DimensionalNumberValue;
}
