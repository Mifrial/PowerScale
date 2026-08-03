import type { FieldMeta } from '@/modules/Core/UI/Dto/FieldMeta'

export interface FieldDefinition {
  key: string
  label: string
  type: string
  meta?: FieldMeta
}
