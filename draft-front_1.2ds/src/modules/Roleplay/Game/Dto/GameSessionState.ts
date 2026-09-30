import type { CombatEntityKey } from '@/modules/Roleplay/Game/Dto/CombatEntityKey';

export interface GameSessionState {
  gameId: number;
  sessionId: string;
  status: 'active';
  sessionStateVersion: number;
  participantEntityKeys: CombatEntityKey[];
  activeBattleId: string | null;
  sessionMarkers: Record<string, string | number | boolean | null>;
  startedAt: string;
  updatedAt: string;
}
