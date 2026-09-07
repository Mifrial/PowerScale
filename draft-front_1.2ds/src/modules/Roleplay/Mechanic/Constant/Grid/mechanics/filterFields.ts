import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

export const filterFields: FilterField[] = [
  { key: 'code', label: 'Код', type: 'string' },
  { key: 'name', label: 'Название', type: 'string' },
  { key: 'version', label: 'Версия', type: 'string' },
];
