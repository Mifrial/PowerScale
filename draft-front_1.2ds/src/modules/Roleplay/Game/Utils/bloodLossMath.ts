export function reservedExhaustion(bloodLoss: number): number {
  return Math.floor(Math.max(0, bloodLoss) / 10);
}

export interface BloodLossGainResult {
  bloodLoss: number;
  exhaustion: number;
  addedExhaustion: number;
  reserved: number;
}

/** Прирост кровопотери: +⌊Δ/10⌋ к истощению, минимум = reserved. */
export function applyBloodLossGain(oldBlood: number, delta: number, exhaustion: number): BloodLossGainResult {
  const nextBlood = Math.max(0, oldBlood + Math.max(0, delta));
  const add = reservedExhaustion(nextBlood) - reservedExhaustion(oldBlood);
  const reserved = reservedExhaustion(nextBlood);
  const nextExhaustion = Math.max(reserved, exhaustion + Math.max(0, add));

  return {
    bloodLoss: nextBlood,
    exhaustion: nextExhaustion,
    addedExhaustion: nextExhaustion - exhaustion,
    reserved,
  };
}

/** Сложность увечья от шкалы крови; null если reserved < 4. */
export function bloodLossInjuryDifficulty(reserved: number): number | null {
  if (reserved < 4) return null;

  return 2 ** (reserved - 4);
}
