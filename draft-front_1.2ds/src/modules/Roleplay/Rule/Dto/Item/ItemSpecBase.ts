import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';

/** Общие поля предмета (не подтип-специфичные). */
export interface ItemSpecBase {
  category: 'money' | 'equipment' | 'other';
  cost_gm: number | null;
  weight: DimensionalNumberValue | null;
  special_rule_codes: string[];
  innate?: boolean;
}
