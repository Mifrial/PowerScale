export interface AccumulatedStateEffects {
  characteristicDeltas: Map<string, number>;
  resourceLimitModify: Map<string, number>;
  resourceLimitSet: Map<string, number>;
  /** Производные характеристики, на которые прямо нацелен эффект состояния. */
  derivedTargets: Set<string>;
}
