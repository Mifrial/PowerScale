import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface GameBattleCommandContext {
  gameId: number;
  sessionId: string;
  battleId: string;
  processId: string | null;
  offerId: number | null;
  expectedSessionStateVersion: number;
  expectedBattleStateVersion: number;
  expectedEntityVersions: Record<CombatEntityKey, number>;
}
