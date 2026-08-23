/** Структурная операция модификатора предмета. Применяется к клону ItemSpec в applyStack. */
export type ItemModifierOp =
  | { type: 'weight'; factor?: number; add_kg?: number }
  | { type: 'min_strength'; delta: number }
  | { type: 'durability'; delta?: number; add_size?: number }
  | { type: 'block'; factor?: number; add?: number; add_size?: number }
  | { type: 'defense'; factor?: number; add?: number; add_size?: number; min?: number }
  | { type: 'armor_reliability'; set?: number; add?: number }
  | { type: 'max_agility'; delta?: number; add_size?: number }
  | { type: 'strength_penalty'; add?: number; set?: number | null }
  | {
      type: 'action_strength';
      field: 'damage' | 'penetration';
      delta: number;
      profiles?: ('strike' | 'throw' | 'shoot')[];
      damage_type_codes?: string[];
    }
  | { type: 'resistance'; damage_type_code: string; mode: 'add' | 'add_size' | 'max'; value: number }
  | { type: 'keyword'; add?: string[]; remove?: string[] }
  | { type: 'min_action_cost'; min: number }
  | { type: 'magic_conductor'; value: number }
  | { type: 'advantage'; delta: number; source_code: string };
