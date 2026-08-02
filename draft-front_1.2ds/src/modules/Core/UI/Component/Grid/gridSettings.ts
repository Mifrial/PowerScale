import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'

export interface ColumnSetting {
  key: string
  visible: boolean
}

export interface GridSettings {
  columns: ColumnSetting[]
  widths?: Record<string, number>
}

const STORAGE_PREFIX = 'smartgrid_'

function storageKey(gridId: string) {
  return `${STORAGE_PREFIX}${gridId}`
}

export function loadGridSettings(gridId: string): GridSettings | null {
  try {
    const raw = localStorage.getItem(storageKey(gridId))
    if (!raw) return null
    return JSON.parse(raw) as GridSettings
  } catch {
    return null
  }
}

export function saveGridSettings(gridId: string, settings: GridSettings) {
  try {
    localStorage.setItem(storageKey(gridId), JSON.stringify(settings))
  } catch {
    /* localStorage not available */
  }
}

export function buildDisplayColumns(
  allColumns: ColumnDefinition[],
  settings: GridSettings | null,
): ColumnDefinition[] {
  if (!settings) return allColumns

  const savedMap = new Map(settings.columns.map(c => [c.key, c]))

  const ordered: ColumnDefinition[] = []
  const seen = new Set<string>()

  for (const sc of settings.columns) {
    const col = allColumns.find(c => c.key === sc.key)
    if (col && sc.visible) {
      ordered.push(col)
      seen.add(col.key)
    }
  }

  for (const col of allColumns) {
    if (!seen.has(col.key)) {
      const saved = savedMap.get(col.key)
      if (saved && saved.visible) {
        ordered.push(col)
      }
      seen.add(col.key)
    }
  }

  if (!ordered.length) return allColumns
  return ordered
}
