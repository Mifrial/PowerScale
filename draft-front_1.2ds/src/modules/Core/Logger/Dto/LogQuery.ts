import type { LogLevel } from '@/modules/Core/Logger/Enum/LogLevel';
import type { LogTextFilterMode } from '@/modules/Core/Logger/Enum/LogTextFilterMode';

export interface LogQuery {
  limit: number;
  offset: number;
  level?: LogLevel;
  source?: string;
  sourceMode?: LogTextFilterMode;
  errorCode?: string;
  errorCodeMode?: LogTextFilterMode;
  from?: number;
  to?: number;
}
