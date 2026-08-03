import type { FieldDefinition } from '@/modules/Core/UI/Dto/FieldDefinition'

export interface ColumnDefinition extends FieldDefinition {
  sortable?: boolean
  editable?: boolean
  width?: string | number
}
