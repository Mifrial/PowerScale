import type { LogPage } from '@/modules/Core/Logger/Dto/LogPage';
import type { LogQuery } from '@/modules/Core/Logger/Dto/LogQuery';
import type { LogEntry } from '@/modules/Core/Logger/Dto/LogEntry';

export interface ILoggerApi {
  findPage(query: LogQuery, signal?: AbortSignal): Promise<LogPage>;
  get(id: number, signal?: AbortSignal): Promise<LogEntry>;
}
