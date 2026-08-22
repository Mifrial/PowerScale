/** Бюджеты создания персонажа (из настроек /characters/new или игры). null = лимит не задан. */
export interface CharacterCreationConfig {
  /** Лимит ОС. */
  osTotal: number | null;
  /** Лимит ОР. */
  orTotal: number | null;
  /** Бюджет денег на закупку. */
  moneyBudget: number | null;
}
