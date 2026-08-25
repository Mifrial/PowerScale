/** Помеха/преимущество предмета на проверки выбранных характеристик (не на попадание, если не задано). */
export interface ItemCheckAdvantage {
  delta: number;
  characteristic_codes: string[];
  includes_hit?: boolean;
}
