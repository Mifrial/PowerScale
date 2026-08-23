/**
 * Помеха/преимущество от источника: delta > 0 — преимущества, < 0 — помехи.
 * Агрегация как у модификаторов характеристик (макс+ / мин− от одного source_code).
 */
export interface AdvantageModifier {
  source_code: string | null;
  source_label: string | null;
  delta: number;
}
