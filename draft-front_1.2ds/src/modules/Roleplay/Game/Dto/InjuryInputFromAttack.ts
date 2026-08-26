import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';
export interface InjuryInputFromAttack {
  hpDamage: number;
  cuttingWound: number | null;
  woundFromHit: number | null;
  overlayExhaustion: number;
  endurance: number;
  remainingSr: number;
  damageTypeCode: string | null;
  actorKey: CombatEntityKey;
}
