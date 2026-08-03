import type { FilterField } from '@/modules/Core/UI/Dto/FilterField';

export const filterFields: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  {
    key: 'active',
    label: 'Активность',
    type: 'active',
    options: [
      { label: 'Активен', value: true },
      { label: 'Неактивен', value: false },
    ],
  },
];
