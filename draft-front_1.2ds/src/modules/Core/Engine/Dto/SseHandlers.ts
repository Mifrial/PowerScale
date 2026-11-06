import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

/** Колбэки SSE: кадр события, отказ канала (не AUTH_REQUIRED) и штатное закрытие потока. */
export interface SseHandlers {
  onEvent: (eventName: string, payload: unknown) => void;
  onError: (error: ActionError) => void;
  /** Сервер закрыл поток сам (потолок жизни), а не ошибка канала. */
  onEnd?: () => void;
}
