import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

/** Ошибка Game API с кодом action. */
export class GameApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details: unknown = null,
  ) {
    super(message);
    this.name = 'GameApiError';
  }

  static fromActionError(error: ActionError): GameApiError {
    return new GameApiError(error.code, error.message, error.details ?? null);
  }
}
