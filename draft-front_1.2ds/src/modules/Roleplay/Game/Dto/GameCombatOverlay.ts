import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

/**
 * Transient Game state for one runtime entity.
 * Character/NPC sheet sections are authoritative in actual storage.
 */
export interface GameCombatOverlay {
  gameId: number;
  entityKey: CombatEntityKey;
  kind: 'character' | 'npc';
  updatedAt: string;
  /** Потрачен ли жетон концентрации с конца предыдущего своего хода. */
  concentrationUsedInCycle?: boolean;
  /** Была ли перевязка этой цели в текущей сессии боя. */
  woundBandagedOnce?: boolean;
}
