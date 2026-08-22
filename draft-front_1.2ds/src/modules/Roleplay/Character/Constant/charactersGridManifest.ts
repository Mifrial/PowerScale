import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { CHARACTER_STATUS_OPTIONS } from '@/modules/Roleplay/Character/Constant/CHARACTER_STATUS_OPTIONS';

export const filterFields: FilterField[] = [
  { key: 'name', label: 'Имя', type: 'string' },
  {
    key: 'status',
    label: 'Статус',
    type: 'select',
    options: CHARACTER_STATUS_OPTIONS,
  },
  { key: 'raceLabel', label: 'Раса', type: 'string' },
  { key: 'ownerName', label: 'Владелец', type: 'string' },
  { key: 'gameName', label: 'Игра', type: 'string' },
  {
    key: 'inGame',
    label: 'В игре',
    type: 'select',
    options: [
      { label: 'В игре', value: true },
      { label: 'Без игры', value: false },
    ],
  },
];
