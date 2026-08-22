import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export interface RaceAbilityRef {
  ability_code: string;
  /** true = бесплатная/авто, false = доступная (расовая/видовая). */
  automatic: boolean;
  /**
   * Значения параметров способности: для доступной — потолок (макс. X),
   * для automatic — значение, с которым раса даёт способность.
   */
  parameters?: Record<string, DimensionalNumberValue>;
}
