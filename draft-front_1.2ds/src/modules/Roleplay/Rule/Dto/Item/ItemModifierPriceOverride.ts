/** Частичный override цены для `ItemModifierPrice.by_keyword`. */
export interface ItemModifierPriceOverride {
  factor?: number | null;
  add_gm?: number | null;
  add_gm_per_100g?: number | null;
  min_final_gm?: number | null;
}
