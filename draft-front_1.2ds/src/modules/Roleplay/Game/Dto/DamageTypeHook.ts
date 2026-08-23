export type DamageTypeHookPhase = 'injury' | 'apply' | 'attack';

export interface DamageTypeHook {
  ruleCode: string;
  mechanicCode: string;
  version: string;
  phase: DamageTypeHookPhase;
  /** Колющий: доп. кубы = floor(РУ / divisor). */
  extraDiceFromSrDivisor?: number;
  /** Рубящий: сдвиг порога сброса граней (обычно −1). */
  efficiencyDelta?: number;
  /** Истощение → рана: множитель силы. */
  woundMultiplier?: number;
}
