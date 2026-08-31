/** Бонус/штраф к лимиту ресурса в представлении карточки. */
export interface ResourceLimitOverview {
  source: string;
  sourceRuleCode: string | null;
  sourceHref: string | null;
  delta: number;
}
