import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Общие поля предмета (не подтип-специфичные). */
export interface ItemSpecBase {
  category: 'money' | 'equipment' | 'other';
  cost_gm: number | null;
  weight: DimensionalNumberValue | null;
  special_rule_codes: string[];
  innate?: boolean;
  /** Группа предмета (варианты одного предмета: тиры артефактов по силе кристалла). */
  group_code?: string | null;
  /** Семья оружия (правило weapon_family): группа владения «Владения оружием». У оружия/щитов. */
  proficiency_family_code?: string | null;
}
