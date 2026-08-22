import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

export const filterFields: FilterField[] = [
  { key: 'name', label: 'Имя', type: 'string' },
  { key: 'surname', label: 'Фамилия', type: 'string' },
  { key: 'nickname', label: 'Псевдоним', type: 'string' },
  { key: 'login', label: 'Логин', type: 'string' },
  { key: 'email', label: 'Email', type: 'string' },
  {
    key: 'active',
    label: 'Активность',
    type: 'active',
    options: [
      { label: 'Активен', value: true },
      { label: 'Неактивен', value: false },
    ],
  },
  { key: 'lastLogin', label: 'Последний вход', type: 'datetime' },
];
