import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

export const filterFields: FilterField[] = [
  { key: 'q', label: 'Поиск', type: 'string' },
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
