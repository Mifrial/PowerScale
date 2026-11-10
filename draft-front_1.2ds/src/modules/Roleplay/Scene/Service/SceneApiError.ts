import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';
import { SCENE_ERROR_MESSAGES } from '@/modules/Roleplay/Scene/Constant/Scene/SCENE_ERROR_MESSAGES';

/** Отказ транспорта локаций с кодом конверта и русской подсказкой. */
export class SceneApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details: unknown = null,
  ) {
    super(message);
    this.name = 'SceneApiError';
  }

  static fromActionError(error: ActionError): SceneApiError {
    const base = SCENE_ERROR_MESSAGES[error.code];
    const message = base ? `${base}: ${error.message}` : error.message;

    return new SceneApiError(error.code, message, error.details ?? null);
  }
}
