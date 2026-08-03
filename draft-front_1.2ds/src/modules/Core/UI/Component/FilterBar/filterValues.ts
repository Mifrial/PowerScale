import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'
import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'

export type MaybeFilterValue = FilterValue | null | undefined

export interface ActiveChip {
  key: string
  label: string
}

function isDateTimeField(field: FilterField): boolean {
  return field.type === 'datetime' || field.type === 'date'
}

export function isFilterActive(field: FilterField, value: MaybeFilterValue): boolean {
  if (field.type === 'boolean') return value === true
  if (value === undefined || value === null) return false
  if (typeof value === 'string') return value !== ''
  if (typeof value === 'object') {
    if (value.mode === 'equals' || value.mode === 'contains') return !!value.value
    if (value.mode === 'from') return value.from !== undefined && value.from !== null
    if (value.mode === 'to') return value.to !== undefined && value.to !== null
    if (value.mode === 'interval') return value.from !== undefined || value.to !== undefined
    return true
  }
  return true
}

export function formatDatetime(iso: string): string {
  try {
    const d = new Date(iso)
    if (isNaN(d.getTime())) return iso
    return d.toLocaleDateString('ru-RU', {
      day: '2-digit', month: '2-digit', year: 'numeric',
    }) + ' ' + d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
  } catch {
    return iso
  }
}

export function formatFilterChip(field: FilterField, value: MaybeFilterValue): string {
  if (field.type === 'boolean') return field.label
  if (typeof value === 'object' && value !== null) {
    const dt = isDateTimeField(field)
    if (value.mode === 'contains') return `${field.label}: содержит "${value.value}"`
    if (value.mode === 'equals' || value.mode === undefined) return `${field.label}: ${dt ? formatDatetime(String(value.value ?? '')) : value.value ?? ''}`
    if (value.mode === 'from') return `${field.label}: с ${dt ? formatDatetime(String(value.from ?? '')) : value.from ?? '...'}`
    if (value.mode === 'to') return `${field.label}: до ${dt ? formatDatetime(String(value.to ?? '')) : value.to ?? '...'}`
    if (value.mode === 'interval') {
      const parts: string[] = []
      if (value.from !== undefined && value.from !== null) parts.push(`с ${dt ? formatDatetime(String(value.from)) : value.from}`)
      if (value.to !== undefined && value.to !== null) parts.push(`до ${dt ? formatDatetime(String(value.to)) : value.to}`)
      return `${field.label}: ${parts.join(' ')}`
    }
    return `${field.label}: ${JSON.stringify(value)}`
  }
  if (typeof value === 'string' && isDateTimeField(field)) {
    return `${field.label}: ${formatDatetime(value)}`
  }
  if ((field.type === 'select' || field.type === 'active') && field.options) {
    const option = field.options.find(opt => opt.value === value)
    if (option) return `${field.label}: ${option.label}`
  }
  return `${field.label}: ${value}`
}

export function buildActiveChips(
  fields: FilterField[],
  values: Record<string, MaybeFilterValue>,
): ActiveChip[] {
  return fields
    .filter(f => isFilterActive(f, values[f.key]))
    .map(f => ({
      key: f.key,
      label: formatFilterChip(f, values[f.key]),
    }))
}
