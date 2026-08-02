import type { FilterValue } from '@/modules/Core/UI/Dto/FilterValue'

export function extractFilterValue(v: FilterValue | undefined): string {
  if (v === undefined || v === null) return ''
  if (typeof v === 'string') return v
  if (typeof v === 'boolean') return String(v)
  if (typeof v === 'object' && (v.mode === 'equals' || v.mode === 'contains') && v.value !== undefined) {
    return String(v.value)
  }
  return ''
}

export function extractStringFilter(v: FilterValue | undefined): { mode: 'equals' | 'contains'; value: string } | null {
  if (v === undefined || v === null) return null
  if (typeof v === 'string') {
    return v ? { mode: 'contains', value: v } : null
  }
  if (typeof v === 'object' && (v.mode === 'equals' || v.mode === 'contains') && v.value !== undefined) {
    return { mode: v.mode === 'equals' ? 'equals' : 'contains', value: String(v.value) }
  }
  return null
}
