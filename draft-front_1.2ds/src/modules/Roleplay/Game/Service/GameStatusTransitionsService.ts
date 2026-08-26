import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

import { GAME_STARTABLE_STATUSES } from '@/modules/Roleplay/Game/Constant/Game/GAME_STARTABLE_STATUSES';
import { GAME_STOPPABLE_STATUSES } from '@/modules/Roleplay/Game/Constant/Game/GAME_STOPPABLE_STATUSES';
export class GameStatusTransitionsService {
  canStartGame(status: GameStatus): boolean {
    return GAME_STARTABLE_STATUSES.includes(status);
  }

  /** Сессию можно остановить, только когда она активна (playing). */
  canStopSession(status: GameStatus): boolean {
    return GAME_STOPPABLE_STATUSES.includes(status);
  }
}
