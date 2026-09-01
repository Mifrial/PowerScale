import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

/** Конверт ответа action: успех с данными или ошибка. */
export interface ActionResponse<T = unknown> {
  success: boolean;
  data: T | null;
  error?: ActionError;
}
