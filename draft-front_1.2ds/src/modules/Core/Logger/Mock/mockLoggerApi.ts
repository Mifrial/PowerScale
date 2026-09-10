import type { ILoggerApi } from '@/modules/Core/Logger/Interface/ILoggerApi';
import type { LogEntry } from '@/modules/Core/Logger/Dto/LogEntry';
import type { LogPage } from '@/modules/Core/Logger/Dto/LogPage';
import type { LogQuery } from '@/modules/Core/Logger/Dto/LogQuery';
import { abortableDelay } from '@/modules/Core/Engine/Mock/abortableDelay';

const catalog: LogEntry[] = [
  {
    id: 3,
    createdAt: 1_700_000_300,
    level: 'error',
    message: 'Unhandled kernel error',
    source: 'user.create',
    userId: 7,
    exceptionClass: 'Mifrial\\Core\\Kernel\\Exception\\KernelException',
    errorCode: 'INTERNAL',
    context: { file: 'Application.php', line: 123 },
  },
  {
    id: 2,
    createdAt: 1_700_000_200,
    level: 'warning',
    message: 'Mail job failed',
    source: 'mail.flush',
    userId: null,
    exceptionClass: null,
    errorCode: 'MAIL_FAIL',
    context: { jobId: 'j1', attempts: 3 },
  },
  {
    id: 1,
    createdAt: 1_700_000_100,
    level: 'info',
    message: 'Agent tick',
    source: 'a%b',
    userId: 1,
    exceptionClass: null,
    errorCode: null,
    context: null,
  },
];

function matchesText(
  haystack: string | null,
  needle: string | undefined,
  mode: 'equals' | 'contains' | undefined,
): boolean {
  if (!needle) return true;
  if (haystack === null) return false;
  if (mode === 'contains') return haystack.includes(needle);

  return haystack === needle;
}

export const mockLoggerApi: ILoggerApi = {
  async findPage(query: LogQuery, signal?: AbortSignal): Promise<LogPage> {
    await abortableDelay(80, signal);
    const matched = catalog.filter((entry) => {
      if (query.level && entry.level !== query.level) return false;
      if (!matchesText(entry.source, query.source, query.sourceMode)) return false;
      if (!matchesText(entry.errorCode, query.errorCode, query.errorCodeMode)) return false;
      if (query.from !== undefined && entry.createdAt < query.from) return false;
      if (query.to !== undefined && entry.createdAt > query.to) return false;

      return true;
    });
    const items = matched.slice(query.offset, query.offset + query.limit);

    return { items, total: matched.length };
  },

  async get(id: number, signal?: AbortSignal): Promise<LogEntry> {
    await abortableDelay(80, signal);
    const entry = catalog.find((row) => row.id === id);
    if (!entry) throw new Error('Log entry not found');

    return entry;
  },
};
