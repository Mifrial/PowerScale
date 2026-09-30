import type { GameAuthoritativeCommandResult } from '@/modules/Roleplay/Game/Dto/GameAuthoritativeCommandResult';

export interface GameCombatCommandRecord {
  commandId: string;
  fingerprint: string;
  sessionId: string;
  battleId: string;
  processId: string | null;
  result: GameAuthoritativeCommandResult;
}
