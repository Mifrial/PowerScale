export type SpellComponent =
  | { type: 'verbal' | 'somatic'; note?: string }
  | { type: 'material'; item_code?: string; description?: string }
