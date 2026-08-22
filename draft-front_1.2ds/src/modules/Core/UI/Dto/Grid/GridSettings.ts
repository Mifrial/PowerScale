import type { ColumnSetting } from '@/modules/Core/UI/Dto/Grid/ColumnSetting';

export interface GridSettings {
  columns: ColumnSetting[];
  widths?: Record<string, number>;
}
