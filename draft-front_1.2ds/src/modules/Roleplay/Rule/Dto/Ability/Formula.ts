export type Formula =
  | { type: 'fixed'; value: number }
  | { type: 'characteristic'; characteristic_code: string; modifier: number }
  | { type: 'ability_level'; ability_code: string; multiplier?: number; offset?: number }
  | { type: 'dimensional'; base: number; size: number }
