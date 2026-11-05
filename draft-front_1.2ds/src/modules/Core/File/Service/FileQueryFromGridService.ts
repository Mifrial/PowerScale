import type { DateTimeFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/DateTimeFilterValue';
import type { NumberFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/NumberFilterValue';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';
import type { FileQuery } from '@/modules/Core/File/Dto/FileQuery';

/** Маппит GridQuery файлов в плоский HTTP-контракт file.findPage. */
export class FileQueryFromGridService {
  toQuery(query: GridQuery): FileQuery {
    const httpQuery: FileQuery = {
      limit: query.perPage,
      offset: (query.page - 1) * query.perPage,
    };
    const mime = this.filterText(query.filters.mime)?.trim();
    if (mime) httpQuery.mime = mime;
    const ownerId = this.filterNumber(query.filters.ownerId);
    if (ownerId !== undefined) httpQuery.ownerId = ownerId;
    const isArchived = this.filterBoolean(query.filters.isArchived);
    if (isArchived !== undefined) httpQuery.isArchived = isArchived;
    const isDerived = this.filterBoolean(query.filters.isDerived);
    if (isDerived !== undefined) httpQuery.isDerived = isDerived;
    this.putCreatedAt(httpQuery, query.filters.createdAt);

    return httpQuery;
  }

  private putCreatedAt(httpQuery: FileQuery, value: FilterValue | undefined): void {
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

  private filterNumber(value: FilterValue | undefined): number | undefined {
    if (typeof value === 'number') return value;
    if (value && typeof value === 'object' && 'mode' in value && value.mode === 'equals') {
      const numberValue = (value as NumberFilterValue & { value?: unknown }).value;

      return typeof numberValue === 'number' ? numberValue : undefined;
    }

    return undefined;
  }

  private filterBoolean(value: FilterValue | undefined): boolean | undefined {
    return typeof value === 'boolean' ? value : undefined;
  }

  private filterText(value: FilterValue | undefined): string | undefined {
    if (typeof value === 'string') return value;
    if (value && typeof value === 'object' && 'value' in value && typeof value.value === 'string') {
      return value.value;
    }

    return undefined;
  }
}
