/** Бонус или штраф к лимиту ресурса. */
export interface ResourceLimitBonus {
  sourceRuleCode: string | null;
  sourceLabel: string | null;
  delta: number;
}
