import type { FieldDefinition } from './FieldDefinition'

export interface ColumnDefinition extends FieldDefinition {
  sortable?: boolean
  editable?: boolean
  width?: string | number
}
