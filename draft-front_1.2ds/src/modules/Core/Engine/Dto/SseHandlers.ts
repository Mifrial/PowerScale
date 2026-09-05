import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

/** Колбэки SSE: кадр события и отказ канала (не AUTH_REQUIRED). */
export interface SseHandlers {
  onEvent: (eventName: string, payload: unknown) => void;
  onError: (error: ActionError) => void;
}
