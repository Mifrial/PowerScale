import type { FieldDefinition } from '@/modules/Core/UI/Dto/FieldDefinition';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/FilterValue';

export interface FilterField extends FieldDefinition {
  options?: { label: string; value: FilterOptionValue }[];
}
