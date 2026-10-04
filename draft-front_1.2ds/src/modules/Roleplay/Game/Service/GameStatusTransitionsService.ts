import type { GameStatus } from '@/modules/Roleplay/Game/Enum/GameStatus';

import { GAME_STARTABLE_STATUSES } from '@/modules/Roleplay/Game/Constant/Game/GAME_STARTABLE_STATUSES';

export class GameStatusTransitionsService {
  canStartGame(status: GameStatus): boolean {
    return GAME_STARTABLE_STATUSES.includes(status);
  }

  /** Сессию можно остановить, только когда она запущена. Статус кампании на это не влияет. */
  canStopSession(sessionRunning: boolean): boolean {
    return sessionRunning;
  }
}
