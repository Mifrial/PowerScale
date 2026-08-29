import type { AttackResistanceLayer } from '@/modules/Roleplay/Game/Dto/AttackCalcPayload';
export interface ApplyAttackDamageResult {
  remainingSr: number;
  resistance: number;
  raw: number;
  hpDamage: number;
  exhaustion: number;
  remainingHpDamage: number;
  stun: number | null;
  wound: number | null;
  knockout: boolean;
  cuttingWound: number | null;
  layers: AttackResistanceLayer[];
}
