import type { GameBattleState } from '@/modules/Roleplay/Game/Dto/GameBattleState';
import type { GameSessionState } from '@/modules/Roleplay/Game/Dto/GameSessionState';

export interface GameStateSnapshot {
  gameId: number;
  session: GameSessionState | null;
  battle: GameBattleState | null;
}
