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
    };
