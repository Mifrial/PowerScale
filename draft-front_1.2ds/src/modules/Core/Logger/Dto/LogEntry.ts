import type { LogLevel } from '@/modules/Core/Logger/Enum/LogLevel';

export interface LogEntry {
  id: number;
  createdAt: number;
  level: LogLevel;
  message: string;
  source: string | null;
  userId: number | null;
  exceptionClass: string | null;
  errorCode: string | null;
  context: Record<string, string | number | boolean> | null;
}
