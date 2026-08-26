/**
 * Дельты успехов механики «подсчёта броска»: «1» начисляет `oneDelta` доп. успехов,
 * грань куба списывает `faceDelta`. По умолчанию +1 / −1 (как правило «6 и 1»).
 */
export interface RollScoreAdjustPayload {
  oneDelta?: number;
  faceDelta?: number;
}
