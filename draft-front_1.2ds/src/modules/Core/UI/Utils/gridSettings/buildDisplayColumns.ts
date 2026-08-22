import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';
import type { GridSettings } from '@/modules/Core/UI/Dto/Grid/GridSettings';

export function buildDisplayColumns(allColumns: ColumnDefinition[], settings: GridSettings | null): ColumnDefinition[] {
  if (!settings) return allColumns;

  const savedMap = new Map(settings.columns.map((c) => [c.key, c]));

  const ordered: ColumnDefinition[] = [];
  const seen = new Set<string>();

  for (const sc of settings.columns) {
    const col = allColumns.find((c) => c.key === sc.key);
    if (col && sc.visible) {
      ordered.push(col);
      seen.add(col.key);
    }
  }

  for (const col of allColumns) {
    if (!seen.has(col.key)) {
      const saved = savedMap.get(col.key);
      if (saved && saved.visible) {
        ordered.push(col);
      }
      seen.add(col.key);
    }
  }

  if (!ordered.length) return allColumns;

  return ordered;
}
