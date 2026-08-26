export interface AccumulatedStateEffects {
  characteristicDeltas: Map<string, number>;
  resourceLimitModify: Map<string, number>;
  resourceLimitSet: Map<string, number>;
}
