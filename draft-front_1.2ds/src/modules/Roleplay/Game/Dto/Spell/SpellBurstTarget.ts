import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/** Цель арканного взрыва: ключ и дистанция в ипари. */
export interface SpellBurstTarget {
  key: CombatEntityKey;
  distanceIpari: number;
}
