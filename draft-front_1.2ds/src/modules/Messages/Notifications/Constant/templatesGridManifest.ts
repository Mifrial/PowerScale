import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'

export const columns: ColumnDefinition[] = [
  { key: 'key', label: 'Ключ', type: 'string', meta: { clickable: true } },
  { key: 'titleTemplate', label: 'Заголовок', type: 'string' },
  {
    key: 'active',
    label: 'Статус',
    type: 'boolean',
    meta: {
      trueLabel: 'Активен',
      falseLabel: 'Удалён',
      trueIcon: 'mdi-check-circle',
      falseIcon: 'mdi-cancel',
      trueColor: 'success',
      falseColor: 'grey',
    },
  },
]

export const filterFields: FilterField[] = [
  { key: 'key', label: 'Ключ', type: 'string' },
  { key: 'active', label: 'Статус', type: 'select', options: [{ label: 'Активен', value: true }, { label: 'Удалён', value: false }] },
]
