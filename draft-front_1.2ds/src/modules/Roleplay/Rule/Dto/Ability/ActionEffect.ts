import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

type AttackComponent = 'strike' | 'throw' | 'shoot';

type AttackScope = {
  components: AttackComponent[];
  hit_count: number | 'all';
};

export type ActionEffect =
  | {
      type: 'current_action_attack_accuracy';
      delta: number;
      scope: AttackScope;
    }
  | {
      type: 'current_action_attack_characteristic_modifier';
      delta: number;
      scope: AttackScope;
      /** Минимум слотов рук на оружии; нет — без фильтра. */
      min_occupy_hands?: number;
      /** Типы урона профиля; нет или пусто — любой. */
      damage_type_codes?: string[];
    }
  | {
      type: 'current_action_attack_characteristic_from_success_rating';
      floor_div: number;
      cap: number;
      scope: AttackScope;
    }
  | {
      type: 'current_action_check_modifier';
      check_codes: string[];
      delta: number;
    }
  | {
      type: 'next_action_attack_cost';
      resource_code: string;
      delta: number;
    }
  | {
      type: 'next_action_attack_target_characteristic_modifier';
      check_code: string;
      characteristic_code: string;
      delta: number;
      min?: number;
      max_total_action_cost?: number;
      scope: AttackScope;
    }
  | {
      type: 'after_action_until_resource_spent_check_modifier';
      resource_code: string;
      amount: number;
      check_codes: string[];
      delta: number;
    }
  | {
      type: 'apply_state';
      state_code: string;
      amount?: DimensionalNumberValue | number;
    }
  | {
      /** Опция после удара: проверка, себе урон, снять pending родителя. */
      type: 'optional_after_strike_check';
      check_code: string;
      difficulty: number;
      skip_parent_pending: boolean;
      self_damage: {
        size_delta: number;
        damage_type_code: string;
        internal: boolean;
      };
    };
