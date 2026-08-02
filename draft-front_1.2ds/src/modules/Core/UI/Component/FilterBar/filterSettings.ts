import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'

export interface FilterFieldSetting {
  key: string
  visible: boolean
}

export interface FilterSettings {
  fields: FilterFieldSetting[]
}

const STORAGE_PREFIX = 'filterbar_'

function storageKey(settingsKey: string) {
  return `${STORAGE_PREFIX}${settingsKey}`
}

export function loadFilterSettings(settingsKey: string): FilterSettings | null {
  try {
    const raw = localStorage.getItem(storageKey(settingsKey))
    if (!raw) return null
    return JSON.parse(raw) as FilterSettings
  } catch {
    return null
  }
}

export function saveFilterSettings(settingsKey: string, settings: FilterSettings) {
  try {
    localStorage.setItem(storageKey(settingsKey), JSON.stringify(settings))
  } catch {
    /* localStorage not available */
  }
}

export function buildVisibleFields(
  allFields: FilterField[],
  settings: FilterSettings | null,
): FilterField[] {
  if (!settings) return allFields

  const savedMap = new Map(settings.fields.map(f => [f.key, f.visible]))
  const visible = allFields.filter(f => savedMap.get(f.key) !== false)
  if (!visible.length) return allFields
  return visible
}
