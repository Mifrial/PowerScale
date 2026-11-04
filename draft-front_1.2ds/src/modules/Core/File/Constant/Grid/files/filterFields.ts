import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

export const filterFields: FilterField[] = [
  { key: 'createdAt', label: 'Период', type: 'datetime' },
  {
    key: 'mime',
    label: 'Тип',
    type: 'select',
    options: [
      { label: 'PNG', value: 'image/png' },
      { label: 'JPEG', value: 'image/jpeg' },
      { label: 'WebP', value: 'image/webp' },
    ],
  },
  { key: 'ownerId', label: 'Владелец', type: 'number', modes: ['equals'], meta: { min: 1 } },
  {
    key: 'isArchived',
    label: 'Архив',
    type: 'select',
    options: [
      { label: 'Активные', value: false },
      { label: 'В архиве', value: true },
    ],
  },
];
