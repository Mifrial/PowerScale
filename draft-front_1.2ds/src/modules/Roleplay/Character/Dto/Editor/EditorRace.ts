/** Раса в модели редактора. */
export interface EditorRace {
  ruleCode: string | null;
  name: string | null;
  /** Стоимость расы в ОС (отрицательная = даёт ОС). */
  costOs: number;
}
