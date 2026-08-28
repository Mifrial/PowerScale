import type { HitCheckRoll } from '@/modules/Roleplay/Game/Dto/HitCheckRoll';

export interface SimultaneousHitRoll {
  attackers: HitCheckRoll['attacker'][];
  defender: HitCheckRoll['defender'];
}
