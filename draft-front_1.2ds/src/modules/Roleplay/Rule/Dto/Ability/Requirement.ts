import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

export type Requirement =
  | { type: 'has_ability'; ability_code: string; min_level?: number }
  | { type: 'has_ability_keyword'; keyword_code: string; min_count: number }
  | { type: 'has_keyword'; keyword_code: string }
  | { type: 'min_weapon_mastery'; keyword_code: string; min_level: number }
  | { type: 'characteristic_value'; characteristic_code: string; min: DimensionalNumberValue }
  | { type: 'resource_limit'; resource_code: string; min?: DimensionalNumberValue | number }
  | {
      type: 'current_speed';
      axis: 'horizontal' | 'vertical';
      direction: 'front' | 'flank' | 'rear' | 'up' | 'down';
      min_steps_per_action_point: number;
    }
  | { type: 'and'; children: Requirement[] }
  | { type: 'or'; children: Requirement[] };
