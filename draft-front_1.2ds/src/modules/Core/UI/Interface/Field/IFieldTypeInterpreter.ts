import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';

export interface IFieldTypeInterpreter {
  isActive(field: FilterField, value: MaybeFilterValue): boolean;
  predicate(field: FilterField, value: MaybeFilterValue): (rowValue: unknown) => boolean;
  compare(field: FilterField, a: unknown, b: unknown): number;
  format(field: FilterField, value: MaybeFilterValue): string;
}
