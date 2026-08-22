import type { ActionError } from '@/modules/Core/Engine/Dto/ActionError';

export interface ActionResponse<T = unknown> {
  success: boolean;
  data: T | null;
  error?: ActionError;
}
