/** Модификатор лимита ресурса в редакторе с разрешённым именем источника. */
export interface EditorResourceBonus {
  delta: number;
  source: string;
  sourceRuleCode: string | null;
}
