import type { FieldMeta } from '@/modules/Core/UI/Dto/Field/Meta';

export interface FieldDefinition {
  key: string;
  label: string;
  type: string;
  meta?: FieldMeta;
}
