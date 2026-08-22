import type { FieldDefinition } from '@/modules/Core/UI/Dto/Field/Definition';

export interface ColumnDefinition extends FieldDefinition {
  sortable?: boolean;
  editable?: boolean;
  width?: string | number;
}
