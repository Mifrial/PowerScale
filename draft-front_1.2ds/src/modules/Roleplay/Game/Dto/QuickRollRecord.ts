import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Кандидат макроса быстрых бросков (CD-8): характеристика, боевой стат или оружие. */
export interface QuickRollRecord {
  ruleId: string;
  /** Имя броска: подпись характеристики/оружия; для статов секции — «Ближний бой»/«Дальний бой». */
  name: string;
  value: DimensionalNumberValue;
  valueLabel: string;
}
