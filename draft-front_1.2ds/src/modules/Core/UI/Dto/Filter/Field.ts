import type { FieldDefinition } from '@/modules/Core/UI/Dto/Field/Definition';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterOptionValue';

export interface FilterField extends FieldDefinition {
  options?: { label: string; value: FilterOptionValue }[];
  treeOptions?: {
    label: string;
    value: FilterOptionValue;
    path: string;
    depth: number;
    parentValue?: FilterOptionValue | null;
  }[];
}
