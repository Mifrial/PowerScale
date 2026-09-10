import type { Engine } from '@/modules/Core/Engine/Service/Engine';
import type { ILoggerApi } from '@/modules/Core/Logger/Interface/ILoggerApi';
import type { LogEntry } from '@/modules/Core/Logger/Dto/LogEntry';
import type { LogPage } from '@/modules/Core/Logger/Dto/LogPage';
import type { LogQuery } from '@/modules/Core/Logger/Dto/LogQuery';

/** HTTP-транспорт logger.findPage / logger.get. */
export class LoggerApi implements ILoggerApi {
  constructor(private readonly engine: Engine) {}

  async findPage(query: LogQuery, signal?: AbortSignal): Promise<LogPage> {
    const res = await this.engine.runAction<LogPage>('logger.findPage', query, signal);
    if (!res.data) {
      return { items: [], total: 0 };
    }

    return res.data;
  }

  async get(id: number, signal?: AbortSignal): Promise<LogEntry> {
    const res = await this.engine.runAction<LogEntry>('logger.get', { id }, signal);
    if (!res.data) throw new Error('Log entry not found');

    return res.data;
  }
}
