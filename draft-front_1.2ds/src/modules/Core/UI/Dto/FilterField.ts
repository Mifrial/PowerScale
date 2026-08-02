import type { FieldDefinition } from './FieldDefinition'
import type { FilterOptionValue } from './FilterValue'

export interface FilterField extends FieldDefinition {
  options?: { label: string; value: FilterOptionValue }[]
}
