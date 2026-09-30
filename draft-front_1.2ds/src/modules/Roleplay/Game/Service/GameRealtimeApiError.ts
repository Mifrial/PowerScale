import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

/** Ошибка realtime API с явным состоянием недоступности будущего backend transport. */
export class GameRealtimeApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
  ) {
    super(message);
    this.name = 'GameRealtimeApiError';
  }

  static fromActionError(error: ActionError): GameRealtimeApiError {
    const code = error.code === 'UNKNOWN_ACTION' ? 'GAME_REALTIME_UNAVAILABLE' : error.code;

    return new GameRealtimeApiError(code, error.message);
  }
}
