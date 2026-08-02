import type { FieldMeta } from './FieldMeta'

export interface FieldDefinition {
  key: string
  label: string
  type: string
  meta?: FieldMeta
}
