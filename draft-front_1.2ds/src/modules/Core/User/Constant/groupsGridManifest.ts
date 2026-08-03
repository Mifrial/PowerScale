import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'
import type { FilterField } from '@/modules/Core/UI/Dto/FilterField'

export const columns: ColumnDefinition[] = [
  { key: 'name', label: 'Название', type: 'string', meta: { clickable: true } },
  { key: 'memberCount', label: 'Участники', type: 'number' },
  {
    key: 'active',
    label: 'Статус',
    type: 'boolean',
    meta: {
      trueLabel: 'Активна',
      falseLabel: 'Деактивирована',
      trueIcon: 'mdi-check-circle',
      falseIcon: 'mdi-cancel',
      trueColor: 'success',
      falseColor: 'grey',
    },
  },
]

export const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  { key: 'active', label: 'Статус', type: 'select', options: [{ label: 'Активна', value: true }, { label: 'Деактивирована', value: false }] },
]
