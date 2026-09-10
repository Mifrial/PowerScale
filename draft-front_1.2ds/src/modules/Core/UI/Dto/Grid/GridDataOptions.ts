import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { GridPage } from '@/modules/Core/UI/Dto/Grid/GridPage';
import type { GridQuery } from '@/modules/Core/UI/Dto/Grid/GridQuery';
import type { GridDataMode } from '@/modules/Core/UI/Enum/GridDataMode';

export type GridDataOptions<T extends Record<string, unknown>> =
  | {
      mode: Extract<GridDataMode, 'client'>;
      getItems: () => T[];
      fields: FilterField[];
      columns: ColumnDefinition[];
      searchFields?: string[];
    }
  | {
      mode: Extract<GridDataMode, 'server'>;
      loadPage: (query: GridQuery, signal: AbortSignal) => Promise<GridPage<T>>;
    };
