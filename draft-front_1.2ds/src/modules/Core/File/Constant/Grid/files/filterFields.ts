import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';

/** Поля фильтра админской страницы файлов; владелец дополняется списком пользователей. */
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
  { key: 'ownerId', label: 'Владелец', type: 'select', options: [] },
  {
    key: 'isArchived',
    label: 'Архив',
    type: 'select',
    options: [
      { label: 'Активные', value: false },
      { label: 'В архиве', value: true },
    ],
  },
  {
    key: 'isDerived',
    label: 'Производная',
    type: 'select',
    options: [
      { label: 'Только оригиналы', value: false },
      { label: 'Только производные', value: true },
    ],
  },
];
