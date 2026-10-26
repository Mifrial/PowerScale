import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { RULE_CONTENT_STATUS_OPTIONS } from '@/modules/Roleplay/Rule/Constant/RULE_CONTENT_STATUS_OPTIONS';
import { RULE_TYPES } from '@/modules/Roleplay/Rule/Constant/RULE_TYPES';

/** Поля попапа FilterBar на каталоге правил пространства. */
export const RULE_LIST_FILTER_FIELDS: FilterField[] = [
  { key: 'name', label: 'Название', type: 'string' },
  {
    key: 'contentStatus',
    label: 'Редакционный статус',
    type: 'select',
    options: RULE_CONTENT_STATUS_OPTIONS.map((option) => ({ label: option.title, value: option.value })),
  },
  {
    key: 'type',
    label: 'Тип правила',
    type: 'select',
    options: RULE_TYPES.map((option) => ({ label: option.title, value: option.value })),
  },
];
