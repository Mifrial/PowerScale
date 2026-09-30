import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface GameCleanupSummary {
  gameId: number;
  sessionId: string;
  battleId: string | null;
  reason: 'battle_ended' | 'session_stopped' | 'character_returned';
  cancelledProcessIds: string[];
  cancelledProcessEntityKeys: CombatEntityKey[];
  cancelledOfferIds: number[];
  clearedPendingEffectEntityKeys: CombatEntityKey[];
  clearedCommittedActionEntityKeys: CombatEntityKey[];
  clearedActiveSpellIds: string[];
  clearedMovementEntityKeys: CombatEntityKey[];
  clearedQuickRollEntityKeys: CombatEntityKey[];
  initiativeCleared: boolean;
  clearedMarkerKeys: string[];
}
