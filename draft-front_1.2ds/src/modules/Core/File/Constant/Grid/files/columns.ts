import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export const columns: ColumnDefinition[] = [
  { key: 'id', label: 'ID', type: 'number', sortable: false },
  { key: 'originalName', label: 'Имя', type: 'string', sortable: false },
  { key: 'mime', label: 'Тип', type: 'string', sortable: false },
  { key: 'size', label: 'Размер', type: 'number', sortable: false },
  { key: 'width', label: 'Ширина', type: 'number', sortable: false },
  { key: 'height', label: 'Высота', type: 'number', sortable: false },
  { key: 'ownerId', label: 'Владелец', type: 'number', sortable: false },
  {
    key: 'isArchived',
    label: 'Архив',
    type: 'boolean',
    sortable: false,
    meta: {
      trueLabel: 'В архиве',
      falseLabel: 'Активен',
      trueIcon: 'mdi-archive',
      falseIcon: 'mdi-check-circle',
      trueColor: 'grey',
      falseColor: 'success',
    },
  },
  {
    key: 'isDerived',
    label: 'Производная',
    type: 'boolean',
    sortable: false,
    meta: {
      trueLabel: 'Да',
      falseLabel: 'Нет',
      trueIcon: 'mdi-file-link',
      falseIcon: 'mdi-file',
      trueColor: 'info',
      falseColor: 'grey',
    },
  },
  { key: 'createdAt', label: 'Загружен', type: 'datetime', sortable: false },
];
