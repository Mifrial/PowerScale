import type { StringFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/StringFilterValue';
import type { NumberFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/NumberFilterValue';
import type { DateTimeFilterValue } from '@/modules/Core/UI/Dto/Filter/Values/DateTimeFilterValue';

export type FilterValue = boolean | string | number | StringFilterValue | NumberFilterValue | DateTimeFilterValue;
