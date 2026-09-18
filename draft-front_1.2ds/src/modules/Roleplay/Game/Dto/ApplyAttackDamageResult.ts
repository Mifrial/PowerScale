import type { AttackResistanceLayer } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';
export interface ApplyAttackDamageResult {
  remainingSr: number;
  /** РУ, которым умножали повреждения (после капа типа). */
  appliedSr: number;
  /** Потолок множителя РУ типа урона; null — без капа. */
  srCap: number | null;
  /** Сумма учтённых слоёв до пробития (то, что видно в отчёте). */
  resistance: number;
  penetration: number;
  dodgeSoak: number;
  durabilityShave: number;
  raw: number;
  hpDamage: number;
  exhaustion: number;
  remainingHpDamage: number;
  stun: number | null;
  shock: number | null;
  wound: number | null;
  knockout: boolean;
  cuttingWound: number | null;
  layers: AttackResistanceLayer[];
}
