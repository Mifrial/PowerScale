/** Бонус или штраф к лимиту ресурса. */
export interface ResourceLimitBonus {
  sourceRuleId: string | null;
  sourceLabel: string | null;
  delta: number;
}
