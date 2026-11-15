import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';
import { LORE_ERROR_MESSAGES } from '@/modules/Roleplay/Lore/Constant/LORE_ERROR_MESSAGES';

/** Отказ транспорта кодекса с кодом конверта и русской подсказкой. */
export class LoreApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details: unknown = null,
  ) {
    super(message);
    this.name = 'LoreApiError';
  }

  static fromActionError(error: ActionError): LoreApiError {
    const base = LORE_ERROR_MESSAGES[error.code];
    const message = base ? `${base}: ${error.message}` : error.message;

    return new LoreApiError(error.code, message, error.details ?? null);
  }
}
