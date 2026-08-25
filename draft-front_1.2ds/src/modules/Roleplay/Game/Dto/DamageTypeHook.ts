export type DamageTypeHookPhase = 'injury' | 'apply' | 'attack';

export interface DamageTypeHook {
  ruleCode: string;
  mechanicCode: string;
  version: string;
  phase: DamageTypeHookPhase;
  /** Колющий: доп. сложность = floor(РУ / divisor). */
  extraDiceFromSrDivisor?: number;
  /** Рубящий: дельта эффективности < 0 → помеха на проверку увечья. */
  efficiencyDelta?: number;
  /** Истощение → рана: множитель силы. */
  woundMultiplier?: number;
}
