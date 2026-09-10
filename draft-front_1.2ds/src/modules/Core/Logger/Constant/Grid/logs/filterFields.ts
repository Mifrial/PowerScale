import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import { LOGGER_LEVEL_OPTIONS } from '@/modules/Core/Logger/Constant/LoggerLevelOptions';

export const filterFields: FilterField[] = [
  { key: 'createdAt', label: 'Период', type: 'datetime' },
  { key: 'level', label: 'Уровень', type: 'select', options: LOGGER_LEVEL_OPTIONS },
  { key: 'source', label: 'Источник', type: 'string' },
  { key: 'errorCode', label: 'Код ошибки', type: 'string' },
];
