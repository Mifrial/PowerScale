export type AbilityCost =
  | { kind: 'array'; levels_cost: number[] }
  | { kind: 'progression'; max_level: number; base_cost: number; step: number }
  | { kind: 'automatic' }
