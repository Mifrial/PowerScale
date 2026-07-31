import type { FieldDefinition } from './FieldDefinition'

export interface FilterField extends FieldDefinition {
  options?: { label: string; value: any }[]
}
