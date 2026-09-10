import type { LogEntry } from '@/modules/Core/Logger/Dto/LogEntry';

export interface LogPage {
  items: LogEntry[];
  total: number;
}
