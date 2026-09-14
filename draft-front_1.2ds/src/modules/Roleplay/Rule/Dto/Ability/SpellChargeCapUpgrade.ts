/** Кап зарядов от изученного улучшения, не чекбокс каста. */
export interface SpellChargeCapUpgrade {
  base: number;
  experience_keyword_code: string;
  steps: { min_experience: number; cap?: number; per_experience?: number }[];
}
