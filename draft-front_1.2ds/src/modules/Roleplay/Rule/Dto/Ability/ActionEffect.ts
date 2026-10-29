import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

type AttackComponent = 'strike' | 'throw' | 'shoot';

type AttackScope = {
  components: AttackComponent[];
  hit_count: number | 'all';
};

type ModifierOutcomeEffect =
  | {
      type: 'self_damage';
      size_delta: number;
      damage_type_code: string;
      /** Игнорировать защиту и сопротивление. */
      ignore_defense: boolean;
    }
  | {
      type: 'skip_after_action';
    };

export type ActionEffect =
  | {
      type: 'current_action_attack_accuracy';
      delta: number;
      scope: AttackScope;
    }
  | {
      type: 'current_action_attack_reach';
      /** Доля шага атакующего (0.5 — полшага), не ипари. */
      step_fraction: number;
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
      /** Источник; нет — отдельный уникальный. */
      source_code?: string;
    }
  | {
      type: 'current_action_attack_characteristic_from_success_rating';
      floor_div: number;
      cap: number;
      scope: AttackScope;
      /** Источник; нет — отдельный уникальный. */
      source_code?: string;
    }
  | {
      type: 'current_action_check_modifier';
      check_codes: string[];
      delta: number;
      source_code?: string;
    }
  | {
      type: 'next_action_attack_cost';
      resource_code: string;
      delta: number;
    }
  | {
      type: 'next_action_attack_accuracy';
      delta: number;
      same_target: boolean;
      targetKey?: string;
      scope: AttackScope;
    }
  | {
      type: 'current_action_attack_dodge_soak';
      size_delta: number;
      ignore_at_sr?: number;
      scope: AttackScope;
    }
  | {
      type: 'next_action_attack_dodge_soak_from_reaction';
      max_total_action_cost?: number;
      scope: AttackScope;
    }
  | {
      type: 'after_action_until_resource_spent_check_modifier';
      resource_code: string;
      amount: number;
      check_codes: string[];
      delta: number;
      source_code?: string;
      /** attacker_hit — не на защиту; нет или any — как Размашистый. */
      applies_to?: 'attacker_hit' | 'any';
    }
  | {
      type: 'current_action_roll_score_adjust';
      oneDelta: number;
      faceDelta: number;
      scope: AttackScope;
    }
  | {
      type: 'current_action_roll_face_remap';
      from: number;
      to: number;
      scope: AttackScope;
    }
  | {
      type: 'next_action_attack_score_adjust';
      oneDelta: number;
      faceDelta: number;
      same_target: boolean;
      targetKey?: string;
    }
  | {
      type: 'current_action_durability_shave';
      short_extra_on_first_one: boolean;
      scope: AttackScope;
      damage_type_codes?: string[];
    }
  | {
      type: 'require_previous_strike';
      min_sr: number;
      not_kind: 'lethal';
      same_target: boolean;
    }
  | {
      type: 'require_previous_attack';
      same_target: boolean;
      /** Все удары прошлого снимка должны были нанести повреждения. */
      all_damaged?: boolean;
      /** Само действие — один удар. */
      single_strike?: boolean;
    }
  | {
      type: 'attack_sr_from_previous';
      floor_div: number;
      cap: 'double_this';
    }
  | {
      type: 'last_strike_snapshot';
      kind: 'lethal' | 'other';
      hits: { targetKey: string; attackSr: number; reaction?: string; damaged?: boolean }[];
    }
  | {
      type: 'prepared_defense_counter';
      targetKey: string;
      reaction: string;
    }
  | {
      type: 'apply_state';
      state_code: string;
      amount?: DimensionalNumberValue | number;
    }
  | {
      /** Модификатор действия: момент, область, проверка и последствия. */
      type: 'action_modifier';
      trigger: 'after_strike';
      scope: AttackScope;
      /** Нет проверки — последствия применяются сразу. */
      check?: { code: string; difficulty: number };
      effects: ModifierOutcomeEffect[];
    }
  | {
      type: 'next_spell_cast_difficulty';
      delta: number;
      source_code?: string;
      max_total_action_cost: number;
    };
