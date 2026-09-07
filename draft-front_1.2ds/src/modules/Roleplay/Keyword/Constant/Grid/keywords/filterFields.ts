import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

export const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  {
    key: 'active',
    label: 'Статус',
    type: 'select',
    options: [
      { label: 'Активен', value: true },
      { label: 'Удалён', value: false },
    ],
  },
];
