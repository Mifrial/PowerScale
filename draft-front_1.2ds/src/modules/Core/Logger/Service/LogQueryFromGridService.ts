import type { DateTimeFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/DateTimeFilterValue';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';
import type { LogLevel } from '@/modules/Core/Logger/Enum/LogLevel';
import type { LogQuery } from '@/modules/Core/Logger/Dto/LogQuery';
import type { LogTextFilterMode } from '@/modules/Core/Logger/Enum/LogTextFilterMode';

/** Маппит GridQuery журнала в плоский HTTP-контракт logger.findPage. */
export class LogQueryFromGridService {
  toQuery(query: GridQuery): LogQuery {
    const httpQuery: LogQuery = {
      limit: query.perPage,
      offset: (query.page - 1) * query.perPage,
    };
    const level = this.filterLevel(query.filters.level);
    if (level) httpQuery.level = level;
    this.putTextFilter(httpQuery, 'source', 'sourceMode', query.filters.source);
    this.putTextFilter(httpQuery, 'errorCode', 'errorCodeMode', query.filters.errorCode);
    this.putCreatedAt(httpQuery, query.filters.createdAt);

    return httpQuery;
  }

  private putTextFilter(
    httpQuery: LogQuery,
    valueKey: 'source' | 'errorCode',
    modeKey: 'sourceMode' | 'errorCodeMode',
    value: FilterValue | undefined,
  ): void {
    const text = this.filterText(value)?.trim();
    if (!text) return;
    httpQuery[valueKey] = text;
    const mode = this.filterTextMode(value);
    if (mode) httpQuery[modeKey] = mode;
  }

  private putCreatedAt(httpQuery: LogQuery, value: FilterValue | undefined): void {
    if (!value || typeof value !== 'object' || !('mode' in value)) return;
    const range = this.dateRange(value as DateTimeFilterValue);
    if (range.from !== undefined) httpQuery.from = range.from;
    if (range.to !== undefined) httpQuery.to = range.to;
  }

  private dateRange(value: DateTimeFilterValue): { from?: number; to?: number } {
    if (value.mode === 'equals') {
      const start = this.localMinuteToUnix(value.value);
      if (start === undefined) return {};

      return { from: start, to: start + 59 };
    }

    if (value.mode === 'from') {
      return { from: this.localMinuteToUnix(value.from) };
    }

    if (value.mode === 'to') {
      return { to: this.localMinuteToUnix(value.to) };
    }

    return {
      from: this.localMinuteToUnix(value.from),
      to: this.localMinuteToUnix(value.to),
    };
  }

  private localMinuteToUnix(local: string | undefined): number | undefined {
    if (!local) return undefined;
    const match = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/.exec(local);
    if (!match) return undefined;
    const year = Number(match[1]);
    const month = Number(match[2]);
    const day = Number(match[3]);
    const hours = Number(match[4]);
    const minutes = Number(match[5]);

    return Math.floor(new Date(year, month - 1, day, hours, minutes, 0).getTime() / 1000);
  }

  private filterLevel(value: FilterValue | undefined): LogLevel | undefined {
    if (value === 'error' || value === 'warning' || value === 'info') return value;

    return undefined;
  }

  private filterText(value: FilterValue | undefined): string | undefined {
    if (typeof value === 'string') return value;
    if (value && typeof value === 'object' && 'value' in value && typeof value.value === 'string') {
      return value.value;
    }

    return undefined;
  }

  private filterTextMode(value: FilterValue | undefined): LogTextFilterMode | undefined {
    if (value && typeof value === 'object' && 'mode' in value) {
      if (value.mode === 'equals' || value.mode === 'contains') return value.mode;
    }

    return undefined;
  }
}
